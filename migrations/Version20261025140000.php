<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la date de création des utilisateurs (user.created_at).
 * Les utilisateurs existants reçoivent la date de leur premier ajout de film ou de série,
 * à défaut leur date de mise à jour, à défaut la date courante.
 */
final class Version20261025140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.created_at, initialized with the first movie or series addition';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD created_at DATETIME DEFAULT NULL');
        $this->addSql(<<<SQL
            UPDATE user u
            LEFT JOIN (SELECT user_id, MIN(created_at) AS first_at FROM user_movie GROUP BY user_id) um ON um.user_id = u.id
            LEFT JOIN (SELECT user_id, MIN(added_at) AS first_at FROM user_series GROUP BY user_id) us ON us.user_id = u.id
            SET u.created_at = COALESCE(LEAST(um.first_at, us.first_at), um.first_at, us.first_at, u.updated_at, NOW())
        SQL);
        $this->addSql('ALTER TABLE user MODIFY created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP created_at');
    }
}
