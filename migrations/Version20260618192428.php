<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260618192428 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bundles (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, slug VARCHAR(220) NOT NULL, description LONGTEXT DEFAULT NULL, thumbnail_path VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, price_amount_cents INT NOT NULL, price_currency VARCHAR(3) NOT NULL, owner_id INT NOT NULL, UNIQUE INDEX UNIQ_D8A73A98989D9B62 (slug), UNIQUE INDEX UNIQ_D8A73A98B5B48B91 (public_id), INDEX IDX_D8A73A987E3C61F9 (owner_id), INDEX idx_bundle_status (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE bundle_courses (bundle_id INT NOT NULL, course_id INT NOT NULL, INDEX IDX_5F5DE335F1FAD9D3 (bundle_id), INDEX IDX_5F5DE335591CC992 (course_id), PRIMARY KEY (bundle_id, course_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE bundles ADD CONSTRAINT FK_D8A73A987E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE bundle_courses ADD CONSTRAINT FK_5F5DE335F1FAD9D3 FOREIGN KEY (bundle_id) REFERENCES bundles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bundle_courses ADD CONSTRAINT FK_5F5DE335591CC992 FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE orders ADD bundle_id INT DEFAULT NULL, CHANGE course_id course_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEF1FAD9D3 FOREIGN KEY (bundle_id) REFERENCES bundles (id)');
        $this->addSql('CREATE INDEX IDX_E52FFDEEF1FAD9D3 ON orders (bundle_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bundles DROP FOREIGN KEY FK_D8A73A987E3C61F9');
        $this->addSql('ALTER TABLE bundle_courses DROP FOREIGN KEY FK_5F5DE335F1FAD9D3');
        $this->addSql('ALTER TABLE bundle_courses DROP FOREIGN KEY FK_5F5DE335591CC992');
        $this->addSql('DROP TABLE bundles');
        $this->addSql('DROP TABLE bundle_courses');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEEF1FAD9D3');
        $this->addSql('DROP INDEX IDX_E52FFDEEF1FAD9D3 ON orders');
        $this->addSql('ALTER TABLE orders DROP bundle_id, CHANGE course_id course_id INT NOT NULL');
    }
}
