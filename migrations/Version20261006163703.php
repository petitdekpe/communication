<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261006163703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Socle des contenus : entités traduisibles (fr/en), médias, menus, paramètres.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, featured TINYINT NOT NULL, status VARCHAR(16) NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, category_id INT DEFAULT NULL, cover_image_id INT DEFAULT NULL, INDEX IDX_23A0E6612469DE2 (category_id), INDEX IDX_23A0E66E5A0E336 (cover_image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE article_tag (article_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_919694F97294869C (article_id), INDEX IDX_919694F9BAD26311 (tag_id), PRIMARY KEY (article_id, tag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE article_translation (title VARCHAR(255) NOT NULL, excerpt LONGTEXT DEFAULT NULL, body LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, slug VARCHAR(190) NOT NULL, translatable_id INT NOT NULL, UNIQUE INDEX uniq_article_translation_locale (translatable_id, locale), UNIQUE INDEX uniq_article_translation_slug (locale, slug), INDEX IDX_2EEA2F082C2AC5D3 (translatable_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE category_translation (name VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, slug VARCHAR(190) NOT NULL, translatable_id INT NOT NULL, UNIQUE INDEX uniq_category_translation_locale (translatable_id, locale), UNIQUE INDEX uniq_category_translation_slug (locale, slug), INDEX IDX_3F207042C2AC5D3 (translatable_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE consultation (id INT AUTO_INCREMENT NOT NULL, opens_at DATETIME NOT NULL, closes_at DATETIME NOT NULL, participation_url VARCHAR(255) DEFAULT NULL, status VARCHAR(16) NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE consultation_translation (title VARCHAR(255) NOT NULL, summary LONGTEXT DEFAULT NULL, body LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, slug VARCHAR(190) NOT NULL, translatable_id INT NOT NULL, UNIQUE INDEX uniq_consultation_translation_locale (translatable_id, locale), UNIQUE INDEX uniq_consultation_translation_slug (locale, slug), INDEX IDX_518233E72C2AC5D3 (translatable_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME DEFAULT NULL, status VARCHAR(16) NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event_translation (title VARCHAR(255) NOT NULL, location VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, slug VARCHAR(190) NOT NULL, translatable_id INT NOT NULL, UNIQUE INDEX uniq_event_translation_locale (translatable_id, locale), UNIQUE INDEX uniq_event_translation_slug (locale, slug), INDEX IDX_1FE096EF2C2AC5D3 (translatable_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE key_figure (id INT AUTO_INCREMENT NOT NULL, value NUMERIC(20, 4) DEFAULT NULL, source_url VARCHAR(255) DEFAULT NULL, data_date DATE NOT NULL, position INT NOT NULL, status VARCHAR(16) NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE key_figure_translation (label VARCHAR(255) NOT NULL, unit VARCHAR(50) DEFAULT NULL, source VARCHAR(255) NOT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, translatable_id INT NOT NULL, UNIQUE INDEX uniq_key_figure_translation_locale (translatable_id, locale), INDEX IDX_2D390A002C2AC5D3 (translatable_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE media (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(127) NOT NULL, size INT NOT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, credit VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, UNIQUE INDEX UNIQ_6A2CA10CB548B0F (path), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE media_gallery (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(16) NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE media_gallery_item (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, gallery_id INT NOT NULL, media_id INT NOT NULL, UNIQUE INDEX uniq_media_gallery_item (gallery_id, media_id), INDEX IDX_E5F5A71E4E7AF8F (gallery_id), INDEX IDX_E5F5A71EEA9FDD75 (media_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE media_gallery_translation (title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, slug VARCHAR(190) NOT NULL, translatable_id INT NOT NULL, UNIQUE INDEX uniq_media_gallery_translation_locale (translatable_id, locale), UNIQUE INDEX uniq_media_gallery_translation_slug (locale, slug), INDEX IDX_992E7FC12C2AC5D3 (translatable_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE media_translation (alt VARCHAR(255) NOT NULL, caption LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, translatable_id INT NOT NULL, UNIQUE INDEX uniq_media_translation_locale (translatable_id, locale), INDEX IDX_430137FC2C2AC5D3 (translatable_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu_item (id INT AUTO_INCREMENT NOT NULL, menu VARCHAR(32) NOT NULL, external_url VARCHAR(255) DEFAULT NULL, position INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, parent_id INT DEFAULT NULL, page_id INT DEFAULT NULL, INDEX IDX_D754D550727ACA70 (parent_id), INDEX IDX_D754D550C4663E4 (page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu_item_translation (label VARCHAR(150) NOT NULL, path VARCHAR(255) DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, translatable_id INT NOT NULL, UNIQUE INDEX uniq_menu_item_translation_locale (translatable_id, locale), INDEX IDX_683EE3A62C2AC5D3 (translatable_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE official_document (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(100) DEFAULT NULL, document_date DATE DEFAULT NULL, status VARCHAR(16) NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, file_id INT DEFAULT NULL, category_id INT DEFAULT NULL, INDEX IDX_18F406CA93CB796C (file_id), INDEX IDX_18F406CA12469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE official_document_translation (title VARCHAR(255) NOT NULL, summary LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, slug VARCHAR(190) NOT NULL, translatable_id INT NOT NULL, UNIQUE INDEX uniq_official_document_translation_locale (translatable_id, locale), UNIQUE INDEX uniq_official_document_translation_slug (locale, slug), INDEX IDX_B64DC1292C2AC5D3 (translatable_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE page (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, status VARCHAR(16) NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, parent_id INT DEFAULT NULL, INDEX IDX_140AB620727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE page_translation (title VARCHAR(255) NOT NULL, body LONGTEXT DEFAULT NULL, meta_description VARCHAR(300) DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, slug VARCHAR(190) NOT NULL, translatable_id INT NOT NULL, UNIQUE INDEX uniq_page_translation_locale (translatable_id, locale), UNIQUE INDEX uniq_page_translation_slug (locale, slug), INDEX IDX_A3D51B1D2C2AC5D3 (translatable_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service (id INT AUTO_INCREMENT NOT NULL, audience VARCHAR(20) NOT NULL, online TINYINT NOT NULL, online_url VARCHAR(255) DEFAULT NULL, featured TINYINT NOT NULL, position INT NOT NULL, status VARCHAR(16) NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service_translation (title VARCHAR(255) NOT NULL, summary LONGTEXT DEFAULT NULL, body LONGTEXT DEFAULT NULL, cost VARCHAR(255) DEFAULT NULL, delay VARCHAR(255) DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, slug VARCHAR(190) NOT NULL, translatable_id INT NOT NULL, UNIQUE INDEX uniq_service_translation_locale (translatable_id, locale), UNIQUE INDEX uniq_service_translation_slug (locale, slug), INDEX IDX_78F363C32C2AC5D3 (translatable_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE setting (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, UNIQUE INDEX UNIQ_9F74B8985E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE setting_translation (value LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, translatable_id INT NOT NULL, UNIQUE INDEX uniq_setting_translation_locale (translatable_id, locale), INDEX IDX_8FC134B82C2AC5D3 (translatable_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tag_translation (name VARCHAR(100) NOT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, slug VARCHAR(190) NOT NULL, translatable_id INT NOT NULL, UNIQUE INDEX uniq_tag_translation_locale (translatable_id, locale), UNIQUE INDEX uniq_tag_translation_slug (locale, slug), INDEX IDX_A8A03F8F2C2AC5D3 (translatable_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E6612469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66E5A0E336 FOREIGN KEY (cover_image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE article_tag ADD CONSTRAINT FK_919694F97294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_tag ADD CONSTRAINT FK_919694F9BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_translation ADD CONSTRAINT FK_2EEA2F082C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE category_translation ADD CONSTRAINT FK_3F207042C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consultation_translation ADD CONSTRAINT FK_518233E72C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES consultation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_translation ADD CONSTRAINT FK_1FE096EF2C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE key_figure_translation ADD CONSTRAINT FK_2D390A002C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES key_figure (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media_gallery_item ADD CONSTRAINT FK_E5F5A71E4E7AF8F FOREIGN KEY (gallery_id) REFERENCES media_gallery (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media_gallery_item ADD CONSTRAINT FK_E5F5A71EEA9FDD75 FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media_gallery_translation ADD CONSTRAINT FK_992E7FC12C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES media_gallery (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media_translation ADD CONSTRAINT FK_430137FC2C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES media (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550727ACA70 FOREIGN KEY (parent_id) REFERENCES menu_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550C4663E4 FOREIGN KEY (page_id) REFERENCES page (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE menu_item_translation ADD CONSTRAINT FK_683EE3A62C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES menu_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE official_document ADD CONSTRAINT FK_18F406CA93CB796C FOREIGN KEY (file_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE official_document ADD CONSTRAINT FK_18F406CA12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE official_document_translation ADD CONSTRAINT FK_B64DC1292C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES official_document (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page ADD CONSTRAINT FK_140AB620727ACA70 FOREIGN KEY (parent_id) REFERENCES page (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE page_translation ADD CONSTRAINT FK_A3D51B1D2C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_translation ADD CONSTRAINT FK_78F363C32C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE setting_translation ADD CONSTRAINT FK_8FC134B82C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES setting (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tag_translation ADD CONSTRAINT FK_A8A03F8F2C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E6612469DE2');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66E5A0E336');
        $this->addSql('ALTER TABLE article_tag DROP FOREIGN KEY FK_919694F97294869C');
        $this->addSql('ALTER TABLE article_tag DROP FOREIGN KEY FK_919694F9BAD26311');
        $this->addSql('ALTER TABLE article_translation DROP FOREIGN KEY FK_2EEA2F082C2AC5D3');
        $this->addSql('ALTER TABLE category_translation DROP FOREIGN KEY FK_3F207042C2AC5D3');
        $this->addSql('ALTER TABLE consultation_translation DROP FOREIGN KEY FK_518233E72C2AC5D3');
        $this->addSql('ALTER TABLE event_translation DROP FOREIGN KEY FK_1FE096EF2C2AC5D3');
        $this->addSql('ALTER TABLE key_figure_translation DROP FOREIGN KEY FK_2D390A002C2AC5D3');
        $this->addSql('ALTER TABLE media_gallery_item DROP FOREIGN KEY FK_E5F5A71E4E7AF8F');
        $this->addSql('ALTER TABLE media_gallery_item DROP FOREIGN KEY FK_E5F5A71EEA9FDD75');
        $this->addSql('ALTER TABLE media_gallery_translation DROP FOREIGN KEY FK_992E7FC12C2AC5D3');
        $this->addSql('ALTER TABLE media_translation DROP FOREIGN KEY FK_430137FC2C2AC5D3');
        $this->addSql('ALTER TABLE menu_item DROP FOREIGN KEY FK_D754D550727ACA70');
        $this->addSql('ALTER TABLE menu_item DROP FOREIGN KEY FK_D754D550C4663E4');
        $this->addSql('ALTER TABLE menu_item_translation DROP FOREIGN KEY FK_683EE3A62C2AC5D3');
        $this->addSql('ALTER TABLE official_document DROP FOREIGN KEY FK_18F406CA93CB796C');
        $this->addSql('ALTER TABLE official_document DROP FOREIGN KEY FK_18F406CA12469DE2');
        $this->addSql('ALTER TABLE official_document_translation DROP FOREIGN KEY FK_B64DC1292C2AC5D3');
        $this->addSql('ALTER TABLE page DROP FOREIGN KEY FK_140AB620727ACA70');
        $this->addSql('ALTER TABLE page_translation DROP FOREIGN KEY FK_A3D51B1D2C2AC5D3');
        $this->addSql('ALTER TABLE service_translation DROP FOREIGN KEY FK_78F363C32C2AC5D3');
        $this->addSql('ALTER TABLE setting_translation DROP FOREIGN KEY FK_8FC134B82C2AC5D3');
        $this->addSql('ALTER TABLE tag_translation DROP FOREIGN KEY FK_A8A03F8F2C2AC5D3');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE article_tag');
        $this->addSql('DROP TABLE article_translation');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE category_translation');
        $this->addSql('DROP TABLE consultation');
        $this->addSql('DROP TABLE consultation_translation');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE event_translation');
        $this->addSql('DROP TABLE key_figure');
        $this->addSql('DROP TABLE key_figure_translation');
        $this->addSql('DROP TABLE media');
        $this->addSql('DROP TABLE media_gallery');
        $this->addSql('DROP TABLE media_gallery_item');
        $this->addSql('DROP TABLE media_gallery_translation');
        $this->addSql('DROP TABLE media_translation');
        $this->addSql('DROP TABLE menu_item');
        $this->addSql('DROP TABLE menu_item_translation');
        $this->addSql('DROP TABLE official_document');
        $this->addSql('DROP TABLE official_document_translation');
        $this->addSql('DROP TABLE page');
        $this->addSql('DROP TABLE page_translation');
        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE service_translation');
        $this->addSql('DROP TABLE setting');
        $this->addSql('DROP TABLE setting_translation');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE tag_translation');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
