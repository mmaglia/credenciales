<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Usuario;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class RegistrationController extends AbstractController
{
    /**
     * @Route("/registro", name="registro")
     */
    public function registro(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $form = $this->createFormBuilder()
            ->add('usuario')
            ->add('password', RepeatedType::class, [
                    'type' => PasswordType::class,
                    'required' => true,
                    'first_options' => ['label' => 'Contraseña'],
                    'second_options' => ['label' => 'Confirme Contraseña']
            ])
            ->add('dni', NumberType::class)
            ->add('apellido')
            ->add('nombre')
            ->add('email', EmailType::class)
            ->add('Registrar_Usuario', SubmitType::class, [
                'attr' => [
                    'class' => 'btn btn-success float-right'
                ]
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $data = $form->getData();

            $usuario = new Usuario();
            $usuario->setUsername($data['usuario']);
            $usuario->setPassword(
                $passwordEncoder->encodePassword($usuario, $data['password'])
            );
            $usuario->setDni($data['dni']);
            $usuario->setApellido($data['apellido']);
            $usuario->setNombre($data['nombre']);
            $usuario->setEmail($data['email']);
            $usuario->setFechaAlta(new \DateTime());

            $em = $this->getDoctrine()->getManager();

            $em->persist($usuario);
            $em->flush();

            return $this->redirect($this->generateUrl('app_login'));

        }

        return $this->render('registration/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
