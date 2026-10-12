<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007010045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vidéos, contributions aux consultations (documents, résultats), abonnés à la lettre d\'information.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE consultation_document (consultation_id INT NOT NULL, official_document_id INT NOT NULL, INDEX IDX_96280AD362FF6CDF (consultation_id), INDEX IDX_96280AD316F3473D (official_document_id), PRIMARY KEY (consultation_id, official_document_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE consultation_contribution (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, organization VARCHAR(200) DEFAULT NULL, text LONGTEXT NOT NULL, consented_at DATETIME NOT NULL, file_path VARCHAR(255) DEFAULT NULL, file_original_name VARCHAR(255) DEFAULT NULL, file_mime_type VARCHAR(127) DEFAULT NULL, file_size INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, consultation_id INT NOT NULL, INDEX IDX_9B3FE7162FF6CDF (consultation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE newsletter_subscriber (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, locale VARCHAR(2) NOT NULL, status VARCHAR(16) NOT NULL, confirm_token VARCHAR(64) NOT NULL, unsubscribe_token VARCHAR(64) NOT NULL, subscribed_at DATETIME NOT NULL, confirmed_at DATETIME DEFAULT NULL, unsubscribed_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_newsletter_email (email), UNIQUE INDEX uniq_newsletter_confirm_token (confirm_token), UNIQUE INDEX uniq_newsletter_unsubscribe_token (unsubscribe_token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE video (id INT AUTO_INCREMENT NOT NULL, youtube_id VARCHAR(11) NOT NULL, position INT NOT NULL, status VARCHAR(16) NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE video_translation (title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, translatable_id INT NOT NULL, UNIQUE INDEX uniq_video_translation_locale (translatable_id, locale), INDEX IDX_96EBF4CE2C2AC5D3 (translatable_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE consultation_document ADD CONSTRAINT FK_96280AD362FF6CDF FOREIGN KEY (consultation_id) REFERENCES consultation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consultation_document ADD CONSTRAINT FK_96280AD316F3473D FOREIGN KEY (official_document_id) REFERENCES official_document (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consultation_contribution ADD CONSTRAINT FK_9B3FE7162FF6CDF FOREIGN KEY (consultation_id) REFERENCES consultation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_translation ADD CONSTRAINT FK_96EBF4CE2C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES video (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consultation ADD results_published TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE consultation_translation ADD results LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation_document DROP FOREIGN KEY FK_96280AD362FF6CDF');
        $this->addSql('ALTER TABLE consultation_document DROP FOREIGN KEY FK_96280AD316F3473D');
        $this->addSql('ALTER TABLE consultation_contribution DROP FOREIGN KEY FK_9B3FE7162FF6CDF');
        $this->addSql('ALTER TABLE video_translation DROP FOREIGN KEY FK_96EBF4CE2C2AC5D3');
        $this->addSql('DROP TABLE consultation_document');
        $this->addSql('DROP TABLE consultation_contribution');
        $this->addSql('DROP TABLE newsletter_subscriber');
        $this->addSql('DROP TABLE video');
        $this->addSql('DROP TABLE video_translation');
        $this->addSql('ALTER TABLE consultation DROP results_published');
        $this->addSql('ALTER TABLE consultation_translation DROP results');
    }
}
