<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619075443 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add blog: categories, posts and newsletter subscriptions.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE blog_categories (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_DC356481989D9B62 (slug), UNIQUE INDEX UNIQ_DC356481B5B48B91 (public_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE blog_posts (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(280) NOT NULL, title VARCHAR(255) NOT NULL, excerpt VARCHAR(500) NOT NULL, body LONGTEXT NOT NULL, cover_image_path VARCHAR(255) DEFAULT NULL, read_time_minutes INT NOT NULL, is_featured TINYINT NOT NULL, status VARCHAR(255) NOT NULL, published_at DATETIME DEFAULT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id INT NOT NULL, author_id INT NOT NULL, UNIQUE INDEX UNIQ_78B2F932989D9B62 (slug), UNIQUE INDEX UNIQ_78B2F932B5B48B91 (public_id), INDEX IDX_78B2F93212469DE2 (category_id), INDEX IDX_78B2F932F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE newsletter_subscriptions (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, status VARCHAR(255) NOT NULL, unsubscribe_token VARCHAR(64) NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_B3C13B0BE7927C74 (email), UNIQUE INDEX UNIQ_B3C13B0BE0674361 (unsubscribe_token), UNIQUE INDEX UNIQ_B3C13B0BB5B48B91 (public_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE blog_posts ADD CONSTRAINT FK_78B2F93212469DE2 FOREIGN KEY (category_id) REFERENCES blog_categories (id)');
        $this->addSql('ALTER TABLE blog_posts ADD CONSTRAINT FK_78B2F932F675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog_posts DROP FOREIGN KEY FK_78B2F93212469DE2');
        $this->addSql('ALTER TABLE blog_posts DROP FOREIGN KEY FK_78B2F932F675F31B');
        $this->addSql('DROP TABLE blog_categories');
        $this->addSql('DROP TABLE blog_posts');
        $this->addSql('DROP TABLE newsletter_subscriptions');
    }
}
