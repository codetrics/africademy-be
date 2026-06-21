<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add verification_codes.attempts to support per-code brute-force lockout.
 */
final class Version20260621180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add verification_codes.attempts for per-code attempt limiting.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE verification_codes ADD attempts INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE verification_codes DROP attempts');
    }
}
