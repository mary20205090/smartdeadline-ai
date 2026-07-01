<?php

namespace App\Service;

use App\Entity\Assignment;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class DeadlineNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function generateForUser(User $user): void
    {
        $now = new \DateTimeImmutable();
        $dueSoonLimit = $now->modify('+3 days');

        foreach ($user->getCourses() as $course) {
            foreach ($course->getAssignments() as $assignment) {
                if ($assignment->getStatus() === 'completed') {
                    continue;
                }

                if ($assignment->getDeadline() < $now) {
                    $this->createNotificationIfMissing(
                        user: $user,
                        assignment: $assignment,
                        title: 'Assignment overdue',
                        message: sprintf(
                            '%s is overdue. The deadline was %s.',
                            $assignment->getTitle(),
                            $assignment->getDeadline()->format('d M Y, H:i')
                        )
                    );

                    continue;
                }

                if ($assignment->getDeadline() <= $dueSoonLimit) {
                    $this->createNotificationIfMissing(
                        user: $user,
                        assignment: $assignment,
                        title: 'Assignment due soon',
                        message: sprintf(
                            '%s is due soon on %s.',
                            $assignment->getTitle(),
                            $assignment->getDeadline()->format('d M Y, H:i')
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
        $notification->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($notification);
    }
}