<?php

namespace App\Controller;

use App\Service\AssignmentRiskPredictionService;
use App\Service\ActivityLogService;
use App\Entity\Assignment;
use App\Entity\Course;
use App\Entity\User;
use App\Form\AssignmentType;
use App\Repository\AssignmentRepository;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/assignment')]
#[IsGranted('ROLE_USER')]
final class AssignmentController extends AbstractController
{
    #[Route(name: 'app_assignment_index', methods: ['GET'])]
    public function index(
        AssignmentRepository $assignmentRepository,
        AssignmentRiskPredictionService $assignmentRiskPredictionService,
        EntityManagerInterface $entityManager
    ): Response
    {
        $user = $this->getCurrentUser();

        $assignments = $assignmentRepository->createQueryBuilder('assignment')
            ->innerJoin('assignment.course', 'course')
            ->andWhere('course.user = :user')
            ->andWhere('assignment.deletedAt IS NULL')
            ->andWhere('course.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('assignment.deadline', 'ASC')
            ->getQuery()
            ->getResult();

        
        $predictionsByAssignmentId = [];

        foreach ($assignments as $assignment) {
            $prediction = $assignmentRiskPredictionService->predictAndSave($assignment);

            if ($assignment->getId() !== null) {
                $predictionsByAssignmentId[$assignment->getId()] = $prediction;
            }
        }

        $entityManager->flush();

        return $this->render('assignment/index.html.twig', [
            'assignments' => $assignments,
            'predictionsByAssignmentId' => $predictionsByAssignmentId,
        ]);

    }

    #[Route('/new', name: 'app_assignment_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        CourseRepository $courseRepository,
        ActivityLogService $activityLogService,
        AssignmentRiskPredictionService $assignmentRiskPredictionService
    ): Response
    {
        $assignment = new Assignment();

        $form = $this->createForm(AssignmentType::class, $assignment, [
            'user' => $this->getCurrentUser(),
            'selected_course' => null,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedCourse = $this->getSubmittedCourse($request, $form, $courseRepository);

            if (!$selectedCourse instanceof Course) {
                $form->get('course')->addError(new FormError('Select one of your active courses.'));

                return $this->render('assignment/new.html.twig', [
                    'assignment' => $assignment,
                    'form' => $form,
                ]);
            }

            $this->applyAssignmentFormValues($assignment, $form, $selectedCourse);

            if (!$this->hasCurrentOrFutureDeadline($assignment)) {
                $form->get('deadline')->addError(new FormError('Choose a current or future deadline.'));

                return $this->render('assignment/new.html.twig', [
                    'assignment' => $assignment,
                    'form' => $form,
                ]);
            }

            $this->denyAccessToAssignmentOwnerOnly($assignment);

            $entityManager->persist($assignment);
            $activityLogService->logAssignmentEvent($assignment, 'assignment_created');
            $assignmentRiskPredictionService->predictAndSave($assignment, true);

            $entityManager->flush();

            return $this->redirectToRoute('app_assignment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('assignment/new.html.twig', [
            'assignment' => $assignment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_assignment_show', methods: ['GET'])]
    public function show(
        Assignment $assignment,
        AssignmentRiskPredictionService $assignmentRiskPredictionService
    ): Response
    {
        $this->denyAccessToAssignmentOwnerOnly($assignment);

        $prediction = $assignmentRiskPredictionService->predictAndSave($assignment);

        return $this->render('assignment/show.html.twig', [
            'assignment' => $assignment,
            'prediction' => $prediction,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_assignment_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Assignment $assignment,
        EntityManagerInterface $entityManager,
        CourseRepository $courseRepository,
        ActivityLogService $activityLogService,
        AssignmentRiskPredictionService $assignmentRiskPredictionService
    ): Response
    {
        $this->denyAccessToAssignmentOwnerOnly($assignment);

        $originalDeadline = $assignment->getDeadline()?->format('Y-m-d H:i:s');
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi'));

        $form = $this->createForm(AssignmentType::class, $assignment, [
            'user' => $this->getCurrentUser(),
            'selected_course' => $assignment->getCourse(),
            'deadline_min' => $assignment->getDeadline() >= $now
                ? $now->format('Y-m-d\TH:i')
                : null,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedCourse = $this->getSubmittedCourse($request, $form, $courseRepository);

            if (!$selectedCourse instanceof Course) {
                $form->get('course')->addError(new FormError('Select one of your active courses.'));

                return $this->render('assignment/edit.html.twig', [
                    'assignment' => $assignment,
                    'form' => $form,
                ]);
            }

            $this->applyAssignmentFormValues($assignment, $form, $selectedCourse);

            $deadlineChanged = $assignment->getDeadline()?->format('Y-m-d H:i:s') !== $originalDeadline;

            if ($deadlineChanged && !$this->hasCurrentOrFutureDeadline($assignment)) {
                $form->get('deadline')->addError(new FormError('Choose a current or future deadline.'));

                return $this->render('assignment/edit.html.twig', [
                    'assignment' => $assignment,
                    'form' => $form,
                ]);
            }

            $this->denyAccessToAssignmentOwnerOnly($assignment);

            $activityLogService->logAssignmentEvent($assignment, 'assignment_updated');
            $assignmentRiskPredictionService->predictAndSave($assignment, true);

            $entityManager->flush();

            return $this->redirectToRoute('app_assignment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('assignment/edit.html.twig', [
            'assignment' => $assignment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/start', name: 'app_assignment_start', methods: ['POST'])]
    public function start(
        Request $request,
        Assignment $assignment,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService,
        AssignmentRiskPredictionService $assignmentRiskPredictionService
    ): Response
    {
        $this->denyAccessToAssignmentOwnerOnly($assignment);

        if ($this->isCsrfTokenValid('start'.$assignment->getId(), $request->getPayload()->getString('_token'))) {
            if ($assignment->getStatus() === 'pending') {
                $assignment->setStatus('in_progress');
                $activityLogService->logAssignmentEvent($assignment, 'assignment_started');
                $assignmentRiskPredictionService->predictAndSave($assignment, true);

                $entityManager->flush();
            }
        }

        return $this->redirectToRoute('app_assignment_index', [], Response::HTTP_SEE_OTHER);
    }
    
    #[Route('/{id}/complete', name: 'app_assignment_complete', methods: ['POST'])]
    public function complete(
        Request $request,
        Assignment $assignment,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService,
        AssignmentRiskPredictionService $assignmentRiskPredictionService
    ): Response
    {
        $this->denyAccessToAssignmentOwnerOnly($assignment);

        if ($this->isCsrfTokenValid('complete'.$assignment->getId(), $request->getPayload()->getString('_token'))) {
            if ($assignment->getStatus() !== 'completed') {
                $assignment->setStatus('completed');
                $assignment->setCompletedAt(new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')));

                $activityLogService->logAssignmentEvent($assignment, 'assignment_completed');
                $assignmentRiskPredictionService->predictAndSave($assignment, true);

                $entityManager->flush();
            }

        }

        return $this->redirectToRoute('app_assignment_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_assignment_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Assignment $assignment,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService
    ): Response
    {
        $this->denyAccessToAssignmentOwnerOnly($assignment);

        if ($this->isCsrfTokenValid('delete'.$assignment->getId(), $request->getPayload()->getString('_token'))) {
            $assignment->setDeletedAt(new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')));
            $activityLogService->logAssignmentEvent($assignment, 'assignment_deleted');
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_assignment_index', [], Response::HTTP_SEE_OTHER);
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        return $user;
    }

    private function denyAccessToAssignmentOwnerOnly(Assignment $assignment): void
    {
        $user = $this->getCurrentUser();

        if (
            $assignment->isDeleted()
            || $assignment->getCourse()?->isDeleted()
            || $assignment->getCourse()?->getUser()?->getId() !== $user->getId()
        ) {
            throw $this->createAccessDeniedException('You cannot access this assignment.');
        }
    }

    private function getSubmittedCourse(Request $request, FormInterface $form, CourseRepository $courseRepository): ?Course
    {
        $submittedData = $request->request->all($form->getName());
        $submittedCourseId = $submittedData['course'] ?? null;

        if ($submittedCourseId === null || $submittedCourseId === '') {
            $course = $form->get('course')->getData();

            return $course instanceof Course && $this->canUseCourse($course) ? $course : null;
        }

        $course = $courseRepository->createQueryBuilder('course')
            ->andWhere('course.id = :courseId')
            ->andWhere('course.user = :user')
            ->andWhere('course.deletedAt IS NULL')
            ->setParameter('courseId', $submittedCourseId)
            ->setParameter('user', $this->getCurrentUser())
            ->getQuery()
            ->getOneOrNullResult();

        return $course instanceof Course && $this->canUseCourse($course) ? $course : null;
    }

    private function canUseCourse(Course $course): bool
    {
        return !$course->isDeleted()
            && $course->getUser()?->getId() === $this->getCurrentUser()->getId();
    }

    private function applyAssignmentFormValues(Assignment $assignment, FormInterface $form, Course $course): void
    {
        $assignment
            ->setTitle(trim((string) $form->get('title')->getData()))
            ->setDescription($form->get('description')->getData())
            ->setPriority($form->get('priority')->getData())
            ->setDeadline($form->get('deadline')->getData())
            ->setCourse($course);
    }

    private function hasCurrentOrFutureDeadline(Assignment $assignment): bool
    {
        $deadline = $assignment->getDeadline();

        if ($deadline === null) {
            return false;
        }

        $localDeadline = new \DateTimeImmutable(
            $deadline->format('Y-m-d H:i:s'),
            new \DateTimeZone('Africa/Nairobi')
        );
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')))->modify('-1 minute');

        return $localDeadline >= $now;
    }
}
