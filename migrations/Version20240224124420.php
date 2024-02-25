<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240224124420 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_series (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, series_id INT NOT NULL, added_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_watch_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_season INT DEFAULT NULL, last_episode INT DEFAULT NULL, viewed_episodes INT DEFAULT 0 NOT NULL, progress DOUBLE PRECISION DEFAULT \'0\' NOT NULL, INDEX IDX_5F421A10A76ED395 (user_id), INDEX IDX_5F421A105278319C (series_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_series ADD CONSTRAINT FK_5F421A10A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_series ADD CONSTRAINT FK_5F421A105278319C FOREIGN KEY (series_id) REFERENCES series (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_series DROP FOREIGN KEY FK_5F421A10A76ED395');
        $this->addSql('ALTER TABLE user_series DROP FOREIGN KEY FK_5F421A105278319C');
        $this->addSql('DROP TABLE user_series');
    }
}
