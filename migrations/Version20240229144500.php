<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240229144500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE provider (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, logo_path VARCHAR(255) NOT NULL, provider_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_provider (user_id INT NOT NULL, provider_id INT NOT NULL, INDEX IDX_7249979CA76ED395 (user_id), INDEX IDX_7249979CA53A8AA (provider_id), PRIMARY KEY(user_id, provider_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_provider ADD CONSTRAINT FK_7249979CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_provider ADD CONSTRAINT FK_7249979CA53A8AA FOREIGN KEY (provider_id) REFERENCES provider (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_provider DROP FOREIGN KEY FK_7249979CA76ED395');
        $this->addSql('ALTER TABLE user_provider DROP FOREIGN KEY FK_7249979CA53A8AA');
        $this->addSql('DROP TABLE provider');
        $this->addSql('DROP TABLE user_provider');
    }
}
