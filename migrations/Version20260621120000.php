<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Backfill ROLE_STUDENT for existing users.
 *
 * The auto-applied base role changed from ROLE_STUDENT to ROLE_USER, so existing
 * learners (who stored an empty roles array and relied on the appended default)
 * must have ROLE_STUDENT persisted explicitly to keep their access. Users that
 * already hold a stored role (teachers/admins granted via app:user:grant-role)
 * are left untouched.
 */
final class Version20260621120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill ROLE_STUDENT for users with no stored roles after ROLE_USER became the base role.';
    }

    public function up(Schema $schema): void
    {
        // id > 0 references the primary key so the statement is accepted under
        // MySQL safe-update mode (sql_safe_updates).
        $this->addSql('UPDATE users SET roles = \'["ROLE_STUDENT"]\' WHERE roles = \'[]\' AND id > 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE users SET roles = \'[]\' WHERE roles = \'["ROLE_STUDENT"]\' AND id > 0');
    }
}
