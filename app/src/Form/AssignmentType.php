<?php

namespace App\Form;

use App\Entity\Assignment;
use App\Entity\Course;
use App\Repository\CourseRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AssignmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'];

        $builder
            ->add('title')
            ->add('description', TextareaType::class, [
                'required' => false,
            ])
            ->add('deadline', null, [
                'widget' => 'single_text',
            ])
            ->add('priority', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'Select priority',
                'choices' => [
                    'Low' => 'low',
                    'Medium' => 'medium',
                    'High' => 'high',
                ],
            ])
            ->add('course', EntityType::class, [
                'class' => Course::class,
                'choice_label' => function (Course $course): string {
                    return $course->getCode()
                        ? $course->getName().' ('.$course->getCode().')'
                        : $course->getName();
                },
                'placeholder' => 'Select course',
                'query_builder' => function (CourseRepository $courseRepository) use ($user) {
                    return $courseRepository->createQueryBuilder('course')
                        ->andWhere('course.user = :user')
                        ->setParameter('user', $user)
                        ->orderBy('course.name', 'ASC');
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Assignment::class,
            'user' => null,
        ]);
    }
}