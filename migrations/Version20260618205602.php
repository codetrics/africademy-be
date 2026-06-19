<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260618205602 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE certificates (id INT AUTO_INCREMENT NOT NULL, credential_id VARCHAR(32) NOT NULL, student_name VARCHAR(255) NOT NULL, course_title VARCHAR(200) NOT NULL, instructor_name VARCHAR(255) NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, student_id INT NOT NULL, course_id INT NOT NULL, enrollment_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_8D26FB5F2558A7A5 (credential_id), UNIQUE INDEX UNIQ_8D26FB5FB5B48B91 (public_id), INDEX IDX_8D26FB5FCB944F1A (student_id), INDEX IDX_8D26FB5F591CC992 (course_id), UNIQUE INDEX uniq_certificate_enrollment (enrollment_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE certificates ADD CONSTRAINT FK_8D26FB5FCB944F1A FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE certificates ADD CONSTRAINT FK_8D26FB5F591CC992 FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE certificates ADD CONSTRAINT FK_8D26FB5F8F7DB25B FOREIGN KEY (enrollment_id) REFERENCES enrollments (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE courses ADD certificate_enabled TINYINT DEFAULT 0 NOT NULL, ADD requires_quiz TINYINT DEFAULT 0 NOT NULL, CHANGE rating_average rating_average DOUBLE PRECISION DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE certificates DROP FOREIGN KEY FK_8D26FB5FCB944F1A');
        $this->addSql('ALTER TABLE certificates DROP FOREIGN KEY FK_8D26FB5F591CC992');
        $this->addSql('ALTER TABLE certificates DROP FOREIGN KEY FK_8D26FB5F8F7DB25B');
        $this->addSql('DROP TABLE certificates');
        $this->addSql('ALTER TABLE courses DROP certificate_enabled, DROP requires_quiz, CHANGE rating_average rating_average DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
    }
}
