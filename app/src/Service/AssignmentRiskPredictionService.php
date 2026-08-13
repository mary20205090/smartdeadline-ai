<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Assignment;
use App\Entity\Prediction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\Process;

class AssignmentRiskPredictionService
{
    private const ML_MODEL_NAME = 'decision_tree_model_v1';
    private const FALLBACK_MODEL_NAME = 'decision_tree_rules_fallback_v1';
    private const CACHE_MINUTES = 60;
    private const STUDENT_ACTIVITY_EVENTS = [
        'assignment_created',
        'assignment_updated',
        'assignment_started',
        'assignment_completed',
        'assignment_deleted',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function predictAndSave(Assignment $assignment, bool $forceRefresh = false): Prediction
    {
        if ($assignment->getStatus() === 'completed') {
            return $this->saveCompletedPrediction($assignment);
        }

        if (!$forceRefresh) {
            $recentPrediction = $this->getRecentPrediction($assignment);

            if ($recentPrediction !== null) {
                return $recentPrediction;
            }
        }

        $features = $this->buildFeatures($assignment);
        $mlResult = $this->predictUsingPythonModel($features);

        if ($mlResult !== null) {
            $riskLevel = $mlResult['risk_level'];
            $probability = $mlResult['probability'];
            $modelName = $mlResult['model_name'];
        } else {
            [$riskLevel, $probability] = $this->calculateFallbackRisk($assignment);
            $modelName = self::FALLBACK_MODEL_NAME;
        }

        [$riskLevel, $probability] = $this->applyDeadlineUrgencyGuard($assignment, $riskLevel, $probability);

        $prediction = $this->entityManager
            ->getRepository(Prediction::class)
            ->findOneBy(
                ['assignment' => $assignment],
                ['createdAt' => 'DESC']
            );
        $previousRiskLevel = $prediction?->getRiskLevel();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi'));

        if (!$prediction) {
            $prediction = new Prediction();
            $prediction->setAssignment($assignment);
            $prediction->setCreatedAt($now);
            $this->entityManager->persist($prediction);
        }

        $this->logPredictionActivity($assignment, $previousRiskLevel, $riskLevel);

        $prediction->setRiskLevel($riskLevel);
        $prediction->setProbability($probability);
        $prediction->setModelName($modelName);
        $prediction->setUpdatedAt($now);

        $this->entityManager->flush();

        return $prediction;
    }

    private function saveCompletedPrediction(Assignment $assignment): Prediction
    {
        $prediction = $this->entityManager
            ->getRepository(Prediction::class)
            ->findOneBy(
                ['assignment' => $assignment],
                ['createdAt' => 'DESC']
            );
        $previousRiskLevel = $prediction?->getRiskLevel();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi'));

        if (!$prediction) {
            $prediction = new Prediction();
            $prediction->setAssignment($assignment);
            $prediction->setCreatedAt($now);
            $this->entityManager->persist($prediction);
        }

        $this->logPredictionActivity($assignment, $previousRiskLevel, 'low');

        $prediction->setRiskLevel('low');
        $prediction->setProbability(0.05);
        $prediction->setModelName(self::ML_MODEL_NAME);
        $prediction->setUpdatedAt($now);

        $this->entityManager->flush();

        return $prediction;
    }

    private function getRecentPrediction(Assignment $assignment): ?Prediction
    {
        $prediction = $this->entityManager
            ->getRepository(Prediction::class)
            ->findOneBy(
                ['assignment' => $assignment],
                ['createdAt' => 'DESC']
            );

        if ($prediction === null) {
            return null;
        }

        if ($prediction->getModelName() !== self::ML_MODEL_NAME) {
            return null;
        }

        $lastUpdatedAt = $prediction->getUpdatedAt() ?? $prediction->getCreatedAt();

        if ($lastUpdatedAt === null) {
            return null;
        }

        if ($this->isUrgentAssignment($assignment)) {
            return null;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi'));
        $ageInSeconds = $now->getTimestamp() - $lastUpdatedAt->getTimestamp();

        if ($ageInSeconds <= self::CACHE_MINUTES * 60) {
            return $prediction;
        }

        return null;
    }

    private function buildFeatures(Assignment $assignment): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi'));
        $deadline = $this->getLocalDeadline($assignment);

        $daysToDeadline = (int) floor(($deadline->getTimestamp() - $now->getTimestamp()) / 86400);

        $user = $assignment->getCourse()?->getUser();

        $pendingAssignments = 0;
        $previousLateSubmissions = 0;
        $recentActivityCount = 0;
        $latestActivityDate = null;
        $loginFrequency = 0;

        if ($user !== null) {
            $loginFrequency = min(14, $user->getLoginCount());

            if ($user->getLastLoginAt() !== null) {
                $latestActivityDate = $user->getLastLoginAt();
            }

            foreach ($user->getCourses() as $course) {
                if ($course->isDeleted()) {
                    continue;
                }

                foreach ($course->getAssignments() as $studentAssignment) {
                    if ($studentAssignment->isDeleted()) {
                        continue;
                    }

                    if ($studentAssignment->getStatus() !== 'completed') {
                        $pendingAssignments++;
                    }

                    if (
                        $studentAssignment->getCompletedAt() !== null
                        && $studentAssignment->getDeadline() !== null
                        && $studentAssignment->getCompletedAt() > $studentAssignment->getDeadline()
                    ) {
                        $previousLateSubmissions++;
                    }

                    foreach ($studentAssignment->getActivityLogs() as $activityLog) {
                        if (!in_array($activityLog->getEventType(), self::STUDENT_ACTIVITY_EVENTS, true)) {
                            continue;
                        }

                        $createdAt = $activityLog->getCreatedAt();

                        if ($createdAt === null) {
                            continue;
                        }

                        if ($createdAt >= $now->modify('-7 days')) {
                            $recentActivityCount++;
                        }

                        if ($latestActivityDate === null || $createdAt > $latestActivityDate) {
                            $latestActivityDate = $createdAt;
                        }
                    }
                }
            }
        }

        $inactivityDays = 30;

        if ($latestActivityDate !== null) {
            $inactivityDays = (int) $latestActivityDate->diff($now)->format('%a');
        }

        return [
            'days_to_deadline' => $daysToDeadline,
            'priority' => $assignment->getPriority() ?: 'medium',
            'status' => $assignment->getStatus() ?: 'pending',
            'login_frequency' => $loginFrequency,
            'previous_late_submissions' => $previousLateSubmissions,
            'pending_assignments' => $pendingAssignments,
            'recent_activity_count' => $recentActivityCount,
            'inactivity_days' => $inactivityDays,
        ];
    }

    private function predictUsingPythonModel(array $features): ?array
    {
        $projectRoot = dirname(__DIR__, 3);
        $mlServicePath = $projectRoot . '/ml-service';
        $pythonPath = $mlServicePath . '/.venv/bin/python';
        $predictScriptPath = $mlServicePath . '/predict.py';

        if (!file_exists($pythonPath) || !file_exists($predictScriptPath)) {
            return null;
        }

        $process = new Process([$pythonPath, $predictScriptPath]);
        $process->setWorkingDirectory($mlServicePath);
        $process->setInput(json_encode($features));
        $process->setTimeout(10);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $output = trim($process->getOutput());
        $result = json_decode($output, true);

        if (!is_array($result) || isset($result['error'])) {
            return null;
        }

        return [
            'risk_level' => $result['risk_level'] ?? 'medium',
            'probability' => isset($result['probability']) ? (float) $result['probability'] : null,
            'model_name' => $result['model_name'] ?? self::ML_MODEL_NAME,
        ];
    }

    private function calculateFallbackRisk(Assignment $assignment): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi'));
        $deadline = $this->getLocalDeadline($assignment);

        if ($assignment->getStatus() === 'completed') {
            return ['low', 0.05];
        }

        $secondsToDeadline = $deadline->getTimestamp() - $now->getTimestamp();
        $daysToDeadline = (int) floor($secondsToDeadline / 86400);

        if ($secondsToDeadline < 0) {
            return ['high', 0.95];
        }

        $score = 0.20;

        if ($daysToDeadline <= 1) {
            $score += 0.35;
        } elseif ($daysToDeadline <= 3) {
            $score += 0.25;
        } elseif ($daysToDeadline <= 7) {
            $score += 0.10;
        }

        if ($assignment->getStatus() === 'pending') {
            $score += 0.15;
        }

        if ($assignment->getStatus() === 'in_progress') {
            $score -= 0.10;
        }

        if ($assignment->getPriority() === 'high') {
            $score += 0.15;
        } elseif ($assignment->getPriority() === 'medium') {
            $score += 0.05;
        }

        $score = max(0.05, min(0.95, $score));

        if ($score >= 0.70) {
            return ['high', round($score, 2)];
        }

        if ($score >= 0.40) {
            return ['medium', round($score, 2)];
        }

        return ['low', round($score, 2)];
    }

