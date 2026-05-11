<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260511181725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE operation (id INT AUTO_INCREMENT NOT NULL, number INT NOT NULL, status VARCHAR(255) NOT NULL, document_date DATETIME NOT NULL, confirmed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, full_number VARCHAR(255) NOT NULL, confirmed_by_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_1981A66DC5A43A6 (full_number), INDEX IDX_1981A66D6F45385D (confirmed_by_id), INDEX IDX_1981A66DB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE operation_line (id INT AUTO_INCREMENT NOT NULL, quantity NUMERIC(10, 3) NOT NULL, unit_price NUMERIC(10, 2) DEFAULT NULL, operation_id INT NOT NULL, product_id INT NOT NULL, location_from_id INT DEFAULT NULL, location_to_id INT DEFAULT NULL, INDEX IDX_FE64E96744AC3583 (operation_id), INDEX IDX_FE64E9674584665A (product_id), INDEX IDX_FE64E967968BCAAF (location_from_id), INDEX IDX_FE64E96746690F40 (location_to_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE receipt (supplier VARCHAR(255) DEFAULT NULL, invoice_number VARCHAR(255) DEFAULT NULL, delivery_date DATETIME DEFAULT NULL, transport VARCHAR(255) DEFAULT NULL, id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `release` (recipient VARCHAR(255) DEFAULT NULL, customer_order_number VARCHAR(255) DEFAULT NULL, release_date DATETIME DEFAULT NULL, release_method VARCHAR(255) DEFAULT NULL, id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE relocation (reason VARCHAR(255) DEFAULT NULL, id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE operation ADD CONSTRAINT FK_1981A66D6F45385D FOREIGN KEY (confirmed_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE operation ADD CONSTRAINT FK_1981A66DB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE operation_line ADD CONSTRAINT FK_FE64E96744AC3583 FOREIGN KEY (operation_id) REFERENCES operation (id)');
        $this->addSql('ALTER TABLE operation_line ADD CONSTRAINT FK_FE64E9674584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE operation_line ADD CONSTRAINT FK_FE64E967968BCAAF FOREIGN KEY (location_from_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE operation_line ADD CONSTRAINT FK_FE64E96746690F40 FOREIGN KEY (location_to_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE receipt ADD CONSTRAINT FK_5399B645BF396750 FOREIGN KEY (id) REFERENCES operation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `release` ADD CONSTRAINT FK_9E47031DBF396750 FOREIGN KEY (id) REFERENCES operation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE relocation ADD CONSTRAINT FK_3C7EAF9ABF396750 FOREIGN KEY (id) REFERENCES operation (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE operation DROP FOREIGN KEY FK_1981A66D6F45385D');
        $this->addSql('ALTER TABLE operation DROP FOREIGN KEY FK_1981A66DB03A8386');
        $this->addSql('ALTER TABLE operation_line DROP FOREIGN KEY FK_FE64E96744AC3583');
        $this->addSql('ALTER TABLE operation_line DROP FOREIGN KEY FK_FE64E9674584665A');
        $this->addSql('ALTER TABLE operation_line DROP FOREIGN KEY FK_FE64E967968BCAAF');
        $this->addSql('ALTER TABLE operation_line DROP FOREIGN KEY FK_FE64E96746690F40');
        $this->addSql('ALTER TABLE receipt DROP FOREIGN KEY FK_5399B645BF396750');
        $this->addSql('ALTER TABLE `release` DROP FOREIGN KEY FK_9E47031DBF396750');
        $this->addSql('ALTER TABLE relocation DROP FOREIGN KEY FK_3C7EAF9ABF396750');
        $this->addSql('DROP TABLE operation');
        $this->addSql('DROP TABLE operation_line');
        $this->addSql('DROP TABLE receipt');
        $this->addSql('DROP TABLE `release`');
        $this->addSql('DROP TABLE relocation');
    }
}
