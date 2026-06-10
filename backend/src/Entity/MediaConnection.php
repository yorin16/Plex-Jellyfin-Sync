<?php

namespace App\Entity;

use App\Repository\MediaConnectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MediaConnectionRepository::class)]
#[ORM\Table(name: 'media_connections')]
class MediaConnection
{
    public const ROLE_SOURCE = 'source';
    public const ROLE_DESTINATION = 'destination';

    public const TYPE_PLEX = 'plex';
    public const TYPE_JELLYFIN = 'jellyfin';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Profile::class, inversedBy: 'connections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Profile $profile;

    #[ORM\Column(length: 16)]
    private string $role;

    #[ORM\Column(length: 16)]
    private string $type;

    #[ORM\Column(length: 512)]
    private string $url;

    #[ORM\Column(length: 512)]
    private string $apiToken;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $libraryId = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $libraryRoot = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $localPath = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastScannedAt = null;

    #[ORM\OneToMany(targetEntity: CachedMedia::class, mappedBy: 'connection', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $cachedMedia;

    public function __construct()
    {
        $this->cachedMedia = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getProfile(): Profile { return $this->profile; }
    public function setProfile(Profile $profile): static { $this->profile = $profile; return $this; }
    public function getRole(): string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getUrl(): string { return $this->url; }
    public function setUrl(string $url): static { $this->url = rtrim($url, '/'); return $this; }
    public function getApiToken(): string { return $this->apiToken; }
    public function setApiToken(string $apiToken): static { $this->apiToken = $apiToken; return $this; }
    public function getLibraryId(): ?string { return $this->libraryId; }
    public function setLibraryId(?string $libraryId): static { $this->libraryId = $libraryId; return $this; }
    public function getLibraryRoot(): ?string { return $this->libraryRoot; }
    public function setLibraryRoot(?string $libraryRoot): static { $this->libraryRoot = $libraryRoot; return $this; }
    public function getLocalPath(): ?string { return $this->localPath; }
    public function setLocalPath(?string $localPath): static { $this->localPath = $localPath; return $this; }
    public function getLastScannedAt(): ?\DateTimeImmutable { return $this->lastScannedAt; }
    public function setLastScannedAt(?\DateTimeImmutable $lastScannedAt): static { $this->lastScannedAt = $lastScannedAt; return $this; }

    /** @return Collection<int, CachedMedia> */
    public function getCachedMedia(): Collection { return $this->cachedMedia; }
}
