<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260623094049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create enquiries table for website contact submissions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE enquiries (id INT AUTO_INCREMENT NOT NULL, full_name VARCHAR(150) NOT NULL, email VARCHAR(255) NOT NULL, subject VARCHAR(150) NOT NULL, message LONGTEXT NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_E7F817C1B5B48B91 (public_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE enquiries');
    }
}
