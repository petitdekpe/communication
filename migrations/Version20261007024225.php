<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007024225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE content_version (id INT AUTO_INCREMENT NOT NULL, subject_class VARCHAR(255) NOT NULL, subject_id INT NOT NULL, number INT NOT NULL, snapshot JSON NOT NULL, author VARCHAR(180) NOT NULL, created_at DATETIME NOT NULL, restored_from INT DEFAULT NULL, INDEX idx_content_version_subject (subject_class, subject_id), UNIQUE INDEX uniq_content_version_number (subject_class, subject_id, number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE edit_lock (id INT AUTO_INCREMENT NOT NULL, subject_class VARCHAR(255) NOT NULL, subject_id INT NOT NULL, holder VARCHAR(180) NOT NULL, holder_name VARCHAR(180) NOT NULL, last_activity_at DATETIME NOT NULL, UNIQUE INDEX uniq_edit_lock_subject (subject_class, subject_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE publication_history (id INT AUTO_INCREMENT NOT NULL, subject_class VARCHAR(255) NOT NULL, subject_id INT NOT NULL, transition VARCHAR(24) NOT NULL, from_status VARCHAR(16) DEFAULT NULL, to_status VARCHAR(16) DEFAULT NULL, actor VARCHAR(180) NOT NULL, actor_name VARCHAR(180) DEFAULT NULL, reason LONGTEXT DEFAULT NULL, occurred_at DATETIME NOT NULL, INDEX idx_publication_history_subject (subject_class, subject_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE article ADD scheduled_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE consultation ADD scheduled_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD scheduled_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE key_figure ADD scheduled_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE media_gallery ADD scheduled_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE official_document ADD scheduled_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE page ADD scheduled_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD scheduled_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE video ADD scheduled_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE content_version');
        $this->addSql('DROP TABLE edit_lock');
        $this->addSql('DROP TABLE publication_history');
        $this->addSql('ALTER TABLE article DROP scheduled_at');
        $this->addSql('ALTER TABLE consultation DROP scheduled_at');
        $this->addSql('ALTER TABLE event DROP scheduled_at');
        $this->addSql('ALTER TABLE key_figure DROP scheduled_at');
        $this->addSql('ALTER TABLE media_gallery DROP scheduled_at');
        $this->addSql('ALTER TABLE official_document DROP scheduled_at');
        $this->addSql('ALTER TABLE page DROP scheduled_at');
        $this->addSql('ALTER TABLE service DROP scheduled_at');
        $this->addSql('ALTER TABLE video DROP scheduled_at');
    }
}
