<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260618202343 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reviews (id INT AUTO_INCREMENT NOT NULL, rating SMALLINT NOT NULL, body LONGTEXT DEFAULT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, student_id INT NOT NULL, course_id INT NOT NULL, UNIQUE INDEX UNIQ_6970EB0FB5B48B91 (public_id), INDEX IDX_6970EB0FCB944F1A (student_id), INDEX IDX_6970EB0F591CC992 (course_id), UNIQUE INDEX uniq_review_student_course (student_id, course_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0FCB944F1A FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0F591CC992 FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE courses ADD rating_average DOUBLE PRECISION DEFAULT 0 NOT NULL, ADD rating_count INT DEFAULT 0 NOT NULL, ADD enrollment_count INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reviews DROP FOREIGN KEY FK_6970EB0FCB944F1A');
        $this->addSql('ALTER TABLE reviews DROP FOREIGN KEY FK_6970EB0F591CC992');
        $this->addSql('DROP TABLE reviews');
        $this->addSql('ALTER TABLE courses DROP rating_average, DROP rating_count, DROP enrollment_count');
    }
}
