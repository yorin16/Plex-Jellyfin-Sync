<?php

namespace App\Entity;

use App\Repository\TransferJobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransferJobRepository::class)]
#[ORM\Table(name: 'transfer_jobs')]
#[ORM\Index(columns: ['profile_id', 'status'], name: 'idx_profile_status')]
class TransferJob
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Profile::class, inversedBy: 'transferJobs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Profile $profile;

    #[ORM\ManyToOne(targetEntity: CachedMedia::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CachedMedia $sourceMedia;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_QUEUED;

    #[ORM\Column(type: 'bigint', options: ['default' => 0])]
    private int $bytesCopied = 0;

    #[ORM\Column(type: 'bigint', options: ['default' => 0])]
    private int $totalBytes = 0;

    #[ORM\Column(nullable: true)]
    private ?int $transferSpeedBps = null;

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
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getBytesCopied(): int { return $this->bytesCopied; }
    public function setBytesCopied(int $bytesCopied): static { $this->bytesCopied = $bytesCopied; return $this; }
    public function getTotalBytes(): int { return $this->totalBytes; }
    public function setTotalBytes(int $totalBytes): static { $this->totalBytes = $totalBytes; return $this; }
    public function getTransferSpeedBps(): ?int { return $this->transferSpeedBps; }
    public function setTransferSpeedBps(?int $transferSpeedBps): static { $this->transferSpeedBps = $transferSpeedBps; return $this; }
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
        return round(($this->bytesCopied / $this->totalBytes) * 100, 1);
    }
}
