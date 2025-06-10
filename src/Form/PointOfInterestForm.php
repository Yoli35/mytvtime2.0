<?php

namespace App\Form;

use App\Entity\PointOfInterest;
use App\Entity\PointOfInterestImage;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class PointOfInterestForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => [
                    'placeholder' => 'Enter the name of the point of interest',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'The address cannot be longer than 255 characters',
                    ]),
                ],
                'label' => 'Name',
                'required' => true,
            ])
            ->add('address', TextType::class, [
                'attr' => [
                    'placeholder' => 'Enter the address of the point of interest',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'The address cannot be longer than 255 characters',
                    ]),
                ],
                'label' => 'Address',
                'required' => true,
            ])
            ->add('city', TextType::class, [
                'attr' => [
                    'placeholder' => 'Enter the city of the point of interest',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a city',
                    ]),
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'The city cannot be longer than 255 characters',
                    ]),
                ],
                'label' => 'City',
                'required' => true,
            ])
            ->add('originCountry', CountryType::class, [
                'label' => 'Country',
                'required' => true,
            ])
            ->add('description', TextType::class)
            ->add('latitude', NumberType::class, [
                'attr' => [
                    'placeholder' => 'Enter the latitude of the point of interest',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a latitude',
                    ]),
                ],
                'label' => 'Latitude',
                'required' => true,
            ])
            ->add('longitude', NumberType::class, [
                'attr' => [
                    'placeholder' => 'Enter the longitude of the point of interest',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a longitude',
                    ]),
                ],
                'label' => 'Longitude',
                'required' => true,
            ])
            ->add('createdAt', HiddenType::class, [
            ])
            ->add('updatedAt', HiddenType::class, [
            ])
            ->add('still', EntityType::class, [
                'class' => PointOfInterestImage::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PointOfInterest::class,
        ]);
    }
}
