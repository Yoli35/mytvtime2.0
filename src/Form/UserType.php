<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'empty_data' => '',
                'label' => 'Username',
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'empty_data' => '',
                'label' => 'Email',
                'required' => true,
            ])
            ->add('country', CountryType::class, [
                'empty_data' => '',
                'label' => 'Country',
                'required' => false,
            ])
            ->add('preferredLanguage', LanguageType::class, [
                'empty_data' => '',
                'label' => 'Preferred Language',
                'required' => false,
            ])
            ->add('timezone', TimezoneType::class, [
                'empty_data' => '',
                'label' => 'Timezone',
                'required' => false,
            ])
            ->add('avatarFile', VichImageType::class, [
                'label' => 'Avatar',
                'required' => false,
            ])
            ->add('bannerFile', VichImageType::class, [
                'label' => 'Banner',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
