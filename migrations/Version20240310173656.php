<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240310173656 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_episode (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, series_id INT NOT NULL, season_number INT NOT NULL, episode_number INT NOT NULL, watch_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', provider_id INT DEFAULT NULL, device_id INT DEFAULT NULL, vote INT DEFAULT NULL, INDEX IDX_85A702D0A76ED395 (user_id), INDEX IDX_85A702D05278319C (series_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_episode ADD CONSTRAINT FK_85A702D0A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_episode ADD CONSTRAINT FK_85A702D05278319C FOREIGN KEY (series_id) REFERENCES user_series (id)');
        $this->addSql('ALTER TABLE user_series CHANGE rating rating INT DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_episode DROP FOREIGN KEY FK_85A702D0A76ED395');
        $this->addSql('ALTER TABLE user_episode DROP FOREIGN KEY FK_85A702D05278319C');
        $this->addSql('DROP TABLE user_episode');
        $this->addSql('ALTER TABLE user_series CHANGE rating rating INT DEFAULT NULL');
    }
}
