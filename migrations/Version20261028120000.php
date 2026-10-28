<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Vidéos personnelles importées depuis des fichiers (converties en MP4 avec ffmpeg).
 */
final class Version20261028120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add personal_video table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE personal_video (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, source_path VARCHAR(255) DEFAULT NULL, filename VARCHAR(255) DEFAULT NULL, thumbnail VARCHAR(255) DEFAULT NULL, duration INT DEFAULT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, size BIGINT DEFAULT NULL, status VARCHAR(16) NOT NULL, error LONGTEXT DEFAULT NULL, recorded_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX personal_video_status_idx (status), INDEX IDX_249E9C3AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE personal_video ADD CONSTRAINT FK_249E9C3AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personal_video DROP FOREIGN KEY FK_249E9C3AA76ED395');
        $this->addSql('DROP TABLE personal_video');
    }
}
