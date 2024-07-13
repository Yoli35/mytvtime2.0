<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240713121631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE movie_localized_name (id INT AUTO_INCREMENT NOT NULL, movie_id INT NOT NULL, name VARCHAR(255) NOT NULL, locale VARCHAR(8) NOT NULL, slug VARCHAR(255) NOT NULL, INDEX IDX_3AA482D78F93B6FC (movie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE movie_localized_overview (id INT AUTO_INCREMENT NOT NULL, movie_id INT NOT NULL, overview LONGTEXT NOT NULL, locale VARCHAR(8) NOT NULL, INDEX IDX_FBE82E3E8F93B6FC (movie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE movie_localized_name ADD CONSTRAINT FK_3AA482D78F93B6FC FOREIGN KEY (movie_id) REFERENCES movie (id)');
        $this->addSql('ALTER TABLE movie_localized_overview ADD CONSTRAINT FK_FBE82E3E8F93B6FC FOREIGN KEY (movie_id) REFERENCES movie (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE movie_localized_name DROP FOREIGN KEY FK_3AA482D78F93B6FC');
        $this->addSql('ALTER TABLE movie_localized_overview DROP FOREIGN KEY FK_FBE82E3E8F93B6FC');
        $this->addSql('DROP TABLE movie_localized_name');
        $this->addSql('DROP TABLE movie_localized_overview');
    }
}
