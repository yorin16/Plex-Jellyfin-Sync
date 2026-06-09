<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: profiles, connections, cached_media, overrides, jobs, config, rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE profiles (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE media_connections (
            id INT AUTO_INCREMENT NOT NULL,
            profile_id INT NOT NULL,
            role VARCHAR(16) NOT NULL,
            type VARCHAR(16) NOT NULL,
            url VARCHAR(512) NOT NULL,
            api_token VARCHAR(512) NOT NULL,
            library_id VARCHAR(64) DEFAULT NULL,
            library_root VARCHAR(512) DEFAULT NULL,
            last_scanned_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_PROFILE (profile_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_MEDIA_CONN_PROFILE FOREIGN KEY (profile_id) REFERENCES profiles (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE cached_media (
            id INT AUTO_INCREMENT NOT NULL,
            connection_id INT NOT NULL,
            title VARCHAR(512) NOT NULL,
            year SMALLINT DEFAULT NULL,
            tmdb_id VARCHAR(32) DEFAULT NULL,
            imdb_id VARCHAR(32) DEFAULT NULL,
            path VARCHAR(1024) DEFAULT NULL,
            file_size BIGINT DEFAULT NULL,
            codec VARCHAR(64) DEFAULT NULL,
            resolution VARCHAR(32) DEFAULT NULL,
            hdr_type VARCHAR(64) DEFAULT NULL,
            raw_metadata JSON DEFAULT NULL,
            last_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            external_id VARCHAR(128) DEFAULT NULL,
            INDEX idx_tmdb (tmdb_id),
            INDEX idx_imdb (imdb_id),
            INDEX idx_title_year (connection_id, title(191), year),
            INDEX IDX_CONNECTION (connection_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_CACHED_MEDIA_CONN FOREIGN KEY (connection_id) REFERENCES media_connections (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE match_overrides (
            id INT AUTO_INCREMENT NOT NULL,
            profile_id INT NOT NULL,
            source_media_id INT NOT NULL,
            destination_media_id INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UQ_PROFILE_SOURCE (profile_id, source_media_id),
            INDEX IDX_MO_PROFILE (profile_id),
            INDEX IDX_MO_SOURCE (source_media_id),
            INDEX IDX_MO_DEST (destination_media_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_MO_PROFILE FOREIGN KEY (profile_id) REFERENCES profiles (id) ON DELETE CASCADE,
            CONSTRAINT FK_MO_SOURCE FOREIGN KEY (source_media_id) REFERENCES cached_media (id) ON DELETE CASCADE,
            CONSTRAINT FK_MO_DEST FOREIGN KEY (destination_media_id) REFERENCES cached_media (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE ignored_items (
            id INT AUTO_INCREMENT NOT NULL,
            profile_id INT NOT NULL,
            source_media_id INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UQ_IGN_PROFILE_SOURCE (profile_id, source_media_id),
            INDEX IDX_IGN_PROFILE (profile_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_IGN_PROFILE FOREIGN KEY (profile_id) REFERENCES profiles (id) ON DELETE CASCADE,
            CONSTRAINT FK_IGN_SOURCE FOREIGN KEY (source_media_id) REFERENCES cached_media (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE transfer_configs (
            id INT AUTO_INCREMENT NOT NULL,
            profile_id INT NOT NULL,
            method VARCHAR(16) NOT NULL DEFAULT \'sftp\',
            config JSON NOT NULL,
            UNIQUE INDEX UQ_TC_PROFILE (profile_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_TC_PROFILE FOREIGN KEY (profile_id) REFERENCES profiles (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE transfer_jobs (
            id INT AUTO_INCREMENT NOT NULL,
            profile_id INT NOT NULL,
            source_media_id INT NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT \'queued\',
            bytes_copied BIGINT NOT NULL DEFAULT 0,
            total_bytes BIGINT NOT NULL DEFAULT 0,
            transfer_speed_bps INT DEFAULT NULL,
            error_message LONGTEXT DEFAULT NULL,
            queued_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            started_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_profile_status (profile_id, status),
            INDEX IDX_TJ_SOURCE (source_media_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_TJ_PROFILE FOREIGN KEY (profile_id) REFERENCES profiles (id) ON DELETE CASCADE,
            CONSTRAINT FK_TJ_SOURCE FOREIGN KEY (source_media_id) REFERENCES cached_media (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE validation_rules (
            id INT AUTO_INCREMENT NOT NULL,
            profile_id INT NOT NULL,
            rule_type VARCHAR(32) NOT NULL,
            operator VARCHAR(16) NOT NULL,
            value VARCHAR(255) NOT NULL,
            action VARCHAR(16) NOT NULL,
            INDEX IDX_VR_PROFILE (profile_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_VR_PROFILE FOREIGN KEY (profile_id) REFERENCES profiles (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE validation_rules');
        $this->addSql('DROP TABLE transfer_jobs');
        $this->addSql('DROP TABLE transfer_configs');
        $this->addSql('DROP TABLE ignored_items');
        $this->addSql('DROP TABLE match_overrides');
        $this->addSql('DROP TABLE cached_media');
        $this->addSql('DROP TABLE media_connections');
        $this->addSql('DROP TABLE profiles');
    }
}
