<?php

namespace App\Form;

use App\DTO\SeriesAdvancedSearchDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SeriesAdvancedSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('language')
            ->add('timezone')
            ->add('watchRegion')
            ->add('airDateGTE')
            ->add('airDateLTE')
            ->add('firstAirDateYear')
            ->add('firstAirDateGTE')
            ->add('firstAirDateLTE')
            ->add('withOriginCountry')
            ->add('withOriginalLanguage')
            ->add('withWatchMonetizationTypes')
            ->add('withWatchProviders')
            ->add('withRuntimeGTE')
            ->add('withRuntimeLTE')
            ->add('withStatus')
            ->add('withType')
            ->add('sortBy')
            ->add('page')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SeriesAdvancedSearchDTO::class,
        ]);
    }
}
