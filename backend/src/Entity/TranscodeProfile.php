<?php

namespace App\Entity;

use App\Repository\TranscodeProfileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TranscodeProfileRepository::class)]
#[ORM\Table(name: 'transcode_profiles')]
class TranscodeProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    private string $name = '';

    #[ORM\Column(length: 32)]
    private string $videoCodec = 'hevc_qsv';

    #[ORM\Column]
    private int $videoBitrateKbps = 5000;

    #[ORM\Column(nullable: true)]
    private ?int $maxHeight = 1080;

    #[ORM\Column]
    private bool $hdrToSdr = true;

    /** Codec to use when transcoding lossless audio (truehd, dts-hd ma, pcm, etc.) */
    #[ORM\Column(length: 16)]
    private string $losslessAudioCodec = 'eac3';

    #[ORM\Column]
    private int $losslessAudioBitrateKbps = 768;

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getVideoCodec(): string { return $this->videoCodec; }
    public function setVideoCodec(string $videoCodec): static { $this->videoCodec = $videoCodec; return $this; }
    public function getVideoBitrateKbps(): int { return $this->videoBitrateKbps; }
    public function setVideoBitrateKbps(int $videoBitrateKbps): static { $this->videoBitrateKbps = $videoBitrateKbps; return $this; }
    public function getMaxHeight(): ?int { return $this->maxHeight; }
    public function setMaxHeight(?int $maxHeight): static { $this->maxHeight = $maxHeight; return $this; }
    public function isHdrToSdr(): bool { return $this->hdrToSdr; }
    public function setHdrToSdr(bool $hdrToSdr): static { $this->hdrToSdr = $hdrToSdr; return $this; }
    public function getLosslessAudioCodec(): string { return $this->losslessAudioCodec; }
    public function setLosslessAudioCodec(string $losslessAudioCodec): static { $this->losslessAudioCodec = $losslessAudioCodec; return $this; }
    public function getLosslessAudioBitrateKbps(): int { return $this->losslessAudioBitrateKbps; }
    public function setLosslessAudioBitrateKbps(int $losslessAudioBitrateKbps): static { $this->losslessAudioBitrateKbps = $losslessAudioBitrateKbps; return $this; }
}
