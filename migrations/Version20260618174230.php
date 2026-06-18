<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260618174230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification_emails (id INT AUTO_INCREMENT NOT NULL, to_addresses JSON NOT NULL, cc_addresses JSON DEFAULT NULL, subject VARCHAR(150) NOT NULL, template VARCHAR(255) NOT NULL, context JSON NOT NULL, attachment_path VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, send_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL, response LONGTEXT DEFAULT NULL, attempts INT NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8826160FB5B48B91 (public_id), INDEX idx_notification_status_sendat (status, send_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE notification_emails');
    }
}
