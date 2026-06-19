<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260618172651 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_log_types (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_79AEEF89989D9B62 (slug), UNIQUE INDEX UNIQ_79AEEF89B5B48B91 (public_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user_logs (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, message VARCHAR(255) NOT NULL, context JSON DEFAULT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_log_type_id INT NOT NULL, UNIQUE INDEX UNIQ_8A0E8A95B5B48B91 (public_id), INDEX IDX_8A0E8A95549A7D91 (user_log_type_id), INDEX idx_user_log_username (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE user_logs ADD CONSTRAINT FK_8A0E8A95549A7D91 FOREIGN KEY (user_log_type_id) REFERENCES user_log_types (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_logs DROP FOREIGN KEY FK_8A0E8A95549A7D91');
        $this->addSql('DROP TABLE user_log_types');
        $this->addSql('DROP TABLE user_logs');
    }
}
