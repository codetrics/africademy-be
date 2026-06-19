<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260618200039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE coupon_redemptions (id INT AUTO_INCREMENT NOT NULL, amount_discounted_cents INT NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, coupon_id INT NOT NULL, user_id INT NOT NULL, order_id INT DEFAULT NULL, subscription_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_CE234CEAB5B48B91 (public_id), INDEX IDX_CE234CEA66C5951B (coupon_id), INDEX IDX_CE234CEAA76ED395 (user_id), INDEX IDX_CE234CEA8D9F6D38 (order_id), INDEX IDX_CE234CEA9A1887DC (subscription_id), UNIQUE INDEX uniq_coupon_user (coupon_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE coupons (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(60) NOT NULL, discount_type VARCHAR(255) NOT NULL, discount_value INT NOT NULL, max_redemptions INT DEFAULT NULL, redemption_count INT DEFAULT 0 NOT NULL, min_amount_cents INT DEFAULT NULL, expires_at DATETIME DEFAULT NULL, active TINYINT DEFAULT 1 NOT NULL, public_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_F564111877153098 (code), UNIQUE INDEX UNIQ_F5641118B5B48B91 (public_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE coupon_redemptions ADD CONSTRAINT FK_CE234CEA66C5951B FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE coupon_redemptions ADD CONSTRAINT FK_CE234CEAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE coupon_redemptions ADD CONSTRAINT FK_CE234CEA8D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE coupon_redemptions ADD CONSTRAINT FK_CE234CEA9A1887DC FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE orders ADD discount_amount_cents INT DEFAULT 0 NOT NULL, ADD coupon_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE66C5951B FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E52FFDEE66C5951B ON orders (coupon_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE coupon_redemptions DROP FOREIGN KEY FK_CE234CEA66C5951B');
        $this->addSql('ALTER TABLE coupon_redemptions DROP FOREIGN KEY FK_CE234CEAA76ED395');
        $this->addSql('ALTER TABLE coupon_redemptions DROP FOREIGN KEY FK_CE234CEA8D9F6D38');
        $this->addSql('ALTER TABLE coupon_redemptions DROP FOREIGN KEY FK_CE234CEA9A1887DC');
        $this->addSql('DROP TABLE coupon_redemptions');
        $this->addSql('DROP TABLE coupons');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE66C5951B');
        $this->addSql('DROP INDEX IDX_E52FFDEE66C5951B ON orders');
        $this->addSql('ALTER TABLE orders DROP discount_amount_cents, DROP coupon_id');
    }
}
