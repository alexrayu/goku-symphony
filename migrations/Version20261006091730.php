<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261006091730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tile order of scrambled page derivatives';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page ADD tile_order TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page DROP tile_order');
    }
}
