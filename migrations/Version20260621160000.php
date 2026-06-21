<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename the stored ROLE_TEACHER role to ROLE_FACILITATOR.
 *
 * The "teacher" concept was renamed to "facilitator" across the application,
 * including the role string. Existing users carry ROLE_TEACHER in their JSON
 * roles column and must be rewritten to keep their access.
 */
final class Version20260621160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename stored ROLE_TEACHER to ROLE_FACILITATOR on existing users.';
    }

    public function up(Schema $schema): void
    {
        // id > 0 references the primary key so the statement is accepted under
        // MySQL safe-update mode (sql_safe_updates).
        $this->addSql("UPDATE users SET roles = REPLACE(roles, 'ROLE_TEACHER', 'ROLE_FACILITATOR') WHERE roles LIKE '%ROLE_TEACHER%' AND id > 0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE users SET roles = REPLACE(roles, 'ROLE_FACILITATOR', 'ROLE_TEACHER') WHERE roles LIKE '%ROLE_FACILITATOR%' AND id > 0");
    }
}
