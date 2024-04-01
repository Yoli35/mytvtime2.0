<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240401102732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE series_broadcast_schedule (id INT AUTO_INCREMENT NOT NULL, series_id INT NOT NULL, day_of_week INT NOT NULL, air_at TIME NOT NULL, country VARCHAR(2) NOT NULL, utc INT NOT NULL, INDEX IDX_AA0FF0CA5278319C (series_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE series_broadcast_schedule ADD CONSTRAINT FK_AA0FF0CA5278319C FOREIGN KEY (series_id) REFERENCES series (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE series_broadcast_schedule DROP FOREIGN KEY FK_AA0FF0CA5278319C');
        $this->addSql('DROP TABLE series_broadcast_schedule');
    }
}
