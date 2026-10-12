<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261009111755 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pages : clé système (accueil) ; blocs globaux réutilisables.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE global_block (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(120) NOT NULL,
              block JSON NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              created_by VARCHAR(180) DEFAULT NULL,
              updated_by VARCHAR(180) DEFAULT NULL,
              UNIQUE INDEX UNIQ_C71EED615E237E06 (name),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE page ADD system_key VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_140AB62047280172 ON page (system_key)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE global_block');
        $this->addSql('DROP INDEX UNIQ_140AB62047280172 ON page');
        $this->addSql('ALTER TABLE page DROP system_key');
    }
}
