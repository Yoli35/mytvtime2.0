<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la description des vidéos personnelles (personal_video.description).
 */
final class Version20261028140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add personal_video.description';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personal_video ADD description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personal_video DROP description');
    }
}
