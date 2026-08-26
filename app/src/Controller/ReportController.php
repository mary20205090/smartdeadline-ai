<?php

namespace App\Controller;

use App\Entity\Assignment;
use App\Entity\Prediction;
use App\Entity\User;
use App\Repository\ActivityLogRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reports')]
#[IsGranted('ROLE_USER')]
final class ReportController extends AbstractController
{
    #[Route('', name: 'app_report_index', methods: ['GET'])]
    public function index(ActivityLogRepository $activityLogRepository): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $courses = [];
        $assignments = [];

        foreach ($user->getCourses() as $course) {
            if ($course->isDeleted()) {
                continue;
            }

            $courses[] = $course;

            foreach ($course->getAssignments() as $assignment) {
                if ($assignment->isDeleted()) {
                    continue;
                }

                $assignments[] = $assignment;
            }
        }

        $now = new DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi'));
        $dueSoonLimit = $now->modify('+3 days');
        $riskCounts = ['high' => 0, 'medium' => 0, 'low' => 0, 'unknown' => 0];

        $statusCounts = [
            'pending' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'overdue' => 0,
            'due_soon' => 0,
        ];

        foreach ($assignments as $assignment) {
            $status = $assignment->getStatus() ?? 'pending';

            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }

            $deadline = $this->getLocalDeadline($assignment);

            if ($assignment->getStatus() !== 'completed' && $deadline < $now) {
                $statusCounts['overdue']++;
            }

            if (
                $assignment->getStatus() !== 'completed'
                && $deadline >= $now
                && $deadline <= $dueSoonLimit
            ) {
                $statusCounts['due_soon']++;
            }

            $latestPrediction = $this->getLatestPrediction($assignment);
            $riskLevel = $latestPrediction?->getRiskLevel() ?? 'unknown';
            $riskCounts[$riskLevel] = ($riskCounts[$riskLevel] ?? 0) + 1;
        }

        $recentActivity = $activityLogRepository->createQueryBuilder('activityLog')
            ->join('activityLog.assignment', 'assignment')
            ->join('assignment.course', 'course')
            ->andWhere('course.user = :user')
            ->setParameter('user', $user)
            ->orderBy('activityLog.createdAt', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        return $this->render('reports/index.html.twig', [
            'totalCourses' => count($courses),
            'totalAssignments' => count($assignments),
            'statusCounts' => $statusCounts,
            'riskCounts' => $riskCounts,
            'recentActivity' => $recentActivity,
        ]);
    }

    private function getLatestPrediction(Assignment $assignment): ?Prediction
    {
        $latestPrediction = null;

        foreach ($assignment->getPredictions() as $prediction) {
            if (
                $latestPrediction === null
                || $prediction->getCreatedAt() > $latestPrediction->getCreatedAt()
            ) {
                $latestPrediction = $prediction;
            }
        }

        return $latestPrediction;
    }

    private function getLocalDeadline(Assignment $assignment): DateTimeImmutable
    {
        $deadline = $assignment->getDeadline();

        return new DateTimeImmutable(
            $deadline->format('Y-m-d H:i:s'),
            new \DateTimeZone('Africa/Nairobi')
        );
    }
}
