<?php

namespace App\Form;

use App\Entity\Series;
use App\Entity\SeriesVideo;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SeriesVideoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'attr' => ['placeholder' => 'Title'],
                'label' => 'Title',
            ])
            ->add('link', TextType::class, [
                'attr' => ['placeholder' => 'Video Identifier (11 characters - i.e. 5KHgFEiPD4g)'],
                'label' => 'Video Identifier',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SeriesVideo::class,
        ]);
    }
}
