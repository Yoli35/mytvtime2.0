<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240410100851 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE source (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, logo_path VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE series_additional_overview ADD source_id INT DEFAULT NULL, DROP source_name, DROP source_path, DROP source_logo_path');
        $this->addSql('ALTER TABLE series_additional_overview ADD CONSTRAINT FK_7F5CBAA5953C1C61 FOREIGN KEY (source_id) REFERENCES source (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7F5CBAA5953C1C61 ON series_additional_overview (source_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE series_additional_overview DROP FOREIGN KEY FK_7F5CBAA5953C1C61');
        $this->addSql('DROP TABLE source');
        $this->addSql('DROP INDEX UNIQ_7F5CBAA5953C1C61 ON series_additional_overview');
        $this->addSql('ALTER TABLE series_additional_overview ADD source_name VARCHAR(255) DEFAULT NULL, ADD source_path VARCHAR(255) DEFAULT NULL, ADD source_logo_path VARCHAR(255) DEFAULT NULL, DROP source_id');
    }
}
