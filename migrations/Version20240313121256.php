<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240313121256 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE series_watch_link ADD series_id INT NOT NULL');
        $this->addSql('ALTER TABLE series_watch_link ADD CONSTRAINT FK_1E036DF05278319C FOREIGN KEY (series_id) REFERENCES series (id)');
        $this->addSql('CREATE INDEX IDX_1E036DF05278319C ON series_watch_link (series_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE series_watch_link DROP FOREIGN KEY FK_1E036DF05278319C');
        $this->addSql('DROP INDEX IDX_1E036DF05278319C ON series_watch_link');
        $this->addSql('ALTER TABLE series_watch_link DROP series_id');
    }
}
