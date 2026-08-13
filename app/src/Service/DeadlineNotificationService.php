<?php

namespace App\Service;

use App\Entity\Assignment;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class DeadlineNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AssignmentRiskPredictionService $assignmentRiskPredictionService
    ) {
    }

    public function generateForUser(User $user): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi'));
        $dueSoonLimit = $now->modify('+3 days');

        foreach ($user->getCourses() as $course) {
            if ($course->isDeleted()) {
                continue;
            }

            foreach ($course->getAssignments() as $assignment) {
                if ($assignment->isDeleted() || $assignment->getStatus() === 'completed') {
                    continue;
                }

                $deadline = $this->getLocalDeadline($assignment);

                if ($deadline < $now) {
                    $this->createNotificationIfMissing(
                        user: $user,
                        assignment: $assignment,
                        title: 'Assignment overdue',
                        message: sprintf(
                            '%s is overdue. The deadline was %s.',
                            $assignment->getTitle(),
                            $deadline->format('d M Y, H:i')
                        )
                    );
                } elseif ($deadline <= $dueSoonLimit) {
                    $this->createNotificationIfMissing(
                        user: $user,
                        assignment: $assignment,
                        title: 'Assignment due soon',
                        message: sprintf(
                            '%s is due soon on %s.',
                            $assignment->getTitle(),
                            $deadline->format('d M Y, H:i')
                        )
                    );
                }

                $prediction = $this->assignmentRiskPredictionService->predictAndSave($assignment);

                if ($prediction->getRiskLevel() === 'high') {
                    $this->createNotificationIfMissing(
                        user: $user,
                        assignment: $assignment,
                        title: 'AI Risk Alert',
                        message: sprintf(
                            '%s is predicted as high risk of missing the deadline. Please review it and take action.',
                            $assignment->getTitle()
                        )
                    );
                }
            }
        }

        $this->entityManager->flush();
    }

    private function createNotificationIfMissing(
        User $user,
        Assignment $assignment,
        string $title,
        string $message
    ): void {
        $existingNotification = $this->entityManager
            ->getRepository(Notification::class)
            ->findOneBy([
                'user' => $user,
                'assignment' => $assignment,
                'title' => $title,
                'channel' => 'in_app',
            ]);

        if ($existingNotification !== null) {
            return;
        }

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setAssignment($assignment);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setChannel('in_app');
        $notification->setStatus('unread');
        $notification->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')));

        $this->entityManager->persist($notification);
    }

    private function getLocalDeadline(Assignment $assignment): \DateTimeImmutable
    {
        $deadline = $assignment->getDeadline();

        return new \DateTimeImmutable(
            $deadline->format('Y-m-d H:i:s'),
            new \DateTimeZone('Africa/Nairobi')
        );
    }
}
