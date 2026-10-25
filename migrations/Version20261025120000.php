<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée les tables movie_comment et movie_comment_image : commentaires (et leurs images) sur les films.
 */
final class Version20261025120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create movie_comment and movie_comment_image tables (movie comments and their images)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE movie_comment (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, movie_id INT NOT NULL, reply_to_id INT DEFAULT NULL, INDEX IDX_B4E21042A76ED395 (user_id), INDEX IDX_B4E210428F93B6FC (movie_id), INDEX IDX_B4E21042FFDF7169 (reply_to_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE movie_comment_image (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, movie_comment_id INT NOT NULL, INDEX IDX_D3540B5911990D2F (movie_comment_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE movie_comment ADD CONSTRAINT FK_B4E21042A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE movie_comment ADD CONSTRAINT FK_B4E210428F93B6FC FOREIGN KEY (movie_id) REFERENCES movie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE movie_comment ADD CONSTRAINT FK_B4E21042FFDF7169 FOREIGN KEY (reply_to_id) REFERENCES movie_comment (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE movie_comment_image ADD CONSTRAINT FK_D3540B5911990D2F FOREIGN KEY (movie_comment_id) REFERENCES movie_comment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE movie_comment_image DROP FOREIGN KEY FK_D3540B5911990D2F');
        $this->addSql('ALTER TABLE movie_comment DROP FOREIGN KEY FK_B4E21042FFDF7169');
        $this->addSql('ALTER TABLE movie_comment DROP FOREIGN KEY FK_B4E210428F93B6FC');
        $this->addSql('ALTER TABLE movie_comment DROP FOREIGN KEY FK_B4E21042A76ED395');
        $this->addSql('DROP TABLE movie_comment_image');
        $this->addSql('DROP TABLE movie_comment');
    }
}
