<?php

namespace App\Service;

use App\Entity\Assignment;
use App\Entity\Prediction;
use Doctrine\ORM\EntityManagerInterface;

class AssignmentRiskPredictionService
{
    private const MODEL_NAME = 'decision_tree_rules_v1';

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function predictAndSave(Assignment $assignment): Prediction
    {
        [$riskLevel, $probability] = $this->calculateRisk($assignment);

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
        $prediction->setModelName(self::MODEL_NAME);
        $prediction->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')));

        $this->entityManager->flush();

        return $prediction;
    }

    private function calculateRisk(Assignment $assignment): array
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
