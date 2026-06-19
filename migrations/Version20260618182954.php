<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260618182954 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE entitlements (id INT AUTO_INCREMENT NOT NULL, source VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, expires_at DATETIME DEFAULT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, course_id INT NOT NULL, UNIQUE INDEX UNIQ_579D9BD5B5B48B91 (public_id), INDEX IDX_579D9BD5A76ED395 (user_id), INDEX IDX_579D9BD5591CC992 (course_id), UNIQUE INDEX uniq_entitlement_user_course (user_id, course_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE orders (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, pf_payment_id VARCHAR(100) DEFAULT NULL, paid_at DATETIME DEFAULT NULL, refunded_at DATETIME DEFAULT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, amount_amount_cents INT NOT NULL, amount_currency VARCHAR(3) NOT NULL, user_id INT NOT NULL, course_id INT NOT NULL, UNIQUE INDEX UNIQ_E52FFDEEB5B48B91 (public_id), INDEX IDX_E52FFDEEA76ED395 (user_id), INDEX IDX_E52FFDEE591CC992 (course_id), INDEX idx_order_status (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE refund_requests (id INT AUTO_INCREMENT NOT NULL, reason LONGTEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, resolved_at DATETIME DEFAULT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, order_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_A6AE452B5B48B91 (public_id), INDEX IDX_A6AE452A76ED395 (user_id), UNIQUE INDEX uniq_refund_order (order_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE entitlements ADD CONSTRAINT FK_579D9BD5A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE entitlements ADD CONSTRAINT FK_579D9BD5591CC992 FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE591CC992 FOREIGN KEY (course_id) REFERENCES courses (id)');
        $this->addSql('ALTER TABLE refund_requests ADD CONSTRAINT FK_A6AE4528D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE refund_requests ADD CONSTRAINT FK_A6AE452A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE courses ADD is_free TINYINT DEFAULT 0 NOT NULL, ADD is_purchasable TINYINT DEFAULT 0 NOT NULL, ADD included_in_subscription TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE enrollments DROP payment_status');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entitlements DROP FOREIGN KEY FK_579D9BD5A76ED395');
        $this->addSql('ALTER TABLE entitlements DROP FOREIGN KEY FK_579D9BD5591CC992');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEEA76ED395');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE591CC992');
        $this->addSql('ALTER TABLE refund_requests DROP FOREIGN KEY FK_A6AE4528D9F6D38');
        $this->addSql('ALTER TABLE refund_requests DROP FOREIGN KEY FK_A6AE452A76ED395');
        $this->addSql('DROP TABLE entitlements');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE refund_requests');
        $this->addSql('ALTER TABLE courses DROP is_free, DROP is_purchasable, DROP included_in_subscription');
        $this->addSql('ALTER TABLE enrollments ADD payment_status VARCHAR(255) NOT NULL');
    }
}
