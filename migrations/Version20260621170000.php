<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add users.last_otp_at to track the last successful login OTP, used to skip the
 * OTP step within the trust window.
 */
final class Version20260621170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users.last_otp_at for the login OTP trust window.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD last_otp_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP last_otp_at');
    }
}
