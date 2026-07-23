<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Assignment;
use Doctrine\ORM\EntityManagerInterface;

class ActivityLogService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function logAssignmentEvent(Assignment $assignment, string $eventType): void
    {
        $activityLog = new ActivityLog();
        $activityLog->setAssignment($assignment);
        $activityLog->setEventType($eventType);
        $activityLog->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')));

        $this->entityManager->persist($activityLog);
    }
}
