<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260515093105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE adjustment (stocktaking_id INT NOT NULL, id INT NOT NULL, UNIQUE INDEX UNIQ_89F9781689B86B49 (stocktaking_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stocktaking (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, created_by_id INT DEFAULT NULL, completed_by_id INT DEFAULT NULL, INDEX IDX_1082729BB03A8386 (created_by_id), INDEX IDX_1082729B85ECDE76 (completed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stocktaking_line (id INT AUTO_INCREMENT NOT NULL, expected_quantity NUMERIC(10, 3) NOT NULL, counted_quantity NUMERIC(10, 3) DEFAULT NULL, saved_at DATETIME DEFAULT NULL, stocktaking_id INT NOT NULL, product_id INT NOT NULL, location_id INT NOT NULL, saved_by_id INT DEFAULT NULL, INDEX IDX_43A2674489B86B49 (stocktaking_id), INDEX IDX_43A267444584665A (product_id), INDEX IDX_43A2674464D218E (location_id), INDEX IDX_43A26744C734FB1D (saved_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE adjustment ADD CONSTRAINT FK_89F9781689B86B49 FOREIGN KEY (stocktaking_id) REFERENCES stocktaking (id)');
        $this->addSql('ALTER TABLE adjustment ADD CONSTRAINT FK_89F97816BF396750 FOREIGN KEY (id) REFERENCES operation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stocktaking ADD CONSTRAINT FK_1082729BB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE stocktaking ADD CONSTRAINT FK_1082729B85ECDE76 FOREIGN KEY (completed_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE stocktaking_line ADD CONSTRAINT FK_43A2674489B86B49 FOREIGN KEY (stocktaking_id) REFERENCES stocktaking (id)');
        $this->addSql('ALTER TABLE stocktaking_line ADD CONSTRAINT FK_43A267444584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE stocktaking_line ADD CONSTRAINT FK_43A2674464D218E FOREIGN KEY (location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE stocktaking_line ADD CONSTRAINT FK_43A26744C734FB1D FOREIGN KEY (saved_by_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE adjustment DROP FOREIGN KEY FK_89F9781689B86B49');
        $this->addSql('ALTER TABLE adjustment DROP FOREIGN KEY FK_89F97816BF396750');
        $this->addSql('ALTER TABLE stocktaking DROP FOREIGN KEY FK_1082729BB03A8386');
        $this->addSql('ALTER TABLE stocktaking DROP FOREIGN KEY FK_1082729B85ECDE76');
        $this->addSql('ALTER TABLE stocktaking_line DROP FOREIGN KEY FK_43A2674489B86B49');
        $this->addSql('ALTER TABLE stocktaking_line DROP FOREIGN KEY FK_43A267444584665A');
        $this->addSql('ALTER TABLE stocktaking_line DROP FOREIGN KEY FK_43A2674464D218E');
        $this->addSql('ALTER TABLE stocktaking_line DROP FOREIGN KEY FK_43A26744C734FB1D');
        $this->addSql('DROP TABLE adjustment');
        $this->addSql('DROP TABLE stocktaking');
        $this->addSql('DROP TABLE stocktaking_line');
    }
}
