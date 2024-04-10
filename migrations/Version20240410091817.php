<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240410091817 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE series_additional_overview (id INT AUTO_INCREMENT NOT NULL, series_id INT NOT NULL, overview LONGTEXT NOT NULL, locale VARCHAR(8) NOT NULL, source_path VARCHAR(255) DEFAULT NULL, source_logo_path VARCHAR(255) DEFAULT NULL, INDEX IDX_7F5CBAA55278319C (series_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE series_additional_overview ADD CONSTRAINT FK_7F5CBAA55278319C FOREIGN KEY (series_id) REFERENCES series (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE series_additional_overview DROP FOREIGN KEY FK_7F5CBAA55278319C');
        $this->addSql('DROP TABLE series_additional_overview');
    }
}
