<?php

namespace App\Form;

use App\Entity\Usuario;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class UsuarioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('username', null,
            [   'label' => 'Usuario',
                'required' => true,
                'attr' => array('autofocus' => null, 'maxlength' => '20')
            ])
        ->add('password', RepeatedType::class, [
            'type' => PasswordType::class,
            'required' => true,
            'first_options' => ['label' => 'Contraseña'],
            'second_options' => ['label' => 'Confirme Contraseña']
            ])
        ->add('dni', NumberType::class, ['label' => 'DNI', 'required' => false, 'attr' => ['max' => '99999999']])
        ->add('apellido', null, ['label' => 'Apellido', 'attr' => array('maxlength' => '50')])
        ->add('nombre', null, ['label' => 'Nombre', 'attr' => array('maxlength' => '50')])
        ->add('email', EmailType::class, ['label' => 'Correo', 'required' => false, 'attr' => array('maxlength' => '100')])
        ->add('roles', ChoiceType::class, [
            'multiple' => true,
            'expanded' => true, // render check-boxes
            'label'    => 'Rol',
            'choices'  => [
                'Consultor' => 'ROLE_CONSULTOR',
                'Editor' => 'ROLE_EDITOR',
                'Auditoría de Gestión' => 'ROLE_AUDITORIA_GESTION',
                'Administrador' => 'ROLE_ADMIN',
                'Super Admin' => 'ROLE_SUPER_ADMIN'
            ]])
;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Usuario::class,
        ]);
    }
}
