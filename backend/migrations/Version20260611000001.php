<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611000001 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE transcode_profiles (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(128) NOT NULL,
            video_codec VARCHAR(32) NOT NULL DEFAULT \'hevc_qsv\',
            video_bitrate_kbps INT NOT NULL DEFAULT 5000,
            max_height INT DEFAULT NULL,
            hdr_to_sdr TINYINT(1) NOT NULL DEFAULT 1,
            lossless_audio_codec VARCHAR(16) NOT NULL DEFAULT \'eac3\',
            lossless_audio_bitrate_kbps INT NOT NULL DEFAULT 768,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        $this->addSql('CREATE TABLE transcode_jobs (
            id INT AUTO_INCREMENT NOT NULL,
            profile_id INT NOT NULL,
            source_media_id INT NOT NULL,
            transcode_profile_id INT NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT \'queued\',
            bytes_done BIGINT NOT NULL DEFAULT 0,
            total_bytes BIGINT NOT NULL DEFAULT 0,
            error_message LONGTEXT DEFAULT NULL,
            queued_at DATETIME NOT NULL,
            started_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            INDEX idx_transcode_profile_status (profile_id, status),
            CONSTRAINT fk_tj_profile FOREIGN KEY (profile_id) REFERENCES profiles (id) ON DELETE CASCADE,
            CONSTRAINT fk_tj_source_media FOREIGN KEY (source_media_id) REFERENCES cached_media (id) ON DELETE CASCADE,
            CONSTRAINT fk_tj_transcode_profile FOREIGN KEY (transcode_profile_id) REFERENCES transcode_profiles (id) ON DELETE CASCADE,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        $this->addSql('ALTER TABLE transfer_jobs ADD source_dir_override VARCHAR(1024) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE transcode_jobs');
        $this->addSql('DROP TABLE transcode_profiles');
        $this->addSql('ALTER TABLE transfer_jobs DROP COLUMN source_dir_override');
    }
}
