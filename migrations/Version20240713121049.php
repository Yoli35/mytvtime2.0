<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240713121049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE movie_additional_overview (id INT AUTO_INCREMENT NOT NULL, movie_id INT NOT NULL, source_id INT NOT NULL, overview LONGTEXT NOT NULL, locale VARCHAR(8) NOT NULL, INDEX IDX_6C72BFA88F93B6FC (movie_id), INDEX IDX_6C72BFA8953C1C61 (source_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE movie_additional_overview ADD CONSTRAINT FK_6C72BFA88F93B6FC FOREIGN KEY (movie_id) REFERENCES movie (id)');
        $this->addSql('ALTER TABLE movie_additional_overview ADD CONSTRAINT FK_6C72BFA8953C1C61 FOREIGN KEY (source_id) REFERENCES source (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE movie_additional_overview DROP FOREIGN KEY FK_6C72BFA88F93B6FC');
        $this->addSql('ALTER TABLE movie_additional_overview DROP FOREIGN KEY FK_6C72BFA8953C1C61');
        $this->addSql('DROP TABLE movie_additional_overview');
    }
}
