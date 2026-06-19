<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619113149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add lesson video_path for platform-hosted video.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lessons ADD video_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lessons DROP video_path');
    }
}
