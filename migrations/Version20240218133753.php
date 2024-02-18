<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240218133753 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE image_config (id INT AUTO_INCREMENT NOT NULL, base_url VARCHAR(255) NOT NULL, secure_base_url VARCHAR(255) NOT NULL, backdrop_sizes LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', logo_sizes LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', poster_sizes LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', profile_sizes LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', still_sizes LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE image_config');
    }
}
