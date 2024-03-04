<?php

namespace App\Form;

use App\DTO\SeriesAdvancedSearchDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SeriesAdvancedSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('language', HiddenType::class)
            ->add('timezone', HiddenType::class)
            ->add('watchRegion', HiddenType::class)
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
            ->add('withOriginCountry', CountryType::class, [
                'label' => 'Origin country',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('withOriginalLanguage', LanguageType::class, [
                'label' => 'Original language',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('withWatchMonetizationTypes', ChoiceType::class, [
                'label' => 'Monetization type',
                'required' => false,
                'empty_data' => '',
                'choices' => [
                    'Flatrate' => 'flatrate',
                    'Free' => 'free',
                    'Ads' => 'ads',
                    'Rent' => 'rent',
                    'Buy' => 'buy',
                ],
            ])
            ->add('withWatchProviders', ChoiceType::class, [
                'label' => 'Watch providers',
                'required' => false,
                'empty_data' => '',
                'choices' => $options['data']->getWatchProviders(),
                'choice_translation_domain' => false,
            ])
            ->add('withRuntimeGTE', NumberType::class, [
                'label' => 'Runtime greater than',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('withRuntimeLTE', NumberType::class, [
                'label' => 'Runtime less than',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('withKeywords', ChoiceType::class, [
                'label' => 'Keywords',
                'required' => false,
                'empty_data' => '',
                'choices' => $options['data']->getKeywords(),
                'choice_translation_domain' => false,
            ])
            ->add('withStatus', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Returning Series' => '0',
                    'Planned' => '1',
                    'In Production' => '2',
                    'Ended' => '3',
                    'Canceled' => '4',
                    'Pilot' => '5',
                ],
                'required' => false,
            ])
            ->add('withType', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Documentary' => '0',
                    'News' => '1',
                    'Miniseries' => '2',
                    'Reality' => '3',
                    'Scripted' => '4',
                    'Talk Show' => '5',
                    'Video' => '6',
                ],
                'choice_attr' => [
                    'Documentary' => ['data-title' => 'e.g. wildlife documentary'],
                    'News' => ['data-title' => 'e.g. news, political programmes'],
                    'Miniseries' => ['data-title' => 'miniseries'],
                    'Reality' => ['data-title' => 'reality'],
                    'Scripted' => ['data-title' => 'scripted'],
                    'Talk Show' => ['data-title' => 'talk-show'],
                    'Video' => ['data-title' => 'video'],
                ],
                'required' => false,
            ])
            ->add('sortBy', ChoiceType::class, [
                'label' => 'Sort by',
                'choices' => [
                    'Popularity descending' => 'popularity.desc',
                    'Popularity ascending' => 'popularity.asc',
                    'Vote average descending' => 'vote_average.desc',
                    'Vote average ascending' => 'vote_average.asc',
                    'First air date descending' => 'first_air_date.desc',
                    'First air date ascending' => 'first_air_date.asc',
                    'Original name descending' => 'original_name.desc',
                    'Original name ascending' => 'original_name.asc',
                    'Name descending' => 'name.desc',
                    'Name ascending' => 'name.asc',
                    'Vote count descending' => 'vote_count.desc',
                    'Vote count ascending' => 'vote_count.asc',
                ],
                'required' => true,
            ])
            ->add('page', HiddenType::class, [
                'data' => 1,
                'empty_data' => '1',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SeriesAdvancedSearchDTO::class,
        ]);
    }
}
