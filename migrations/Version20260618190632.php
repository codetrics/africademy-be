<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260618190632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE payment_methods (id INT AUTO_INCREMENT NOT NULL, token LONGTEXT NOT NULL, brand VARCHAR(40) NOT NULL, last4 VARCHAR(4) NOT NULL, exp_month VARCHAR(2) DEFAULT NULL, exp_year VARCHAR(4) DEFAULT NULL, is_default TINYINT DEFAULT 0 NOT NULL, status VARCHAR(255) NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_4FABF983B5B48B91 (public_id), INDEX IDX_4FABF983A76ED395 (user_id), INDEX idx_payment_method_user (user_id, status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE subscription_payments (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, period_start DATETIME NOT NULL, period_end DATETIME NOT NULL, attempted_at DATETIME NOT NULL, gateway_response VARCHAR(255) DEFAULT NULL, pf_payment_id VARCHAR(100) DEFAULT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, amount_amount_cents INT NOT NULL, amount_currency VARCHAR(3) NOT NULL, subscription_id INT NOT NULL, UNIQUE INDEX UNIQ_27CC41EB5B48B91 (public_id), INDEX IDX_27CC41E9A1887DC (subscription_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE subscription_plans (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, billing_interval VARCHAR(255) NOT NULL, active TINYINT DEFAULT 1 NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, price_amount_cents INT NOT NULL, price_currency VARCHAR(3) NOT NULL, UNIQUE INDEX UNIQ_CF5F99A2989D9B62 (slug), UNIQUE INDEX UNIQ_CF5F99A2B5B48B91 (public_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE subscriptions (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, current_period_start DATETIME NOT NULL, current_period_end DATETIME NOT NULL, cancel_at_period_end TINYINT DEFAULT 0 NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, plan_id INT NOT NULL, payment_method_id INT NOT NULL, UNIQUE INDEX UNIQ_4778A01B5B48B91 (public_id), INDEX IDX_4778A01A76ED395 (user_id), INDEX IDX_4778A01E899029B (plan_id), INDEX IDX_4778A015AA1164F (payment_method_id), INDEX idx_subscription_user_status (user_id, status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE payment_methods ADD CONSTRAINT FK_4FABF983A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subscription_payments ADD CONSTRAINT FK_27CC41E9A1887DC FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A01A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A01E899029B FOREIGN KEY (plan_id) REFERENCES subscription_plans (id)');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A015AA1164F FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payment_methods DROP FOREIGN KEY FK_4FABF983A76ED395');
        $this->addSql('ALTER TABLE subscription_payments DROP FOREIGN KEY FK_27CC41E9A1887DC');
        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY FK_4778A01A76ED395');
        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY FK_4778A01E899029B');
        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY FK_4778A015AA1164F');
        $this->addSql('DROP TABLE payment_methods');
        $this->addSql('DROP TABLE subscription_payments');
        $this->addSql('DROP TABLE subscription_plans');
        $this->addSql('DROP TABLE subscriptions');
    }
}
