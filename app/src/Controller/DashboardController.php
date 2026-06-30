<?php

namespace App\Controller;

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

        $courses = method_exists($user, 'getCourses') ? $user->getCourses() : [];
        $assignments = [];

        foreach ($courses as $course) {
            foreach ($course->getAssignments() as $assignment) {
                $assignments[] = $assignment;
            }
        }

        $now = new DateTimeImmutable();

        $upcomingAssignments = array_filter($assignments, function ($assignment) use ($now) {
            return $assignment->getDeadline() >= $now && $assignment->getStatus() !== 'completed';
        });

        usort($upcomingAssignments, function ($a, $b) {
            return $a->getDeadline() <=> $b->getDeadline();
        });

        $overdueAssignments = array_filter($assignments, function ($assignment) use ($now) {
            return $assignment->getDeadline() < $now && $assignment->getStatus() !== 'completed';
        });

        $completedAssignments = array_filter($assignments, function ($assignment) {
            return $assignment->getStatus() === 'completed';
        });

        return $this->render('dashboard/index.html.twig', [
            'totalCourses' => count($courses),
            'totalAssignments' => count($assignments),
            'upcomingAssignments' => array_slice($upcomingAssignments, 0, 5),
            'overdueCount' => count($overdueAssignments),
            'completedCount' => count($completedAssignments),
        ]);
    }
}