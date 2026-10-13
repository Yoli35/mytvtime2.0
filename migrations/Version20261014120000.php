<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renomme watch_at → watched_at (user_episode) et last_watch_at → last_watched_at (user_series).
 * RENAME COLUMN conserve les données (contrairement au DROP + ADD que générerait migrations:diff).
 */
final class Version20261014120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename user_episode.watch_at to watched_at and user_series.last_watch_at to last_watched_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_episode RENAME COLUMN watch_at TO watched_at');
        $this->addSql('ALTER TABLE user_series RENAME COLUMN last_watch_at TO last_watched_at');
        $this->addSql('ALTER TABLE user_series RENAME INDEX idx_us_user_last_watch_at TO idx_us_user_last_watched_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_series RENAME INDEX idx_us_user_last_watched_at TO idx_us_user_last_watch_at');
        $this->addSql('ALTER TABLE user_series RENAME COLUMN last_watched_at TO last_watch_at');
        $this->addSql('ALTER TABLE user_episode RENAME COLUMN watched_at TO watch_at');
    }
}
