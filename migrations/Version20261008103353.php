<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008103353 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Site settings and chosen work covers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE site_settings (id INT NOT NULL, bio TEXT DEFAULT NULL, links TEXT DEFAULT NULL, accent VARCHAR(7) NOT NULL, logo_version INT DEFAULT NULL, name VARCHAR(100) NOT NULL, tagline VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE work ADD cover_version INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE site_settings');
        $this->addSql('ALTER TABLE work DROP cover_version');
    }
}
