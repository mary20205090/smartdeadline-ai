<?php

namespace App\Controller;

use App\Entity\Assignment;
use App\Entity\User;
use App\Form\AssignmentType;
use App\Repository\AssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/assignment')]
#[IsGranted('ROLE_USER')]
final class AssignmentController extends AbstractController
{
    #[Route(name: 'app_assignment_index', methods: ['GET'])]
    public function index(AssignmentRepository $assignmentRepository): Response
    {
        $user = $this->getCurrentUser();

        $assignments = $assignmentRepository->createQueryBuilder('assignment')
            ->innerJoin('assignment.course', 'course')
            ->andWhere('course.user = :user')
            ->setParameter('user', $user)
            ->orderBy('assignment.deadline', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('assignment/index.html.twig', [
            'assignments' => $assignments,
        ]);
    }

    #[Route('/new', name: 'app_assignment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $assignment = new Assignment();

        $form = $this->createForm(AssignmentType::class, $assignment, [
            'user' => $this->getCurrentUser(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessToAssignmentOwnerOnly($assignment);

            $entityManager->persist($assignment);
            $entityManager->flush();

            return $this->redirectToRoute('app_assignment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('assignment/new.html.twig', [
            'assignment' => $assignment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_assignment_show', methods: ['GET'])]
    public function show(Assignment $assignment): Response
    {
        $this->denyAccessToAssignmentOwnerOnly($assignment);

        return $this->render('assignment/show.html.twig', [
            'assignment' => $assignment,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_assignment_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Assignment $assignment, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessToAssignmentOwnerOnly($assignment);

        $form = $this->createForm(AssignmentType::class, $assignment, [
            'user' => $this->getCurrentUser(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessToAssignmentOwnerOnly($assignment);

            $entityManager->flush();

            return $this->redirectToRoute('app_assignment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('assignment/edit.html.twig', [
            'assignment' => $assignment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_assignment_delete', methods: ['POST'])]
    public function delete(Request $request, Assignment $assignment, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessToAssignmentOwnerOnly($assignment);

        if ($this->isCsrfTokenValid('delete'.$assignment->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($assignment);
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

        if ($assignment->getCourse()?->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You cannot access this assignment.');
        }
    }
}