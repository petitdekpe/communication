<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Page builder : modèle et blocs JSON des pages, titre SEO par langue.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE page ADD template VARCHAR(16) DEFAULT 'standard' NOT NULL, ADD blocks JSON NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE page ALTER COLUMN blocks DROP DEFAULT');
        $this->addSql('ALTER TABLE page_translation ADD meta_title VARCHAR(70) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page DROP template, DROP blocks');
        $this->addSql('ALTER TABLE page_translation DROP meta_title');
    }
}
