<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261006215316 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Article : pièces jointes et galerie.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article_attachment (article_id INT NOT NULL, official_document_id INT NOT NULL, INDEX IDX_4586083A7294869C (article_id), INDEX IDX_4586083A16F3473D (official_document_id), PRIMARY KEY (article_id, official_document_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE article_attachment ADD CONSTRAINT FK_4586083A7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_attachment ADD CONSTRAINT FK_4586083A16F3473D FOREIGN KEY (official_document_id) REFERENCES official_document (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article ADD gallery_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E664E7AF8F FOREIGN KEY (gallery_id) REFERENCES media_gallery (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_23A0E664E7AF8F ON article (gallery_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article_attachment DROP FOREIGN KEY FK_4586083A7294869C');
        $this->addSql('ALTER TABLE article_attachment DROP FOREIGN KEY FK_4586083A16F3473D');
        $this->addSql('DROP TABLE article_attachment');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E664E7AF8F');
        $this->addSql('DROP INDEX IDX_23A0E664E7AF8F ON article');
        $this->addSql('ALTER TABLE article DROP gallery_id');
    }
}
