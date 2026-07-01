<?php

namespace App\Controller;

use App\Entity\User;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $courses = $user->getCourses();
        $assignments = [];

        foreach ($courses as $course) {
            foreach ($course->getAssignments() as $assignment) {
                $assignments[] = $assignment;
            }
        }

        $now = new DateTimeImmutable();

        $pendingAssignments = array_filter($assignments, function ($assignment) {
            return $assignment->getStatus() === 'pending';
        });

        $inProgressAssignments = array_filter($assignments, function ($assignment) {
            return $assignment->getStatus() === 'in_progress';
        });

        $completedAssignments = array_filter($assignments, function ($assignment) {
            return $assignment->getStatus() === 'completed';
        });

        $overdueAssignments = array_filter($assignments, function ($assignment) use ($now) {
            return $assignment->getDeadline() < $now && $assignment->getStatus() !== 'completed';
        });

        $upcomingAssignments = array_filter($assignments, function ($assignment) use ($now) {
            return $assignment->getDeadline() >= $now && $assignment->getStatus() !== 'completed';
        });

        usort($upcomingAssignments, function ($a, $b) {
            return $a->getDeadline() <=> $b->getDeadline();
        });

        return $this->render('dashboard/index.html.twig', [
            'totalCourses' => count($courses),
            'totalAssignments' => count($assignments),
            'pendingCount' => count($pendingAssignments),
            'inProgressCount' => count($inProgressAssignments),
            'completedCount' => count($completedAssignments),
            'overdueCount' => count($overdueAssignments),
            'upcomingAssignments' => array_slice($upcomingAssignments, 0, 5),
        ]);
    }
}