<?php

namespace App\Form;

use App\DTO\MapDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MapType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('page', HiddenType::class, [
                'empty_data' => 1,
                'required' => false,
            ])
            ->add('perPage', ChoiceType::class, [
                'attr' => ['onchange' => 'this.form.submit()'],
                'choice_translation_domain' => false,
                'choices' => [
                    '10' => 10,
                    '20' => 20,
                    '50' => 50,
                    '100' => 100,
                ],
                'label' => 'Results per page',
            ])
            ->add('type', ChoiceType::class, [
                'attr' => ['onchange' => 'this.form.submit()'],
                'choices' => [
                    'map additions' => 'creation',
                    'map updates' => 'update',
                ],
                'label' => 'Type',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MapDTO::class,
        ]);
    }
}
