<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260622170828 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store signature-valid PayFast ITN webhook events for audit';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE payfast_webhook_event (id INT AUTO_INCREMENT NOT NULL, public_id BINARY(16) NOT NULL, received_at DATETIME NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, m_payment_id VARCHAR(26) DEFAULT NULL, pf_payment_id VARCHAR(255) DEFAULT NULL, payment_status VARCHAR(50) DEFAULT NULL, amount_gross_cents INT DEFAULT NULL, outcome VARCHAR(255) NOT NULL, payload JSON NOT NULL, UNIQUE INDEX UNIQ_2DC8CE75B5B48B91 (public_id), INDEX idx_payfast_webhook_received_at (received_at), INDEX idx_payfast_webhook_m_payment_id (m_payment_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE payfast_webhook_event');
    }
}
