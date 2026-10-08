<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008102438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chapter first-publication date and summary';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chapter ADD published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE chapter ADD summary TEXT DEFAULT NULL');
        // No earlier record of publication: already published chapters date from this migration.
        $this->addSql('UPDATE chapter SET published_at = CURRENT_TIMESTAMP WHERE published = true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chapter DROP published_at');
        $this->addSql('ALTER TABLE chapter DROP summary');
    }
}
