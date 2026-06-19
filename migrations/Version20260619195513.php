<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619195513 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add subscription failed_attempts, user_log user FK, and newsletter confirmation_token.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE newsletter_subscriptions ADD confirmation_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B3C13B0BC05FB297 ON newsletter_subscriptions (confirmation_token)');
        $this->addSql('ALTER TABLE subscriptions ADD failed_attempts INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE user_logs ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_logs ADD CONSTRAINT FK_8A0E8A95A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8A0E8A95A76ED395 ON user_logs (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_B3C13B0BC05FB297 ON newsletter_subscriptions');
        $this->addSql('ALTER TABLE newsletter_subscriptions DROP confirmation_token');
        $this->addSql('ALTER TABLE subscriptions DROP failed_attempts');
        $this->addSql('ALTER TABLE user_logs DROP FOREIGN KEY FK_8A0E8A95A76ED395');
        $this->addSql('DROP INDEX IDX_8A0E8A95A76ED395 ON user_logs');
        $this->addSql('ALTER TABLE user_logs DROP user_id');
    }
}
