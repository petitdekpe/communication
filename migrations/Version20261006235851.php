<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006235851 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Démarches : étapes, pièces requises, contact, base légale, formulaire interne ; demandes en ligne et pièces jointes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE service_request (id INT AUTO_INCREMENT NOT NULL, tracking_number VARCHAR(24) NOT NULL, email VARCHAR(180) NOT NULL, locale VARCHAR(2) NOT NULL, status VARCHAR(16) NOT NULL, payload JSON NOT NULL, consented_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, service_id INT NOT NULL, UNIQUE INDEX uniq_service_request_tracking (tracking_number), INDEX IDX_F413DD03ED5CA9E6 (service_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service_request_attachment (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(127) NOT NULL, size INT NOT NULL, request_id INT NOT NULL, UNIQUE INDEX UNIQ_C7752F3FB548B0F (path), INDEX IDX_C7752F3F427EB8A5 (request_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD03ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE service_request_attachment ADD CONSTRAINT FK_C7752F3F427EB8A5 FOREIGN KEY (request_id) REFERENCES service_request (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service ADD form_key VARCHAR(40) DEFAULT NULL, ADD legal_basis_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD CONSTRAINT FK_E19D9AD289FE313C FOREIGN KEY (legal_basis_id) REFERENCES official_document (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E19D9AD289FE313C ON service (legal_basis_id)');
        $this->addSql('ALTER TABLE service_translation ADD steps JSON NOT NULL DEFAULT \'[]\', ADD required_documents JSON NOT NULL DEFAULT \'[]\', ADD contact LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE service_translation ALTER COLUMN steps DROP DEFAULT, ALTER COLUMN required_documents DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_request DROP FOREIGN KEY FK_F413DD03ED5CA9E6');
        $this->addSql('ALTER TABLE service_request_attachment DROP FOREIGN KEY FK_C7752F3F427EB8A5');
        $this->addSql('DROP TABLE service_request');
        $this->addSql('DROP TABLE service_request_attachment');
        $this->addSql('ALTER TABLE service DROP FOREIGN KEY FK_E19D9AD289FE313C');
        $this->addSql('DROP INDEX IDX_E19D9AD289FE313C ON service');
        $this->addSql('ALTER TABLE service DROP form_key, DROP legal_basis_id');
        $this->addSql('ALTER TABLE service_translation DROP steps, DROP required_documents, DROP contact');
    }
}
