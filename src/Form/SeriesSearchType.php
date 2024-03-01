<?php

namespace App\Form;

use App\DTO\SeriesSearchDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SeriesSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('query', TextType::class, [
                'label' => 'Name of the series',
                'required' => true,
            ])
            ->add('firstAirDateYear', NumberType::class, [
                'label' => 'First air date year',
                'required' => false,
            ])
            ->add('page', HiddenType::class, [
                'data' => 1,
            ])
            ->add('language', HiddenType::class, [
                'data' => 'fr',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SeriesSearchDTO::class,
        ]);
    }
}
