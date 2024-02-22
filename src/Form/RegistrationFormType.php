<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'attr' => ['placeholder' => 'Choose a username']
            ])
            ->add('email', EmailType::class, [
                'attr' => ['placeholder' => 'Email address']
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'I agree to the terms and conditions',
                'mapped' => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'You should agree to our terms.',
                    ]),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'attr' => ['placeholder' => 'Password'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        'max' => 4096,
                    ]),
                ],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'mapped' => false,
                'attr' => ['placeholder' => 'Re-type your password'],
                'constraints' => [
//                    new IdenticalTo([
//                        'propertyPath' => 'plainPassword',
//                    ])
                ],
                'label' => 'Confirm Password',
            ])
            ->add('country', CountryType::class, [
                'attr' => ['class' => 'form-select w100'],
                'help' => 'Your country will be used to determine the correct time for TV shows and movies.',
                'label' => 'Country',
                'preferred_choices' => ['FR', 'DE', 'GB', 'ES', 'US'],
                'required' => false,
            ])
            ->add('preferredLanguage', LanguageType::class, [
                'attr' => ['class' => 'form-select w100'],
                'preferred_choices' => ['fr', 'de', 'en', 'es'],
                'expanded' => false,
                'help' => 'Your preferred language for series descriptions and reviews. You can change this later.',
                'label' => 'Preferred language',
            ])
            ->add('timezone', TimezoneType::class, [
                'attr' => ['class' => 'form-select w100'],
                'help' => 'Your timezone will be used to determine the correct time for TV shows and movies.',
                'label' => 'Timezone',
                'preferred_choices' => ['Europe/Paris', 'Europe/Berlin', 'Europe/London', 'Europe/Madrid', 'America/New_York'],
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
