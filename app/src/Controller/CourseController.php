<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\User;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/course')]
#[IsGranted('ROLE_USER')]
final class CourseController extends AbstractController
{
    #[Route(name: 'app_course_index', methods: ['GET'])]
    public function index(CourseRepository $courseRepository): Response
    {
        $user = $this->getCurrentUser();

        return $this->render('course/index.html.twig', [
            'courses' => $courseRepository->createQueryBuilder('course')
                ->andWhere('course.user = :user')
                ->andWhere('course.deletedAt IS NULL')
                ->setParameter('user', $user)
                ->orderBy('course.createdAt', 'DESC')
                ->getQuery()
                ->getResult(),
        ]);
    }

    #[Route('/new', name: 'app_course_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $course = new Course();
        $course->setUser($this->getCurrentUser());

        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($course);
            $entityManager->flush();

            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/new.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_course_show', methods: ['GET'])]
    public function show(Course $course): Response
    {
        $this->denyAccessToCourseOwnerOnly($course);

        return $this->render('course/show.html.twig', [
            'course' => $course,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_course_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessToCourseOwnerOnly($course);

        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/edit.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_course_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Course $course,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService
    ): Response {
        $this->denyAccessToCourseOwnerOnly($course);

        if ($this->isCsrfTokenValid('delete'.$course->getId(), $request->getPayload()->getString('_token'))) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi'));
            $course->setDeletedAt($now);

            foreach ($course->getAssignments() as $assignment) {
                if ($assignment->isDeleted()) {
                    continue;
                }

                $assignment->setDeletedAt($now);
                $activityLogService->logAssignmentEvent($assignment, 'assignment_deleted');
            }

            $entityManager->flush();
        }

        return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        return $user;
    }

    private function denyAccessToCourseOwnerOnly(Course $course): void
    {
        $user = $this->getCurrentUser();

        if ($course->isDeleted() || $course->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You cannot access this course.');
        }
    }
}
