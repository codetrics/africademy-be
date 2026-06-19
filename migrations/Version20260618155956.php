<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260618155956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE enrollments (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, payment_status VARCHAR(255) NOT NULL, completed_at DATETIME DEFAULT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, student_id INT NOT NULL, course_id INT NOT NULL, UNIQUE INDEX UNIQ_CCD8C132B5B48B91 (public_id), INDEX IDX_CCD8C132CB944F1A (student_id), INDEX IDX_CCD8C132591CC992 (course_id), UNIQUE INDEX uniq_enrollment_student_course (student_id, course_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE lesson_progress (id INT AUTO_INCREMENT NOT NULL, completed_at DATETIME NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, enrollment_id INT NOT NULL, lesson_id INT NOT NULL, UNIQUE INDEX UNIQ_6A46B85FB5B48B91 (public_id), INDEX IDX_6A46B85F8F7DB25B (enrollment_id), INDEX IDX_6A46B85FCDF80196 (lesson_id), UNIQUE INDEX uniq_progress_enrollment_lesson (enrollment_id, lesson_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE enrollments ADD CONSTRAINT FK_CCD8C132CB944F1A FOREIGN KEY (student_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE enrollments ADD CONSTRAINT FK_CCD8C132591CC992 FOREIGN KEY (course_id) REFERENCES courses (id)');
        $this->addSql('ALTER TABLE lesson_progress ADD CONSTRAINT FK_6A46B85F8F7DB25B FOREIGN KEY (enrollment_id) REFERENCES enrollments (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lesson_progress ADD CONSTRAINT FK_6A46B85FCDF80196 FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE enrollments DROP FOREIGN KEY FK_CCD8C132CB944F1A');
        $this->addSql('ALTER TABLE enrollments DROP FOREIGN KEY FK_CCD8C132591CC992');
        $this->addSql('ALTER TABLE lesson_progress DROP FOREIGN KEY FK_6A46B85F8F7DB25B');
        $this->addSql('ALTER TABLE lesson_progress DROP FOREIGN KEY FK_6A46B85FCDF80196');
        $this->addSql('DROP TABLE enrollments');
        $this->addSql('DROP TABLE lesson_progress');
    }
}
