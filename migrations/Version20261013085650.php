<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261013085650 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression de la gestion de menu : le menu du site est défini dans MenuProvider.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE menu_item DROP FOREIGN KEY `FK_D754D550727ACA70`');
        $this->addSql('ALTER TABLE menu_item_translation DROP FOREIGN KEY `FK_683EE3A62C2AC5D3`');
        $this->addSql('DROP TABLE menu_item');
        $this->addSql('DROP TABLE menu_item_translation');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE menu_item (id INT AUTO_INCREMENT NOT NULL, menu VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, external_url VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, position INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, updated_by VARCHAR(180) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, parent_id INT DEFAULT NULL, target_type VARCHAR(16) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, target_id INT DEFAULT NULL, INDEX IDX_D754D550727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE menu_item_translation (label VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, path VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, translatable_id INT NOT NULL, INDEX IDX_683EE3A62C2AC5D3 (translatable_id), UNIQUE INDEX uniq_menu_item_translation_locale (translatable_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT `FK_D754D550727ACA70` FOREIGN KEY (parent_id) REFERENCES menu_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_item_translation ADD CONSTRAINT `FK_683EE3A62C2AC5D3` FOREIGN KEY (translatable_id) REFERENCES menu_item (id) ON DELETE CASCADE');
    }
}
