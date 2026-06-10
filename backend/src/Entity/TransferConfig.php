<?php

namespace App\Entity;

use App\Repository\TransferConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransferConfigRepository::class)]
#[ORM\Table(name: 'transfer_configs')]
class TransferConfig
{
    public const METHOD_LOCAL = 'local';
    public const METHOD_SFTP  = 'sftp';
    public const METHOD_RSYNC = 'rsync';
    public const METHOD_SMB   = 'smb';
    public const METHOD_FTP   = 'ftp';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Profile::class, inversedBy: 'transferConfig')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Profile $profile;

    #[ORM\Column(length: 16)]
    private string $method = self::METHOD_SFTP;

    /**
     * JSON config, keys depend on method:
     * sftp:  host, port, username, password, key_path, dest_base_path
     * local: dest_base_path
     * smb:   host, share, username, password, dest_base_path
     * ftp:   host, port, username, password, dest_base_path
     */
    #[ORM\Column(type: 'json')]
    private array $config = [];

    public function getId(): ?int { return $this->id; }
    public function getProfile(): Profile { return $this->profile; }
    public function setProfile(Profile $profile): static { $this->profile = $profile; return $this; }
    public function getMethod(): string { return $this->method; }
    public function setMethod(string $method): static { $this->method = $method; return $this; }
    public function getConfig(): array { return $this->config; }
    public function setConfig(array $config): static { $this->config = $config; return $this; }
}
