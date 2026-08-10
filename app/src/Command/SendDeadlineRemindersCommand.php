<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\DeadlineEmailReminderService;
use App\Service\DeadlineNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-deadline-reminders',
    description: 'Generate deadline alerts and send pending email reminders.'
)]
class SendDeadlineRemindersCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly DeadlineNotificationService $deadlineNotificationService,
        private readonly DeadlineEmailReminderService $deadlineEmailReminderService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show reminders without sending emails or marking delivery.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of emails to process.', 50)
            ->addOption('recipient', null, InputOption::VALUE_REQUIRED, 'Only process reminders for one email address.')
            ->addOption('skip-generate', null, InputOption::VALUE_NONE, 'Skip generating fresh in-app deadline alerts before sending.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $limit = max(1, (int) $input->getOption('limit'));
        $recipient = $input->getOption('recipient');
        $recipient = is_string($recipient) && $recipient !== '' ? $recipient : null;

        if (!$input->getOption('skip-generate')) {
            $users = $this->userRepository->findActiveEmailReminderUsers($recipient);

            foreach ($users as $user) {
                $this->deadlineNotificationService->generateForUser($user);
            }

            $io->writeln(sprintf('Generated in-app deadline alerts for %d eligible user(s).', count($users)));
        }

        $result = $this->deadlineEmailReminderService->sendPendingReminders($dryRun, $limit, $recipient);

        if ($result['rows'] !== []) {
            $io->table(
                ['Email', 'Alert', 'Assignment', 'Status'],
                array_map(
                    static fn (array $row): array => [$row['email'], $row['title'], $row['assignment'], $row['status']],
                    $result['rows']
                )
            );
        }

        if ($dryRun) {
            $io->success(sprintf('Dry run complete. %d reminder email(s) would be sent.', $result['planned']));

            return Command::SUCCESS;
        }

        if ($result['failed'] > 0) {
            $io->warning(sprintf(
                'Processed %d reminder(s): %d sent, %d failed, %d skipped.',
                $result['planned'],
                $result['sent'],
                $result['failed'],
                $result['skipped']
            ));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Processed %d reminder(s): %d sent, %d skipped.',
            $result['planned'],
            $result['sent'],
            $result['skipped']
        ));

        return Command::SUCCESS;
    }
}
