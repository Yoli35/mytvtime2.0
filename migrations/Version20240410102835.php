<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240410102835 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE series_additional_overview DROP INDEX UNIQ_7F5CBAA5953C1C61, ADD INDEX IDX_7F5CBAA5953C1C61 (source_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE series_additional_overview DROP INDEX IDX_7F5CBAA5953C1C61, ADD UNIQUE INDEX UNIQ_7F5CBAA5953C1C61 (source_id)');
    }
}
