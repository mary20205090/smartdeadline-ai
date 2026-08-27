<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Full Name',
                'attr' => [
                    'placeholder' => 'Example: Mary Mutua',
                    'autocomplete' => 'name',
                    'minlength' => 3,
                    'maxlength' => 120,
                    'pattern' => '^[A-Za-z]+(?:\s+[A-Za-z]+)*$',
                    'title' => 'Full name should contain letters and spaces only.',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Full name is required.',
                    ]),
                    new Length([
                        'min' => 3,
                        'max' => 120,
                        'minMessage' => 'Full name should be at least {{ limit }} characters.',
                        'maxMessage' => 'Full name cannot be longer than {{ limit }} characters.',
                    ]),
                    new Regex([
                        'pattern' => '/^[A-Za-z]+(?:\s+[A-Za-z]+)*$/',
                        'message' => 'Full name should contain letters and spaces only.',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email address',
                'attr' => [
                    'placeholder' => 'Example: mary@example.com',
                    'autocomplete' => 'email',
                    'inputmode' => 'email',
                    'maxlength' => 180,
                    'pattern' => '^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$',
                    'title' => 'Enter a valid email address such as mary@example.com.',
                    'data-valid-email' => 'true',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Email address is required.',
                    ]),
                    new Email([
                        'message' => 'Enter a valid email address such as mary@example.com.',
                    ]),
                    new Length([
                        'max' => 180,
                        'maxMessage' => 'Email address cannot be longer than {{ limit }} characters.',
                    ]),
                    new Regex([
                        'pattern' => '/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/',
                        'message' => 'Enter a valid email address such as mary@example.com.',
                    ]),
                    new Regex([
                        'pattern' => '/\.\./',
                        'match' => false,
                        'message' => 'Email address cannot contain consecutive dots.',
                    ]),
                ],
            ])

            ->add('emailNotificationsEnabled', CheckboxType::class, [
                'required' => false,
            ])

            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'You should agree to our terms.',
                    ]),
                ],
            ])

            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => 'At least 6 characters',
                    'minlength' => 6,
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password.',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters.',
                        'max' => 4096,
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
