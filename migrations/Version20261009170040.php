<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261009170040 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retire le page builder : blocs, brouillons, modèles de page et blocs globaux.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("DELETE FROM page WHERE system_key = 'home'");
        $this->addSql('DROP TABLE global_block');
        $this->addSql('ALTER TABLE page DROP template, DROP blocks, DROP draft_blocks, DROP draft_revision, DROP draft_saved_at, DROP draft_saved_by');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE global_block (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, block JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, updated_by VARCHAR(180) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, UNIQUE INDEX UNIQ_C71EED615E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE page ADD template VARCHAR(16) DEFAULT \'standard\' NOT NULL, ADD blocks JSON NOT NULL, ADD draft_blocks JSON DEFAULT NULL, ADD draft_revision INT DEFAULT 0 NOT NULL, ADD draft_saved_at DATETIME DEFAULT NULL, ADD draft_saved_by VARCHAR(180) DEFAULT NULL');
    }
}
