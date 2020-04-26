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

class CredencialController extends AbstractController
{
    /**
     * @Route("/credencial", name="credencial_index", methods={"GET"})
     */
    public function index(SessionInterface $session)
    {

        $finder = new Finder();

        // Busco todos los archivos de tipo 'pdf' en el directorio /public/pdf'
        // (definida la ruta en config/services.yaml)
        $finder->files()->in('pdf')->sortByName();

        if ($this->isGranted('ROLE_ADMIN')) {
            // Para ROLE_ADMIN muestra listado de credenciales (archivos PDF) con sus acciones posibles
            $archivos = [];
            foreach ($finder as $file) {
                $archivos[] = $file->getRelativePathname();
            }        

            return $this->render('credencial/index.html.twig', [
                'apellido' => '',
                'nombre' => '',
                'mensaje' => '',
                'sugerencia' => '',
                'archivos' => $archivos,
                'controller_name' => 'CredencialController',
            ]);
        }
        else {
            // Sino, busca una credencial en base al apellido y nombre ingresados y la muestra
            $apellido = $session->get('apellido');
            $nombre = $session->get('nombre');
            
            $encontrado = [];
            foreach ($finder as $file) {
                $fileNameWithExtension = $file->getRelativePathname(); // Obtengo el nombre del Archivo
            
                if (!(strripos($fileNameWithExtension, $apellido) === false || strripos($fileNameWithExtension, $nombre) === false))
                {                    
                    // Veo si el string del apellido y del nombre están contenidos en el nombre del archivo
                    // Si así, lo agrego en $encontrado
                    $encontrado[] = $fileNameWithExtension;
                }
            }        

            switch (count($encontrado)) {
                case 0:
                    // Si no encontró ningún archivo coincidente muestra mensaje
                    $mensaje = 'No se ha encontrado credencial para';
                    $sugerencia = 'Verifique por favor que haber ingresado correctamente el Apellido y Nombre';
                    break;
                case 1:
                    // Si encontró un archivo coincidente los muestra
                    return new Response(file_get_contents('pdf/' . $encontrado[0]), 200, array('Content-Type' => 'application/pdf'));
                    break;
                default:
                    // Si encontró más de un archivo que coincida, muestra mensaje pertinente
                    $mensaje = 'Se ha encontrado más de una credencial que coincide para';
                    $sugerencia = 'En lugar de su primer nombre pruebe con su segundo nombre';
            }


            return $this->render('credencial/index.html.twig', [
                'apellido' => $apellido,
                'nombre' => $nombre,
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
    public function upload(Request $request, SluggerInterface $slugger)
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
                        $credencialFile->move(
                            $this->getParameter('credenciales_directory'), $newFilename);
                    } catch (FileException $e) {
                        echo "Ocurrió un error al intentar subir el archivo $newFilename ";
                    }
                }

                // Procesa mensaje de tipo Flash
                if ($cantidad ==  1)
                    $this->addFlash('info', 'Se ha añadido 1 crendencial al Repositorio');
                elseif ($cantidad > 1)
                    $this->addFlash('info', $cantidad . ' credenciales agregadas al repositorio');

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
    public function delete(Request $request)
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
        } catch (IOExceptionInterface $exception) {
            echo "Ocurrió un error al intentar borrar el archivo $archivo " . $exception->getPath();
        }
       
        return $this->redirectToRoute('credencial_index');
    }

    /**
     * @Route("/deleteAll", name="deleteAll", methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function deleteAll(Request $request)
    {
        $filesystem = new Filesystem();

        $finder = new Finder();
        // Busco todos los archivos de tipo PDF en el repositorio
        $finder->files()->in('pdf');

        try {
            // Itero y borro uno a uno cada uno de los archivos
            foreach ($finder as $file) {
                $filesystem->remove($file);
            }        

            $this->addFlash('info', 'Se ha vaciado el repositorio de credenciales');
        } catch (IOExceptionInterface $exception) {
            echo "Ocurrió un error al intentar vaciar el directorio " . $exception->getPath();
        }
       
        return $this->redirectToRoute('credencial_index');
    }
}
