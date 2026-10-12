<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006232448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Documents : type, langue du fichier, compteur de téléchargements, index FULLTEXT ; agenda : type d\'événement.';
    }

    public function up(Schema $schema): void
    {
        // Valeurs par défaut le temps de renseigner les lignes existantes, puis retirées : le mapping n'en porte pas.
        $this->addSql('ALTER TABLE event ADD type VARCHAR(16) NOT NULL DEFAULT \'other\'');
        $this->addSql('ALTER TABLE event ALTER COLUMN type DROP DEFAULT');
        $this->addSql('ALTER TABLE official_document ADD type VARCHAR(16) NOT NULL DEFAULT \'report\', ADD file_language VARCHAR(2) NOT NULL DEFAULT \'fr\', ADD download_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE official_document ALTER COLUMN type DROP DEFAULT, ALTER COLUMN file_language DROP DEFAULT');
        $this->addSql('CREATE FULLTEXT INDEX ft_official_document_translation ON official_document_translation (title, summary)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX ft_official_document_translation ON official_document_translation');
        $this->addSql('ALTER TABLE official_document DROP type, DROP file_language, DROP download_count');
        $this->addSql('ALTER TABLE event DROP type');
    }
}
