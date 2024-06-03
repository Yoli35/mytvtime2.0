<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240602120005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_episode_notification (id INT AUTO_INCREMENT NOT NULL, validate_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_episode_notification_user (user_episode_notification_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_DC485161387C4597 (user_episode_notification_id), INDEX IDX_DC485161A76ED395 (user_id), PRIMARY KEY(user_episode_notification_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_episode_notification_episode_notification (user_episode_notification_id INT NOT NULL, episode_notification_id INT NOT NULL, INDEX IDX_4869CC9E387C4597 (user_episode_notification_id), INDEX IDX_4869CC9E81ADECC6 (episode_notification_id), PRIMARY KEY(user_episode_notification_id, episode_notification_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_episode_notification_user ADD CONSTRAINT FK_DC485161387C4597 FOREIGN KEY (user_episode_notification_id) REFERENCES user_episode_notification (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_episode_notification_user ADD CONSTRAINT FK_DC485161A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_episode_notification_episode_notification ADD CONSTRAINT FK_4869CC9E387C4597 FOREIGN KEY (user_episode_notification_id) REFERENCES user_episode_notification (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_episode_notification_episode_notification ADD CONSTRAINT FK_4869CC9E81ADECC6 FOREIGN KEY (episode_notification_id) REFERENCES episode_notification (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE episode_notification DROP confirmed_at');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_episode_notification_user DROP FOREIGN KEY FK_DC485161387C4597');
        $this->addSql('ALTER TABLE user_episode_notification_user DROP FOREIGN KEY FK_DC485161A76ED395');
        $this->addSql('ALTER TABLE user_episode_notification_episode_notification DROP FOREIGN KEY FK_4869CC9E387C4597');
        $this->addSql('ALTER TABLE user_episode_notification_episode_notification DROP FOREIGN KEY FK_4869CC9E81ADECC6');
        $this->addSql('DROP TABLE user_episode_notification');
        $this->addSql('DROP TABLE user_episode_notification_user');
        $this->addSql('DROP TABLE user_episode_notification_episode_notification');
        $this->addSql('ALTER TABLE episode_notification ADD confirmed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
