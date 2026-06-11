<?php

namespace App\Entity;

use App\Repository\TranscodeJobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TranscodeJobRepository::class)]
#[ORM\Table(name: 'transcode_jobs')]
#[ORM\Index(columns: ['profile_id', 'status'], name: 'idx_transcode_profile_status')]
class TranscodeJob
{
    public const STATUS_QUEUED    = 'queued';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Profile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Profile $profile;

    #[ORM\ManyToOne(targetEntity: CachedMedia::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CachedMedia $sourceMedia;

    #[ORM\ManyToOne(targetEntity: TranscodeProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TranscodeProfile $transcodeProfile;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_QUEUED;

    #[ORM\Column(type: 'bigint', options: ['default' => 0])]
    private int $bytesDone = 0;

    #[ORM\Column(type: 'bigint', options: ['default' => 0])]
    private int $totalBytes = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column]
    private \DateTimeImmutable $queuedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct()
    {
        $this->queuedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProfile(): Profile { return $this->profile; }
    public function setProfile(Profile $profile): static { $this->profile = $profile; return $this; }
    public function getSourceMedia(): CachedMedia { return $this->sourceMedia; }
    public function setSourceMedia(CachedMedia $sourceMedia): static { $this->sourceMedia = $sourceMedia; return $this; }
    public function getTranscodeProfile(): TranscodeProfile { return $this->transcodeProfile; }
    public function setTranscodeProfile(TranscodeProfile $transcodeProfile): static { $this->transcodeProfile = $transcodeProfile; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getBytesDone(): int { return $this->bytesDone; }
    public function setBytesDone(int $bytesDone): static { $this->bytesDone = $bytesDone; return $this; }
    public function getTotalBytes(): int { return $this->totalBytes; }
    public function setTotalBytes(int $totalBytes): static { $this->totalBytes = $totalBytes; return $this; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $errorMessage): static { $this->errorMessage = $errorMessage; return $this; }
    public function getQueuedAt(): \DateTimeImmutable { return $this->queuedAt; }
    public function getStartedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function setStartedAt(?\DateTimeImmutable $startedAt): static { $this->startedAt = $startedAt; return $this; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeImmutable $completedAt): static { $this->completedAt = $completedAt; return $this; }

    public function getProgressPercent(): float
    {
        if ($this->totalBytes <= 0) {
            return 0.0;
        }
        return round(($this->bytesDone / $this->totalBytes) * 100, 1);
    }
}
