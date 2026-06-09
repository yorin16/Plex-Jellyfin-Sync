<?php

namespace App\Entity;

use App\Repository\ProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfileRepository::class)]
#[ORM\Table(name: 'profiles')]
class Profile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: MediaConnection::class, mappedBy: 'profile', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $connections;

    #[ORM\OneToMany(targetEntity: TransferJob::class, mappedBy: 'profile', cascade: ['persist', 'remove'])]
    private Collection $transferJobs;

    #[ORM\OneToMany(targetEntity: MatchOverride::class, mappedBy: 'profile', cascade: ['persist', 'remove'])]
    private Collection $matchOverrides;

    #[ORM\OneToMany(targetEntity: IgnoredItem::class, mappedBy: 'profile', cascade: ['persist', 'remove'])]
    private Collection $ignoredItems;

    #[ORM\OneToMany(targetEntity: ValidationRule::class, mappedBy: 'profile', cascade: ['persist', 'remove'])]
    private Collection $validationRules;

    #[ORM\OneToOne(targetEntity: TransferConfig::class, mappedBy: 'profile', cascade: ['persist', 'remove'])]
    private ?TransferConfig $transferConfig = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->connections = new ArrayCollection();
        $this->transferJobs = new ArrayCollection();
        $this->matchOverrides = new ArrayCollection();
        $this->ignoredItems = new ArrayCollection();
        $this->validationRules = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, MediaConnection> */
    public function getConnections(): Collection { return $this->connections; }

    /** @return Collection<int, TransferJob> */
    public function getTransferJobs(): Collection { return $this->transferJobs; }

    /** @return Collection<int, MatchOverride> */
    public function getMatchOverrides(): Collection { return $this->matchOverrides; }

    /** @return Collection<int, IgnoredItem> */
    public function getIgnoredItems(): Collection { return $this->ignoredItems; }

    /** @return Collection<int, ValidationRule> */
    public function getValidationRules(): Collection { return $this->validationRules; }

    public function getTransferConfig(): ?TransferConfig { return $this->transferConfig; }
    public function setTransferConfig(?TransferConfig $config): static { $this->transferConfig = $config; return $this; }
}
