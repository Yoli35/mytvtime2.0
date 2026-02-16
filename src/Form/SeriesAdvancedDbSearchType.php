<?php

namespace App\Form;

use App\DTO\SeriesAdvancedDbSearchDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SeriesAdvancedDbSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        dump($options);
        $countries = $options['countries'];
        $builder
            ->add('firstAirDateYear', NumberType::class, [
                'label' => 'First air date year',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'autofocus' => 'autofocus',
                    'placeholder' => '2000'
                ],
                'translation_domain' => false,
            ])
            ->add('firstAirDateGTE', DateType::class, [
                'label' => 'After',
                'required' => false,
                'empty_data' => '',
                'widget' => 'single_text',
            ])
            ->add('firstAirDateLTE', DateType::class, [
                'label' => 'Before',
                'required' => false,
                'empty_data' => '',
                'widget' => 'single_text',
            ])
            ->add('withOriginCountry', ChoiceType::class, [
                'choices' => $countries,
                'label' => 'Origin country',
                'placeholder' => '',
                'required' => false,
            ])
            ->add('withOriginalLanguage', LanguageType::class, [
                'label' => 'Original language',
                'required' => false,
                'empty_data' => '',
            ])
//            ->add('withWatchMonetizationTypes', ChoiceType::class, [
//                'label' => 'Monetization type',
//                'required' => false,
//                'empty_data' => '',
//                'choices' => [
//                    'Flatrate' => 'flatrate',
//                    'Free' => 'free',
//                    'Ads' => 'ads',
//                    'Rent' => 'rent',
//                    'Buy' => 'buy',
//                ],
//            ])
//            ->add('withWatchProviders', ChoiceType::class, [
//                'label' => 'Watch providers',
//                'required' => false,
//                'empty_data' => '',
//                'choices' => $options['data']->getWatchProviders(),
//                'choice_translation_domain' => false,
//            ])
            ->add('withKeywords', HiddenType::class, [
                'label' => 'Keywords',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('keywordSeparator', HiddenType::class, [
                'data' => ',',
                'empty_data' => ',',
            ])
            ->add('withStatus', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Returning Series' => 'Returning Series',
                    'Planned' => 'Planned',
                    'In Production' => 'In Production',
                    'Ended' => 'Ended',
                    'Canceled' => 'Canceled',
                    'Pilot' => 'Pilot',
                ],
                'required' => false,
            ])
            ->add('sortBy', ChoiceType::class, [
                'label' => 'Sort by',
                'choices' => [
                    'Date added descending' => 'us.added_at|desc',
                    'Date added ascending' => 'us.added_at|asc',
                    'First air date descending' => 's.first_air_date|desc',
                    'First air date ascending' => 's.first_air_date|asc',
                    'Name descending' => 'display_name|desc',
                    'Name ascending' => 'display_name|asc',
                    'Original name descending' => 's.original_name|desc',
                    'Original name ascending' => 's.original_name|asc',
                ],
                'required' => true,
            ])
            ->add('page', HiddenType::class, [
                'data' => 1,
                'empty_data' => '1',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SeriesAdvancedDbSearchDTO::class,
            'countries' => [],
        ]);

        $resolver->setAllowedTypes('countries', 'array');
    }
}
