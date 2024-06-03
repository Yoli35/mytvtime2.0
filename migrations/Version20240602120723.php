<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240602120723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_episode_notification_episode_notification DROP FOREIGN KEY FK_4869CC9E387C4597');
        $this->addSql('ALTER TABLE user_episode_notification_episode_notification DROP FOREIGN KEY FK_4869CC9E81ADECC6');
        $this->addSql('ALTER TABLE user_episode_notification_user DROP FOREIGN KEY FK_DC485161387C4597');
        $this->addSql('ALTER TABLE user_episode_notification_user DROP FOREIGN KEY FK_DC485161A76ED395');
        $this->addSql('DROP TABLE user_episode_notification_episode_notification');
        $this->addSql('DROP TABLE user_episode_notification_user');
        $this->addSql('ALTER TABLE user_episode_notification ADD user_id INT NOT NULL, ADD episode_notification_id INT NOT NULL');
        $this->addSql('ALTER TABLE user_episode_notification ADD CONSTRAINT FK_78BEF514A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_episode_notification ADD CONSTRAINT FK_78BEF51481ADECC6 FOREIGN KEY (episode_notification_id) REFERENCES episode_notification (id)');
        $this->addSql('CREATE INDEX IDX_78BEF514A76ED395 ON user_episode_notification (user_id)');
        $this->addSql('CREATE INDEX IDX_78BEF51481ADECC6 ON user_episode_notification (episode_notification_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_episode_notification_episode_notification (user_episode_notification_id INT NOT NULL, episode_notification_id INT NOT NULL, INDEX IDX_4869CC9E387C4597 (user_episode_notification_id), INDEX IDX_4869CC9E81ADECC6 (episode_notification_id), PRIMARY KEY(user_episode_notification_id, episode_notification_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_episode_notification_user (user_episode_notification_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_DC485161387C4597 (user_episode_notification_id), INDEX IDX_DC485161A76ED395 (user_id), PRIMARY KEY(user_episode_notification_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE user_episode_notification_episode_notification ADD CONSTRAINT FK_4869CC9E387C4597 FOREIGN KEY (user_episode_notification_id) REFERENCES user_episode_notification (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_episode_notification_episode_notification ADD CONSTRAINT FK_4869CC9E81ADECC6 FOREIGN KEY (episode_notification_id) REFERENCES episode_notification (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_episode_notification_user ADD CONSTRAINT FK_DC485161387C4597 FOREIGN KEY (user_episode_notification_id) REFERENCES user_episode_notification (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_episode_notification_user ADD CONSTRAINT FK_DC485161A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_episode_notification DROP FOREIGN KEY FK_78BEF514A76ED395');
        $this->addSql('ALTER TABLE user_episode_notification DROP FOREIGN KEY FK_78BEF51481ADECC6');
        $this->addSql('DROP INDEX IDX_78BEF514A76ED395 ON user_episode_notification');
        $this->addSql('DROP INDEX IDX_78BEF51481ADECC6 ON user_episode_notification');
        $this->addSql('ALTER TABLE user_episode_notification DROP user_id, DROP episode_notification_id');
    }
}
