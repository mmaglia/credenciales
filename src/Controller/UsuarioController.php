<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Form\UsuarioType;
use App\Repository\UsuarioRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * @Route("/usuario")
 * @IsGranted("ROLE_ADMIN")
 */
class UsuarioController extends AbstractController
{
    /**
     * @Route("/", name="usuario_index", methods={"GET"})
     */
    public function index(UsuarioRepository $usuarioRepository): Response
    {
        return $this->render('usuario/index.html.twig', [
            'usuarios' => $usuarioRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="usuario_new", methods={"GET","POST"})
     */
    function new (Request $request, UserPasswordEncoderInterface $passwordEncoder): Response {
        $usuario = new Usuario();
        $form = $this->createForm(UsuarioType::class, $usuario);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();

            $usuario->setFechaAlta(new \DateTime());
            $usuario->setPassword(
                $passwordEncoder->encodePassword($usuario, $form->get("password")->getData())
            );
            $usuario->setRoles(array_values($form->get("roles")->getData()));
            $usuario->setDni($form->get("dni")->getData());
            $usuario->setApellido($form->get("apellido")->getData());
            $usuario->setNombre($form->get("nombre")->getData());
            $usuario->setEmail($form->get("email")->getData());
            $entityManager->persist($usuario);

            $entityManager->flush();
            return $this->redirectToRoute('usuario_index');
        }

        return $this->render('usuario/new.html.twig', [
            'usuario' => $usuario,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="usuario_show", methods={"GET"})
     */
    public function show(Usuario $usuario): Response
    {
        return $this->render('usuario/show.html.twig', [
            'usuario' => $usuario,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="usuario_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Usuario $usuario, UserPasswordEncoderInterface $passwordEncoder): Response
    {
        $form = $this->createForm(UsuarioType::class, $usuario);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $usuario->setPassword(
                $passwordEncoder->encodePassword($usuario, $form->get("password")->getData())
            );

            $usuario->setRoles(array_values($form->get("roles")->getData()));

            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('usuario_index');
        }

        return $this->render('usuario/edit.html.twig', [
            'usuario' => $usuario,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="usuario_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Usuario $usuario): Response
    {
        if ($this->isCsrfTokenValid('delete' . $usuario->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();

            //No borro definitvamente el usuario sino que le seteo una fecha de baja (baja lógica)
            $usuario->setFechaBaja(new \DateTime('now'));
            $entityManager->flush();
        }

        return $this->redirectToRoute('usuario_index');
    }
}
