<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $builder->getData();
        $builder
            ->add('email', null, ['label' => '* E-mail :'])
            ->add('firstname', null, ['label' => '* Prénom :'])
            ->add('lastname', null, ['label' => '* Nom :'])
            ->add('pictureFile', FileType::class, [
                'label' => '* Image',
                'mapped' => false,
                'required' => !$user->getPicture(),
                'constraints' => [
                    new Image([
                        'mimeTypesMessage' => 'Veuillez soumettre un fichier image',
                        'maxSize' => '1M',
                        'maxSizeMessage' => 'Votre image fait {{ size }} {{ suffix }}. La limite est de {{ limite }} {{ suffix }}'
                    ])
                ]
            ])
            ->add('password',  PasswordType::class, ['label' => '* Mot de passe :']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
