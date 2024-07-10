<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240710170222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE movie (id INT AUTO_INCREMENT NOT NULL, collection_id INT DEFAULT NULL, backdrop_path VARCHAR(255) DEFAULT NULL, tmdb_id INT NOT NULL, origin_country JSON NOT NULL, original_language VARCHAR(2) NOT NULL, original_title VARCHAR(255) DEFAULT NULL, overview LONGTEXT DEFAULT NULL, poster_path VARCHAR(255) DEFAULT NULL, release_date DATE DEFAULT NULL, runtime INT DEFAULT NULL, status VARCHAR(16) DEFAULT NULL, tagline VARCHAR(255) DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, vote_average DOUBLE PRECISION DEFAULT NULL, vote_count INT DEFAULT NULL, INDEX IDX_1D5EF26F514956FD (collection_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE movie_collection (id INT AUTO_INCREMENT NOT NULL, tmdb_id INT NOT NULL, name VARCHAR(255) NOT NULL, poster_path VARCHAR(255) DEFAULT NULL, backdrop_path VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_movie (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, movie_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', favorite TINYINT(1) NOT NULL, rating INT NOT NULL, last_viewed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', view_array JSON NOT NULL, INDEX IDX_FF9C0937A76ED395 (user_id), INDEX IDX_FF9C09378F93B6FC (movie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE movie ADD CONSTRAINT FK_1D5EF26F514956FD FOREIGN KEY (collection_id) REFERENCES movie_collection (id)');
        $this->addSql('ALTER TABLE user_movie ADD CONSTRAINT FK_FF9C0937A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_movie ADD CONSTRAINT FK_FF9C09378F93B6FC FOREIGN KEY (movie_id) REFERENCES movie (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE movie DROP FOREIGN KEY FK_1D5EF26F514956FD');
        $this->addSql('ALTER TABLE user_movie DROP FOREIGN KEY FK_FF9C0937A76ED395');
        $this->addSql('ALTER TABLE user_movie DROP FOREIGN KEY FK_FF9C09378F93B6FC');
        $this->addSql('DROP TABLE movie');
        $this->addSql('DROP TABLE movie_collection');
        $this->addSql('DROP TABLE user_movie');
    }
}
