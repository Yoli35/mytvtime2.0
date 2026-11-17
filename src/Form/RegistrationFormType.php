<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'attr' => [
                    'placeholder' => 'Choose a username',
                    'autofocus' => true,
                    ]
            ])
            ->add('email', EmailType::class, [
                'attr' => ['placeholder' => 'Email address']
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'I agree to the terms and conditions',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'You should agree to our terms.'),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'attr' => ['placeholder' => 'Password'],
                'constraints' => [
                    new NotBlank(message: 'Please enter a password'),
                    new Length(
                        min: 12,
                        max: 4096,
                        minMessage: 'Your password should be at least {{ limit }} characters',
                    ),
                    new Regex(
                        pattern: '/^(?=.*[A-Za-z])(?=.*\\d).+$/',
                        message: 'Your password must contain at least one letter and one digit',
                    ),
                ],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'mapped' => false,
                'attr' => ['placeholder' => 'Re-type your password'],
                // The match with plainPassword is checked in RegistrationController
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
                'expanded' => false,
                'help' => 'Your preferred language for series descriptions and reviews. You can change this later.',
                'label' => 'Preferred language',
                'preferred_choices' => ['fr', 'de', 'en', 'es'],
            ])
            ->add('timezone', TimezoneType::class, [
                'attr' => ['class' => 'form-select w100'],
                'help' => 'Your timezone will be used to determine the correct time for TV shows and movies.',
                'label' => 'Timezone',
                'preferred_choices' => ['Europe/Paris', 'Europe/Berlin', 'Europe/London', 'Europe/Madrid', 'America/New_York'],
                'required' => false,
            ])
            // Anti-bot: honeypot field, hidden from humans (must stay empty)
            ->add('website', TextType::class, [
                'mapped' => false,
                'required' => false,
            ])
            // Anti-bot: signed timestamp of the form display
            ->add('startedAt', HiddenType::class, [
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
