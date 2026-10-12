<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Commentaire interne des demandes ; lien interne générique des entrées de menu (type + identifiant).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_request ADD internal_note LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE menu_item DROP FOREIGN KEY FK_D754D550C4663E4');
        $this->addSql('DROP INDEX IDX_D754D550C4663E4 ON menu_item');
        $this->addSql('ALTER TABLE menu_item DROP page_id, ADD target_type VARCHAR(16) DEFAULT NULL, ADD target_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_request DROP internal_note');
        $this->addSql('ALTER TABLE menu_item DROP target_type, DROP target_id, ADD page_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550C4663E4 FOREIGN KEY (page_id) REFERENCES page (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_D754D550C4663E4 ON menu_item (page_id)');
    }
}
