<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Éditeur de page : brouillon des blocs (enregistrement automatique) et numéro de révision.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page ADD draft_blocks JSON DEFAULT NULL, ADD draft_revision INT DEFAULT 0 NOT NULL, ADD draft_saved_at DATETIME DEFAULT NULL, ADD draft_saved_by VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page DROP draft_blocks, DROP draft_revision, DROP draft_saved_at, DROP draft_saved_by');
    }
}