    private function applyDeadlineUrgencyGuard(Assignment $assignment, string $riskLevel, ?float $probability): array
    {
        if ($assignment->getStatus() === 'completed') {
            return ['low', 0.05];
        }

        $secondsToDeadline = $this->getLocalDeadline($assignment)->getTimestamp()
            - (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')))->getTimestamp();

        if ($secondsToDeadline < 0) {
            return ['high', max($probability ?? 0, 0.95)];
        }

        if ($assignment->getStatus() === 'pending' && $secondsToDeadline <= 3600) {
            return ['high', max($probability ?? 0, 0.90)];
        }

        if (
            $assignment->getStatus() === 'pending'
            && $assignment->getPriority() === 'high'
            && $secondsToDeadline <= 86400
        ) {
            return ['high', max($probability ?? 0, 0.85)];
        }

        if ($riskLevel === 'low' && $secondsToDeadline <= 21600) {
            return ['medium', max($probability ?? 0, 0.60)];
        }

        return [$riskLevel, $probability];
    }

    private function isUrgentAssignment(Assignment $assignment): bool
    {
        if ($assignment->getStatus() === 'completed') {
            return false;
        }

        $secondsToDeadline = $this->getLocalDeadline($assignment)->getTimestamp()
            - (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')))->getTimestamp();

        return $secondsToDeadline <= 86400;
    }

    private function getLocalDeadline(Assignment $assignment): \DateTimeImmutable
    {
        $deadline = $assignment->getDeadline();

        return new \DateTimeImmutable(
            $deadline->format('Y-m-d H:i:s'),
            new \DateTimeZone('Africa/Nairobi')
        );
    }

    private function logPredictionActivity(Assignment $assignment, ?string $previousRiskLevel, string $newRiskLevel): void
    {
        $this->createActivityLog($assignment, 'prediction_generated');

        if ($previousRiskLevel !== null && $previousRiskLevel !== $newRiskLevel) {
            $this->createActivityLog($assignment, 'risk_changed_to_'.$newRiskLevel);
        }
    }

    private function createActivityLog(Assignment $assignment, string $eventType): void
    {
        $activityLog = new ActivityLog();
        $activityLog->setAssignment($assignment);
        $activityLog->setEventType($eventType);
        $activityLog->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')));

        $this->entityManager->persist($activityLog);
    }
}
