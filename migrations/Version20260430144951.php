<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260430144951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, sku VARCHAR(50) NOT NULL, ean VARCHAR(13) DEFAULT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, unit VARCHAR(20) NOT NULL, unit_price DOUBLE PRECISION NOT NULL, min_stock_level DOUBLE PRECISION DEFAULT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, created_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_D34A04ADF9038C4 (sku), UNIQUE INDEX UNIQ_D34A04AD67B1C660 (ean), INDEX IDX_D34A04ADB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADB03A8386');
        $this->addSql('DROP TABLE product');
    }
}
