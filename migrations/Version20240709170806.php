<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240709170806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_pinned_series (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, user_series_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3F631B48A76ED395 (user_id), UNIQUE INDEX UNIQ_3F631B485298BC78 (user_series_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_pinned_series ADD CONSTRAINT FK_3F631B48A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_pinned_series ADD CONSTRAINT FK_3F631B485298BC78 FOREIGN KEY (user_series_id) REFERENCES user_series (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_pinned_series DROP FOREIGN KEY FK_3F631B48A76ED395');
        $this->addSql('ALTER TABLE user_pinned_series DROP FOREIGN KEY FK_3F631B485298BC78');
        $this->addSql('DROP TABLE user_pinned_series');
    }
}
