<?php

namespace App\Entity;

use App\Repository\CachedMediaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CachedMediaRepository::class)]
#[ORM\Table(name: 'cached_media')]
#[ORM\Index(columns: ['tmdb_id'], name: 'idx_tmdb')]
#[ORM\Index(columns: ['imdb_id'], name: 'idx_imdb')]
#[ORM\Index(columns: ['connection_id', 'title', 'year'], name: 'idx_title_year')]
class CachedMedia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MediaConnection::class, inversedBy: 'cachedMedia')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MediaConnection $connection;

    #[ORM\Column(length: 512)]
    private string $title;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $year = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $tmdbId = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $imdbId = null;

    /** Folder path relative to the library root */
    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $path = null;

    /** Size in bytes */
    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $codec = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $resolution = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $hdrType = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $rawMetadata = null;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;

    /** External ID used by the media server (Plex ratingKey or Jellyfin ItemId) */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $externalId = null;

    public function __construct()
    {
        $this->lastSeenAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getConnection(): MediaConnection { return $this->connection; }
    public function setConnection(MediaConnection $connection): static { $this->connection = $connection; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getYear(): ?int { return $this->year; }
    public function setYear(?int $year): static { $this->year = $year; return $this; }
    public function getTmdbId(): ?string { return $this->tmdbId; }
    public function setTmdbId(?string $tmdbId): static { $this->tmdbId = $tmdbId; return $this; }
    public function getImdbId(): ?string { return $this->imdbId; }
    public function setImdbId(?string $imdbId): static { $this->imdbId = $imdbId; return $this; }
    public function getPath(): ?string { return $this->path; }
    public function setPath(?string $path): static { $this->path = $path; return $this; }
    public function getFileSize(): ?int { return $this->fileSize; }
    public function setFileSize(?int $fileSize): static { $this->fileSize = $fileSize; return $this; }
    public function getCodec(): ?string { return $this->codec; }
    public function setCodec(?string $codec): static { $this->codec = $codec; return $this; }
    public function getResolution(): ?string { return $this->resolution; }
    public function setResolution(?string $resolution): static { $this->resolution = $resolution; return $this; }
    public function getHdrType(): ?string { return $this->hdrType; }
    public function setHdrType(?string $hdrType): static { $this->hdrType = $hdrType; return $this; }
    public function getRawMetadata(): ?array { return $this->rawMetadata; }
    public function setRawMetadata(?array $rawMetadata): static { $this->rawMetadata = $rawMetadata; return $this; }
    public function getLastSeenAt(): \DateTimeImmutable { return $this->lastSeenAt; }
    public function setLastSeenAt(\DateTimeImmutable $lastSeenAt): static { $this->lastSeenAt = $lastSeenAt; return $this; }
    public function getExternalId(): ?string { return $this->externalId; }
    public function setExternalId(?string $externalId): static { $this->externalId = $externalId; return $this; }
}
