<?php

namespace App\Form;

use App\Entity\ContactMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => ['class' => 'w100'],
                'empty_data' => '',
                'label' => 'Name',
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'attr' => ['class' => 'w100'],
                'empty_data' => '',
                'label' => 'Email',
                'required' => true,
            ])
            ->add('subject', TextType::class, [
                'attr' => ['class' => 'w100'],
                'empty_data' => '',
                'label' => 'Subject',
                'required' => true,
            ])
            ->add('message', TextareaType::class, [
                'attr' => ['class' => 'w100'],
                'empty_data' => '',
                'label' => 'Message',
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactMessage::class,
        ]);
    }
}
