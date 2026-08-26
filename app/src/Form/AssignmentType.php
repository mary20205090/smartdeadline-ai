<?php

namespace App\Form;

use App\Entity\Assignment;
use App\Entity\Course;
use App\Repository\CourseRepository;
use DateTimeImmutable;
use DateTimeZone;
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
        $minimumDeadline = $options['deadline_min'];

        $builder
            ->add('title')
            ->add('description', TextareaType::class, [
                'required' => false,
                'empty_data' => null,
            ])
            ->add('deadline', null, [
                'widget' => 'single_text',
                'required' => true,
                'attr' => [
                    ...($minimumDeadline !== null ? ['min' => $minimumDeadline] : []),
                    'data-deadline-guard' => 'true',
                ],
            ])
            ->add('priority', ChoiceType::class, [
                'required' => true,
                'placeholder' => 'Select priority',
                'placeholder_attr' => [
                    'disabled' => 'disabled',
                ],
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
                'choice_filter' => static function (?Course $course): bool {
                    if ($course === null) {
                        return true;
                    }

                    return preg_match('/[A-Za-z]/', $course->getName() ?? '') === 1;
                },
                'placeholder' => 'Select course',
                'placeholder_attr' => [
                    'disabled' => 'disabled',
                ],
                'required' => true,
                'query_builder' => function (CourseRepository $courseRepository) use ($user) {
                    return $courseRepository->createQueryBuilder('course')
                        ->andWhere('course.user = :user')
                        ->andWhere('course.deletedAt IS NULL')
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
            'deadline_min' => (new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d\TH:i'),
        ]);
    }
}
