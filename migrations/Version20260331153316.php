<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona colunas image_url, mercadolivre_id e score à tabela product';
    }

    public function up(Schema $schema): void
    {
        // Adiciona apenas se não existir (seguro para reaplicar)
        $this->addSql('ALTER TABLE product ADD COLUMN IF NOT EXISTS image_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD COLUMN IF NOT EXISTS mercadolivre_id VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD COLUMN IF NOT EXISTS score NUMERIC(4,1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP COLUMN IF EXISTS image_url');
        $this->addSql('ALTER TABLE product DROP COLUMN IF EXISTS mercadolivre_id');
        $this->addSql('ALTER TABLE product DROP COLUMN IF EXISTS score');
    }
}
