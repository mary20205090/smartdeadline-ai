<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $eventType = null;

    #[ORM\Column(nullable: true)]
    private ?int $loginFrequency = null;

    #[ORM\Column(nullable: true)]
    private ?int $previousLateSubmissions = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'activityLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Assignment $assignment = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getLoginFrequency(): ?int
    {
        return $this->loginFrequency;
    }

    public function setLoginFrequency(?int $loginFrequency): static
    {
        $this->loginFrequency = $loginFrequency;

        return $this;
    }

    public function getPreviousLateSubmissions(): ?int
    {
        return $this->previousLateSubmissions;
    }

    public function setPreviousLateSubmissions(?int $previousLateSubmissions): static
    {
        $this->previousLateSubmissions = $previousLateSubmissions;

        return $this;
    }

    public function getLastLoginDate(): ?\DateTimeImmutable
    {
        return $this->lastLoginDate;
    }

    public function setLastLoginDate(?\DateTimeImmutable $lastLoginDate): static
    {
        $this->lastLoginDate = $lastLoginDate;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getAssignment(): ?Assignment
    {
        return $this->assignment;
    }

    public function setAssignment(?Assignment $assignment): static
    {
        $this->assignment = $assignment;

        return $this;
    }
}
