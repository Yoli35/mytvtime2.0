<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée les tables season et episode : cache local des données de saisons / épisodes renvoyées par TheMovieDB.
 */
final class Version20261016120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create season and episode tables (TMDB season / episode data cache)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE season (id INT AUTO_INCREMENT NOT NULL, tmdb_id INT NOT NULL, locale VARCHAR(8) NOT NULL, air_date DATE DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, overview LONGTEXT DEFAULT NULL, poster_path VARCHAR(255) DEFAULT NULL, season_number INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, series_id INT NOT NULL, INDEX idx_season_series_number_locale (series_id, season_number, locale), UNIQUE INDEX uniq_season_tmdb_id_locale (tmdb_id, locale), INDEX IDX_F0E45BA95278319C (series_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE episode (id INT AUTO_INCREMENT NOT NULL, tmdb_id INT NOT NULL, locale VARCHAR(8) NOT NULL, air_date DATE DEFAULT NULL, season_number INT NOT NULL, episode_number INT NOT NULL, episode_type VARCHAR(32) DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, overview LONGTEXT DEFAULT NULL, runtime INT DEFAULT NULL, still_path VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, series_id INT NOT NULL, season_id INT NOT NULL, INDEX idx_episode_series_season (series_id, season_number, episode_number), UNIQUE INDEX uniq_episode_tmdb_id_locale (tmdb_id, locale), INDEX IDX_DDAA1CDA5278319C (series_id), INDEX IDX_DDAA1CDA4EC001D1 (season_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE season ADD CONSTRAINT FK_F0E45BA95278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE episode ADD CONSTRAINT FK_DDAA1CDA5278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE episode ADD CONSTRAINT FK_DDAA1CDA4EC001D1 FOREIGN KEY (season_id) REFERENCES season (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE episode DROP FOREIGN KEY FK_DDAA1CDA4EC001D1');
        $this->addSql('ALTER TABLE episode DROP FOREIGN KEY FK_DDAA1CDA5278319C');
        $this->addSql('ALTER TABLE season DROP FOREIGN KEY FK_F0E45BA95278319C');
        $this->addSql('DROP TABLE episode');
        $this->addSql('DROP TABLE season');
    }
}
