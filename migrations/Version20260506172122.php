<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260506172122 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_5E9E89CB77153098 ON location');
        $this->addSql('DROP INDEX UNIQ_D34A04AD67B1C660 ON product');
        $this->addSql('DROP INDEX UNIQ_D34A04ADF9038C4 ON product');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5E9E89CB77153098 ON location (code)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D34A04AD67B1C660 ON product (ean)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D34A04ADF9038C4 ON product (sku)');
    }
}
