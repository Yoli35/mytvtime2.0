<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240317081233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_episode DROP FOREIGN KEY FK_85A702D05278319C');
        $this->addSql('DROP INDEX IDX_85A702D05278319C ON user_episode');
        $this->addSql('ALTER TABLE user_episode CHANGE series_id user_series_id INT NOT NULL');
        $this->addSql('ALTER TABLE user_episode ADD CONSTRAINT FK_85A702D05298BC78 FOREIGN KEY (user_series_id) REFERENCES user_series (id)');
        $this->addSql('CREATE INDEX IDX_85A702D05298BC78 ON user_episode (user_series_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_episode DROP FOREIGN KEY FK_85A702D05298BC78');
        $this->addSql('DROP INDEX IDX_85A702D05298BC78 ON user_episode');
        $this->addSql('ALTER TABLE user_episode CHANGE user_series_id series_id INT NOT NULL');
        $this->addSql('ALTER TABLE user_episode ADD CONSTRAINT FK_85A702D05278319C FOREIGN KEY (series_id) REFERENCES user_series (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_85A702D05278319C ON user_episode (series_id)');
    }
}
