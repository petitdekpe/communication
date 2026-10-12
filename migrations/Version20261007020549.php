<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007020549 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Administration : comptes, réinitialisation de mot de passe, journal d\'audit.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, name VARCHAR(150) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, active TINYINT DEFAULT 1 NOT NULL, last_login_at DATETIME DEFAULT NULL, totp_secret VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_admin_user_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE audit_log (id INT AUTO_INCREMENT NOT NULL, occurred_at DATETIME NOT NULL, actor VARCHAR(180) NOT NULL, action VARCHAR(24) NOT NULL, entity_class VARCHAR(80) NOT NULL, entity_id INT DEFAULT NULL, label VARCHAR(255) DEFAULT NULL, changes JSON NOT NULL, INDEX idx_audit_entity (entity_class, entity_id), INDEX idx_audit_occurred (occurred_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES admin_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('DROP TABLE admin_user');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE reset_password_request');
    }
}
