<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\DeadlineNotificationService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(
        DeadlineNotificationService $deadlineNotificationService,
        NotificationRepository $notificationRepository
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        // Generate in-app notifications for due soon and overdue assignments.
        $deadlineNotificationService->generateForUser($user);

        $courses = $user->getCourses();
        $assignments = [];

        foreach ($courses as $course) {
            foreach ($course->getAssignments() as $assignment) {
                $assignments[] = $assignment;
            }
        }

        $now = new DateTimeImmutable();
        $dueSoonLimit = $now->modify('+3 days');

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

        $dueSoonAssignments = array_filter($assignments, function ($assignment) use ($now, $dueSoonLimit) {
            return $assignment->getDeadline() >= $now
                && $assignment->getDeadline() <= $dueSoonLimit
                && $assignment->getStatus() !== 'completed';
        });

        $upcomingAssignments = array_filter($assignments, function ($assignment) use ($now) {
            return $assignment->getDeadline() >= $now && $assignment->getStatus() !== 'completed';
        });

        usort($upcomingAssignments, function ($a, $b) {
            return $a->getDeadline() <=> $b->getDeadline();
        });

        usort($dueSoonAssignments, function ($a, $b) {
            return $a->getDeadline() <=> $b->getDeadline();
        });

        usort($overdueAssignments, function ($a, $b) {
            return $a->getDeadline() <=> $b->getDeadline();
        });

        $recentNotifications = $notificationRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            5
        );

        $unreadNotificationCount = $notificationRepository->count([
            'user' => $user,
            'status' => 'unread',
        ]);

        return $this->render('dashboard/index.html.twig', [
            'totalCourses' => count($courses),
            'totalAssignments' => count($assignments),
            'pendingCount' => count($pendingAssignments),
            'inProgressCount' => count($inProgressAssignments),
            'completedCount' => count($completedAssignments),
            'overdueCount' => count($overdueAssignments),
            'dueSoonCount' => count($dueSoonAssignments),
            'upcomingAssignments' => array_slice($upcomingAssignments, 0, 5),
            'dueSoonAssignments' => array_slice($dueSoonAssignments, 0, 5),
            'overdueAssignments' => array_slice($overdueAssignments, 0, 5),
            'recentNotifications' => $recentNotifications,
            'unreadNotificationCount' => $unreadNotificationCount,
        ]);
    }
}