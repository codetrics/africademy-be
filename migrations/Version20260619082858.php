<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619082858 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email campaigns.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email_campaigns (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(150) NOT NULL, heading VARCHAR(150) NOT NULL, body LONGTEXT NOT NULL, segment VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, recipient_count INT NOT NULL, sent_at DATETIME DEFAULT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_EC78EB5BB5B48B91 (public_id), INDEX IDX_EC78EB5BB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE email_campaigns ADD CONSTRAINT FK_EC78EB5BB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_campaigns DROP FOREIGN KEY FK_EC78EB5BB03A8386');
        $this->addSql('DROP TABLE email_campaigns');
    }
}
