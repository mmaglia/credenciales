<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

use App\Form\CredencialUploadType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;


class CredencialController extends AbstractController
{
    /**
     * @Route("/credencial", name="credencial_index", methods={"GET", "POST"})
     */
    public function index(Request $request, LoggerInterface $logger, SessionInterface $session)
    {

        $finder = new Finder();

        // Busco todos los archivos de tipo 'pdf' en el directorio /public/pdf' (definida la ruta en config/services.yaml)
        $finder->files()->in('pdf')->sortByName();

        // Para ROLE_ADMIN muestra listado de credenciales (archivos PDF) con sus acciones posibles
        if ($this->isGranted('ROLE_ADMIN')) {
            // Procesa filtro y lo mantiene en sesión del usuario
            if (is_null($session->get('filtroFilenameCredencial'))) { // Verifica si es la primera vez que ingresa el usuario
                // Establece el primero por defecto
                $filtroFilename = '';
            } else {
                if (is_null($request->request->get('filename'))) { // Verifica si ingresa sin indicación de filtro (refresco de la opción atendido)
                    // Mantiene el filtro actual
                    $filtroFilename = $session->get('filtroFilenameCredencial');
                } else {
                    // Activa el filtro seleccionado
                    $filtroFilename = $request->request->get('filename');
                }
            }
            $session->set('filtroFilenameCredencial', $filtroFilename); // Almacena en session el filtro actual

            // Recorre lista de archivos PDF y procesa eventuales filtros por nombre
            $archivos = [];
            foreach ($finder as $file) {
                $fileNameWithExtension = $file->getRelativePathname(); // Obtengo el nombre del Archivo

                if (!$filtroFilename || !(strripos($fileNameWithExtension, $filtroFilename) === false))
                {
                    $archivos[] = $file->getRelativePathname();
                }
            }

            $fileList = [];
            $i = 0;
            $vistos = 0;
            $noVistos = 0;
            foreach($archivos as $archivo) {
                // Nombre del Archivo en el FileSystem
                $fileList[$i]['archivo'] = $archivo;

                // Obtengo el DNI (la cadena inicial hasta el primer blanco del nombre del archivo)
                $dividoBlanco = explode(' ', $archivo);
                $fileList[$i]['dni'] = $dividoBlanco[0];

                // Obtengo el Nombre de la Persona (lo que sigue al blanco, luego del DNI, y hasta el punto de la extensión del archivo)
                $dividoPunto = explode('.', substr($archivo, strlen($dividoBlanco[0])));
                $fileList[$i]['nombre'] = trim($dividoPunto[0]);
                
                // Consulto si el último elemento, luego de dividirlo la cadena por punto (.) contiene la cadena 'visto'
                $fileList[$i]['visto'] = (end($dividoPunto) == 'visto');

                if (end($dividoPunto) == 'visto')
                    $vistos++;
                else
                    $noVistos++;

                $i++;
            }

            if (count($fileList)) {
                // Obtengo una lista de columnas de cada uno de los elementos del fileList
                foreach ($fileList as $clave => $fila) {
                    $nombre[$clave] = $fila['nombre'];
                    $dni[$clave] = $fila['dni'];
                    $visto[$clave] = $fila['visto'];
                }
                
                // Ordena el fileList
                array_multisort($visto, SORT_ASC, $nombre, SORT_ASC, $dni, SORT_ASC, $fileList);
            }

            return $this->render('credencial/index.html.twig', [
                'dni' => '',
                'mensaje' => '',
                'sugerencia' => '',
                'archivos' => $fileList,
                'total' => $i,
                'vistos' => $vistos,
                'noVistos' => $noVistos,
                'filtroFilename' => $filtroFilename,
                'controller_name' => 'CredencialController',
            ]);
        }
        else {
            // Sino, busca una credencial en base al DNI ingresado y la muestra
            $dni = $session->get('dni');
            
            $encontrado = [];
            foreach ($finder as $file) {
                $fileNameWithExtension = $file->getRelativePathname(); // Obtengo el nombre del Archivo
            
                if (!(strripos($fileNameWithExtension, $dni) === false))
                {                    
                    // Veo si el string del DNI están contenidos en el nombre del archivo
                    // Si así, lo agrego en $encontrado
                    $encontrado[] = $fileNameWithExtension;
                }
            }        

            switch (count($encontrado)) {
                case 0:
                    // Si no encontró ningún archivo coincidente muestra mensaje
                    $logger->info('Credencial no encontrada', ['dni' => $dni]);                    
                    $mensaje = 'No se ha encontrado credencial para el DNI ';
                    $sugerencia = 'Verifique por favor de haber ingresado correctamente el número';
                    break;
                case 1:
                    // Encontró un archivo coincidente
                    // Lo renombra con sufico .visto si es la primera vez que se accede
                    $nombreArchivo = $encontrado[0];
                    if (substr($encontrado[0], -6) != '.visto') {
                        $filesystem = new Filesystem();
                        try {
                            $nombreArchivo = $encontrado[0] . '.visto';
                            $filesystem->rename('pdf/' . $encontrado[0], 'pdf/' . $nombreArchivo );
                        } catch (IOExceptionInterface $exception) {
                            echo "Ocurrió un error al intentar renombrar el archivo $archivo ";
                        }
                    }
            
                    // Lo muestra
                    $logger->info('Se muestra credencial', ['Archivo' => 'pdf/' . $encontrado[0]]);                    

                    return new Response(file_get_contents('pdf/' . $nombreArchivo), 200, array('Content-Type' => 'application/pdf'));
                    break;
                default:
                    // Si encontró más de un archivo que coincida, muestra mensaje pertinente
                    $logger->info('Coincidencia Múltiple', ['dni' => $dni]);                    
                    $mensaje = 'Se ha encontrado más de una credencial que coincide con el DNI ';
                    $sugerencia = 'No es posible proceer con su descarga.';
            }


            return $this->render('credencial/index.html.twig', [
                'dni' => $dni,
                'mensaje' => $mensaje,
                'sugerencia' => $sugerencia,
                'controller_name' => 'CredencialController',
            ]);
        }
    }


