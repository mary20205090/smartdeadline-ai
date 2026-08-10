<?php

namespace App\Service;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

class DeadlineEmailReminderService
{
    public const EMAILABLE_TITLES = [
        'Assignment due soon',
        'Assignment overdue',
        'AI Risk Alert',
    ];

    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TransportInterface $mailerTransport,
        private readonly string $mailerFrom
    ) {
    }

    /**
     * @return array{planned:int, sent:int, failed:int, skipped:int, rows:list<array<string, string>>}
     */
    public function sendPendingReminders(bool $dryRun = false, int $limit = 50, ?string $recipient = null): array
    {
        $notifications = $this->notificationRepository->findPendingEmailReminders(
            self::EMAILABLE_TITLES,
            $limit,
            self::MAX_ATTEMPTS,
            $recipient
        );

        $result = [
            'planned' => count($notifications),
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'rows' => [],
        ];

        foreach ($notifications as $notification) {
            $user = $notification->getUser();

            if ($user === null || !$user->canReceiveEmailNotifications()) {
                $result['skipped']++;
                continue;
            }

            $row = [
                'email' => (string) $user->getEmail(),
                'title' => (string) $notification->getTitle(),
                'assignment' => (string) ($notification->getAssignment()?->getTitle() ?? 'General reminder'),
            ];

            if ($dryRun) {
                $result['rows'][] = $row + ['status' => 'dry-run'];
                continue;
            }

            $notification->incrementEmailAttempts();

            try {
                $this->mailerTransport->send($this->buildEmail($notification));
                $notification
                    ->setEmailSentAt($this->now())
                    ->setEmailFailedAt(null)
                    ->setEmailError(null);

                $result['sent']++;
                $result['rows'][] = $row + ['status' => 'sent'];
            } catch (\Throwable $exception) {
                $notification
                    ->setEmailFailedAt($this->now())
                    ->setEmailError(mb_substr($exception->getMessage(), 0, 2000));

                $result['failed']++;
                $result['rows'][] = $row + ['status' => 'failed'];
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }

    private function buildEmail(Notification $notification): Email
    {
        $assignment = $notification->getAssignment();
        $course = $assignment?->getCourse();
        $user = $notification->getUser();

        $assignmentTitle = $assignment?->getTitle() ?? 'General reminder';
        $courseName = $course?->getName() ?? 'Not assigned';
        $courseCode = $course?->getCode();
        $deadline = $assignment?->getDeadline()?->format('d M Y, H:i') ?? 'Not set';
        $courseDisplay = $courseCode !== null && $courseCode !== ''
            ? sprintf('%s (%s)', $courseName, $courseCode)
            : $courseName;

        $plainText = sprintf(
            "Hello %s,\n\n%s\n\nAssignment: %s\nCourse: %s\nDeadline: %s\n\nPlease open SMARTDEADLINE AI and review this work.\n\nSMARTDEADLINE AI",
            $user?->getFullName() ?: 'Student',
            $notification->getMessage(),
            $assignmentTitle,
            $courseDisplay,
            $deadline
        );

        $html = sprintf(
            '<p>Hello %s,</p><p>%s</p><table cellpadding="6" cellspacing="0" style="border-collapse:collapse"><tr><td><strong>Assignment</strong></td><td>%s</td></tr><tr><td><strong>Course</strong></td><td>%s</td></tr><tr><td><strong>Deadline</strong></td><td>%s</td></tr></table><p>Please open SMARTDEADLINE AI and review this work.</p><p>SMARTDEADLINE AI</p>',
            $this->escape($user?->getFullName() ?: 'Student'),
            $this->escape((string) $notification->getMessage()),
            $this->escape($assignmentTitle),
            $this->escape($courseDisplay),
            $this->escape($deadline)
        );

        return (new Email())
            ->from($this->mailerFrom)
            ->to((string) $user?->getEmail())
            ->subject(sprintf('SMARTDEADLINE AI: %s', $notification->getTitle()))
            ->text($plainText)
            ->html($html);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi'));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
