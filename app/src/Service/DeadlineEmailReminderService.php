<?php

namespace App\Service;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        private readonly UrlGeneratorInterface $urlGenerator,
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
        $status = $this->formatLabel($assignment?->getStatus());
        $priority = $this->formatLabel($assignment?->getPriority());
        $courseDisplay = $courseCode !== null && $courseCode !== ''
            ? sprintf('%s (%s)', $courseName, $courseCode)
            : $courseName;
        $theme = $this->getAlertTheme($notification);
        $unsubscribeUrl = null;

        if ($user !== null) {
            $unsubscribeUrl = $this->urlGenerator->generate(
                'app_email_unsubscribe',
                ['token' => $user->ensureEmailUnsubscribeToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        $plainText = sprintf(
            "Hello %s,\n\n%s\n\nAssignment: %s\nCourse: %s\nDeadline: %s\nStatus: %s\nPriority: %s\n\nAction: %s\n\nManage email reminders: %s\n\nSMARTDEADLINE AI\nDeadline risk tracker",
            $user?->getFullName() ?: 'Student',
            $notification->getMessage(),
            $assignmentTitle,
            $courseDisplay,
            $deadline,
            $status,
            $priority,
            $theme['action'],
            $unsubscribeUrl ?? 'Sign in to SMARTDEADLINE AI and open Preferences.'
        );

        $studentName = $this->escape($user?->getFullName() ?: 'Student');
        $message = $this->escape((string) $notification->getMessage());
        $escapedTitle = $this->escape($assignmentTitle);
        $escapedCourse = $this->escape($courseDisplay);
        $escapedDeadline = $this->escape($deadline);
        $escapedStatus = $this->escape($status);
        $escapedPriority = $this->escape($priority);
        $escapedAction = $this->escape($theme['action']);
        $escapedAlertTitle = $this->escape((string) $notification->getTitle());
        $escapedUnsubscribeUrl = $unsubscribeUrl !== null ? $this->escape($unsubscribeUrl) : null;
        $unsubscribeHtml = $escapedUnsubscribeUrl !== null
            ? sprintf(
                '<br><a href="%s" style="color:#0f4c81;font-weight:700;text-decoration:none;">Unsubscribe from reminder emails</a>',
                $escapedUnsubscribeUrl
            )
            : '';

        $html = <<<HTML
            <div style="margin:0;padding:0;background:#f3f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
                <div style="max-width:640px;margin:0 auto;padding:28px 16px;">
                    <div style="background:#ffffff;border:1px solid #dbe7f3;border-radius:12px;overflow:hidden;box-shadow:0 14px 34px rgba(15,23,42,0.08);">
                        <div style="background:{$theme['accent']};padding:22px 26px;color:#ffffff;">
                            <div style="display:inline-block;width:42px;height:42px;line-height:42px;text-align:center;border-radius:50%;background:rgba(255,255,255,0.18);font-size:22px;margin-bottom:12px;">{$theme['icon']}</div>
                            <div style="font-size:12px;font-weight:700;letter-spacing:0;text-transform:uppercase;opacity:0.9;">SMARTDEADLINE AI Reminder</div>
                            <h1 style="margin:6px 0 0;font-size:24px;line-height:1.25;font-weight:800;">{$escapedAlertTitle}</h1>
                            <p style="margin:10px 0 0;font-size:15px;line-height:1.6;color:#eaf3ff;">{$theme['intro']}</p>
                        </div>

                        <div style="padding:26px;">
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">Hello {$studentName},</p>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;">{$message}</p>

                            <div style="border:1px solid #dbe7f3;border-radius:10px;overflow:hidden;margin:20px 0;background:#ffffff;">
                                <div style="background:{$theme['soft']};padding:12px 16px;font-size:13px;font-weight:800;color:#0f3f78;">Deadline Details</div>
                                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
                                    <tr>
                                        <td style="padding:14px 16px;border-top:1px solid #e5edf5;font-size:13px;color:#53657d;width:32%;">Assignment</td>
                                        <td style="padding:14px 16px;border-top:1px solid #e5edf5;font-size:14px;font-weight:700;color:#0f172a;">{$escapedTitle}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:14px 16px;border-top:1px solid #e5edf5;font-size:13px;color:#53657d;">Course</td>
                                        <td style="padding:14px 16px;border-top:1px solid #e5edf5;font-size:14px;color:#0f172a;">{$escapedCourse}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:14px 16px;border-top:1px solid #e5edf5;font-size:13px;color:#53657d;">Deadline</td>
                                        <td style="padding:14px 16px;border-top:1px solid #e5edf5;font-size:14px;font-weight:800;color:#0f172a;">{$escapedDeadline}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:14px 16px;border-top:1px solid #e5edf5;font-size:13px;color:#53657d;">Status</td>
                                        <td style="padding:14px 16px;border-top:1px solid #e5edf5;font-size:14px;color:#0f172a;">{$escapedStatus}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:14px 16px;border-top:1px solid #e5edf5;font-size:13px;color:#53657d;">Priority</td>
                                        <td style="padding:14px 16px;border-top:1px solid #e5edf5;font-size:14px;color:#0f172a;">{$escapedPriority}</td>
                                    </tr>
                                </table>
                            </div>

                            <div style="background:#fff8dc;border-left:5px solid #ffd43b;border-radius:8px;padding:14px 16px;margin:20px 0;">
                                <div style="font-size:13px;font-weight:800;color:#7a5200;margin-bottom:4px;">Suggested action</div>
                                <div style="font-size:14px;line-height:1.6;color:#253244;">{$escapedAction}</div>
                            </div>

                            <p style="margin:22px 0 0;font-size:14px;line-height:1.6;color:#53657d;">Open SMARTDEADLINE AI to review the assignment, update progress, or mark it as complete.</p>
                        </div>

                        <div style="padding:16px 26px;background:#f8fbff;border-top:1px solid #dbe7f3;font-size:12px;line-height:1.5;color:#6b7b91;">
                            SMARTDEADLINE AI · Deadline risk tracker<br>
                            You received this because email deadline reminders are enabled for your account.{$unsubscribeHtml}
                        </div>
                    </div>
                </div>
            </div>
            HTML;

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

    /**
     * @return array{icon:string, accent:string, soft:string, intro:string, action:string}
     */
    private function getAlertTheme(Notification $notification): array
    {
        return match ($notification->getTitle()) {
            'Assignment overdue' => [
                'icon' => '&#9888;',
                'accent' => '#dc2626',
                'soft' => '#fff1f2',
                'intro' => 'This deadline has already passed and needs attention.',
                'action' => 'Review the assignment immediately and update your progress or completion status.',
            ],
            'AI Risk Alert' => [
                'icon' => '&#9889;',
                'accent' => '#0f4c81',
                'soft' => '#eaf4ff',
                'intro' => 'The prediction model has flagged this assignment as high risk.',
                'action' => 'Prioritize this assignment and break the remaining work into smaller steps today.',
            ],
            default => [
                'icon' => '&#128276;',
                'accent' => '#0f4c81',
                'soft' => '#eaf4ff',
                'intro' => 'A deadline is coming up soon. A quick review now can save stress later.',
                'action' => 'Open the assignment, confirm what is left, and plan the next study session.',
            ],
        };
    }

    private function formatLabel(?string $value): string
    {
        if ($value === null || $value === '') {
            return 'Not set';
        }

        return ucwords(str_replace('_', ' ', $value));
    }
}