    /**
     * @Route("/upload", name="upload", methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function upload(Request $request, SluggerInterface $slugger, LoggerInterface $logger)
    {
        $form = $this->createForm(CredencialUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $credencialFiles = $form->get('credencial')->getData();

            // Si se seleccionó al menos un archivo PDF lo procesa
            if ($credencialFiles) {
                $cantidad = 0;
                // Para cada archivo seleccionado
                foreach ($credencialFiles as $credencialFile) {
                    // Deja en newFilename el nombre del archivo original
                    $originalFilename = pathinfo($credencialFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $newFilename = $originalFilename . '.' .$credencialFile->guessExtension();

                    // Y lo mueve al repositorio (definido en config/services.yaml)
                    try {
                        $cantidad++;
                        $credencialFile->move($this->getParameter('credenciales_directory'), $newFilename);
                        $logger->info('Credencial subida al repositorio', ['Archivo' => 'pdf/' . $newFilename, 'Usuario' => $this->getUser()->getUsuario()]); 
                    } catch (FileException $e) {
                        echo "Ocurrió un error al intentar subir el archivo $newFilename ";
                    }
                }

                // Procesa mensaje de tipo Flash
                if ($cantidad ==  1) {
                    $info = 'Se ha añadido 1 crendencial al Repositorio';
                    $this->addFlash('info', $info);
                    $logger->info($info);                    
                } elseif ($cantidad > 1) {
                    $info = $cantidad . ' credenciales agregadas al repositorio';
                    $this->addFlash('info', $info);
                    $logger->info($info);                    
                }

            }

            return $this->redirect($this->generateUrl('credencial_index'));
        }

        return $this->render('credencial/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/delete/{credencial}", name="delete", methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function delete(Request $request, LoggerInterface $logger)
    {
        // Obtiene el nombre del archivo a borrar
        $ruta = explode('/', $request->getPathInfo());

        // Le saca cualquier htmlentities para dejar el nombre del archivo tal cual 
        // (por si tuviera espacios en blanco entre otras cosas)
        $archivo = urldecode(end($ruta));

        $filesystem = new Filesystem();

        try {
            // Borro credencial y armo mensaje Flash
            $filesystem->remove('pdf/' . $archivo);
            $this->addFlash('info', $archivo . ' ha sido borrado');
            $logger->info('Se borró una credencial', ['archivo' => 'pdf/' . $archivo, 'Usuario' => $this->getUser()->getUsuario() ]);   
        } catch (IOExceptionInterface $exception) {
            echo "Ocurrió un error al intentar borrar el archivo $archivo " . $exception->getPath();
        }
       
        return $this->redirectToRoute('credencial_index');
    }

    /**
     * @Route("/deleteAll", name="deleteAll", methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function deleteAll(Request $request, LoggerInterface $logger)
    {
        $filesystem = new Filesystem();

        $finder = new Finder();
        // Busco todos los archivos de tipo PDF en el repositorio
        $finder->files()->in('pdf');

        try {
            // Itero y borro uno a uno cada uno de los archivos
            foreach ($finder as $file) {
                $filesystem->remove($file);
                $logger->info('Se borró una credencial', ['archivo' => $file ]); 
            }        

            $this->addFlash('info', 'Se ha vaciado el repositorio de credenciales');
            $logger->info('Se ha vaciado el repositorio de credenciales'); 
            
        } catch (IOExceptionInterface $exception) {
            echo "Ocurrió un error al intentar vaciar el directorio " . $exception->getPath();
        }
       
        return $this->redirectToRoute('credencial_index');
    }
}
