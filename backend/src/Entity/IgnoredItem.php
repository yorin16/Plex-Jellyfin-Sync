<?php

namespace App\Entity;

use App\Repository\IgnoredItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IgnoredItemRepository::class)]
#[ORM\Table(name: 'ignored_items')]
#[ORM\UniqueConstraint(columns: ['profile_id', 'source_media_id'])]
class IgnoredItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Profile::class, inversedBy: 'ignoredItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Profile $profile;

    #[ORM\ManyToOne(targetEntity: CachedMedia::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CachedMedia $sourceMedia;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProfile(): Profile { return $this->profile; }
    public function setProfile(Profile $profile): static { $this->profile = $profile; return $this; }
    public function getSourceMedia(): CachedMedia { return $this->sourceMedia; }
    public function setSourceMedia(CachedMedia $sourceMedia): static { $this->sourceMedia = $sourceMedia; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
