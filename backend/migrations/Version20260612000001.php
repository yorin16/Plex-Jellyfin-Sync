<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612000001 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transcode_jobs ADD current_fps DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE transcode_jobs ADD current_speed VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transcode_jobs DROP COLUMN current_fps');
        $this->addSql('ALTER TABLE transcode_jobs DROP COLUMN current_speed');
    }
}
