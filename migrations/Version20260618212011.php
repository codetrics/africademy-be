<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260618212011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE quiz_attempts (id INT AUTO_INCREMENT NOT NULL, score_percent INT NOT NULL, passed TINYINT NOT NULL, answers JSON NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, quiz_id INT NOT NULL, student_id INT NOT NULL, UNIQUE INDEX UNIQ_69031E21B5B48B91 (public_id), INDEX IDX_69031E21853CD175 (quiz_id), INDEX IDX_69031E21CB944F1A (student_id), INDEX idx_attempt_quiz_student (quiz_id, student_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE quiz_choices (id INT AUTO_INCREMENT NOT NULL, text VARCHAR(500) NOT NULL, is_correct TINYINT DEFAULT 0 NOT NULL, position INT NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, question_id INT NOT NULL, UNIQUE INDEX UNIQ_17943B99B5B48B91 (public_id), INDEX IDX_17943B991E27F6BF (question_id), INDEX idx_choice_question_position (question_id, position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE quiz_questions (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, type VARCHAR(255) NOT NULL, position INT NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, quiz_id INT NOT NULL, UNIQUE INDEX UNIQ_8CBC2533B5B48B91 (public_id), INDEX IDX_8CBC2533853CD175 (quiz_id), INDEX idx_question_quiz_position (quiz_id, position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE quizzes (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, pass_threshold_percent INT DEFAULT 70 NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, course_id INT NOT NULL, UNIQUE INDEX UNIQ_94DC9FB5B5B48B91 (public_id), UNIQUE INDEX UNIQ_94DC9FB5591CC992 (course_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE quiz_attempts ADD CONSTRAINT FK_69031E21853CD175 FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempts ADD CONSTRAINT FK_69031E21CB944F1A FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_choices ADD CONSTRAINT FK_17943B991E27F6BF FOREIGN KEY (question_id) REFERENCES quiz_questions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_questions ADD CONSTRAINT FK_8CBC2533853CD175 FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quizzes ADD CONSTRAINT FK_94DC9FB5591CC992 FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE courses CHANGE rating_average rating_average DOUBLE PRECISION DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE quiz_attempts DROP FOREIGN KEY FK_69031E21853CD175');
        $this->addSql('ALTER TABLE quiz_attempts DROP FOREIGN KEY FK_69031E21CB944F1A');
        $this->addSql('ALTER TABLE quiz_choices DROP FOREIGN KEY FK_17943B991E27F6BF');
        $this->addSql('ALTER TABLE quiz_questions DROP FOREIGN KEY FK_8CBC2533853CD175');
        $this->addSql('ALTER TABLE quizzes DROP FOREIGN KEY FK_94DC9FB5591CC992');
        $this->addSql('DROP TABLE quiz_attempts');
        $this->addSql('DROP TABLE quiz_choices');
        $this->addSql('DROP TABLE quiz_questions');
        $this->addSql('DROP TABLE quizzes');
        $this->addSql('ALTER TABLE courses CHANGE rating_average rating_average DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
    }
}
