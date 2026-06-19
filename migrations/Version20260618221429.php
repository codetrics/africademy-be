<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260618221429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add community hub: posts, comments and post likes.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE community_comments (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, post_id INT NOT NULL, author_id INT NOT NULL, UNIQUE INDEX UNIQ_ADD8530DB5B48B91 (public_id), INDEX IDX_ADD8530D4B89032C (post_id), INDEX IDX_ADD8530DF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE community_post_likes (id INT AUTO_INCREMENT NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, post_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_EF1AB48EB5B48B91 (public_id), INDEX IDX_EF1AB48E4B89032C (post_id), INDEX IDX_EF1AB48EA76ED395 (user_id), UNIQUE INDEX uniq_community_like_post_user (post_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE community_posts (id INT AUTO_INCREMENT NOT NULL, tag VARCHAR(255) NOT NULL, title VARCHAR(200) NOT NULL, body LONGTEXT NOT NULL, image_path VARCHAR(255) DEFAULT NULL, link_url VARCHAR(1024) DEFAULT NULL, like_count INT NOT NULL, comment_count INT NOT NULL, status VARCHAR(255) NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, author_id INT NOT NULL, UNIQUE INDEX UNIQ_F32DC0BEB5B48B91 (public_id), INDEX IDX_F32DC0BEF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE community_comments ADD CONSTRAINT FK_ADD8530D4B89032C FOREIGN KEY (post_id) REFERENCES community_posts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE community_comments ADD CONSTRAINT FK_ADD8530DF675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE community_post_likes ADD CONSTRAINT FK_EF1AB48E4B89032C FOREIGN KEY (post_id) REFERENCES community_posts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE community_post_likes ADD CONSTRAINT FK_EF1AB48EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE community_posts ADD CONSTRAINT FK_F32DC0BEF675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE community_comments DROP FOREIGN KEY FK_ADD8530D4B89032C');
        $this->addSql('ALTER TABLE community_comments DROP FOREIGN KEY FK_ADD8530DF675F31B');
        $this->addSql('ALTER TABLE community_post_likes DROP FOREIGN KEY FK_EF1AB48E4B89032C');
        $this->addSql('ALTER TABLE community_post_likes DROP FOREIGN KEY FK_EF1AB48EA76ED395');
        $this->addSql('ALTER TABLE community_posts DROP FOREIGN KEY FK_F32DC0BEF675F31B');
        $this->addSql('DROP TABLE community_comments');
        $this->addSql('DROP TABLE community_post_likes');
        $this->addSql('DROP TABLE community_posts');
    }
}
