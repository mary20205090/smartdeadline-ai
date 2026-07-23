<?php

namespace App\Service;

use App\Entity\Assignment;
use App\Entity\Prediction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\Process;

class AssignmentRiskPredictionService
{
    private const ML_MODEL_NAME = 'decision_tree_model_v1';
    private const FALLBACK_MODEL_NAME = 'decision_tree_rules_fallback_v1';
    private const CACHE_MINUTES = 60;

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function predictAndSave(Assignment $assignment, bool $forceRefresh = false): Prediction
    {
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

        $prediction = $this->entityManager
            ->getRepository(Prediction::class)
            ->findOneBy(
                ['assignment' => $assignment],
                ['createdAt' => 'DESC']
            );

        if (!$prediction) {
            $prediction = new Prediction();
            $prediction->setAssignment($assignment);
            $this->entityManager->persist($prediction);
        }

        $prediction->setRiskLevel($riskLevel);
        $prediction->setProbability($probability);
        $prediction->setModelName($modelName);
        $prediction->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')));

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

        if ($prediction->getCreatedAt() === null) {
            return null;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi'));
        $ageInSeconds = $now->getTimestamp() - $prediction->getCreatedAt()->getTimestamp();

        if ($ageInSeconds <= self::CACHE_MINUTES * 60) {
            return $prediction;
        }

        return null;
    }

    private function buildFeatures(Assignment $assignment): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi'));
        $deadline = $assignment->getDeadline();

        $daysToDeadline = (int) floor(($deadline->getTimestamp() - $now->getTimestamp()) / 86400);

        $user = $assignment->getCourse()?->getUser();

        $pendingAssignments = 0;
        $previousLateSubmissions = 0;
        $recentActivityCount = 0;
        $latestActivityDate = null;

        if ($user !== null) {
            foreach ($user->getCourses() as $course) {
                foreach ($course->getAssignments() as $studentAssignment) {
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

        $loginFrequency = min(14, $recentActivityCount);

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
        $deadline = $assignment->getDeadline();

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
}
