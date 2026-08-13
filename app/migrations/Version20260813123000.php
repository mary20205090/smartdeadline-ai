<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user login tracking and email unsubscribe tokens.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD last_login_at DATETIME DEFAULT NULL, ADD login_count INT DEFAULT 0 NOT NULL, ADD email_unsubscribe_token VARCHAR(64) DEFAULT NULL');
        $this->addSql("UPDATE user SET email_unsubscribe_token = SHA2(CONCAT(id, ':', email, ':smartdeadline'), 256) WHERE email_unsubscribe_token IS NULL");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_EMAIL_UNSUBSCRIBE_TOKEN ON user (email_unsubscribe_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_USER_EMAIL_UNSUBSCRIBE_TOKEN ON user');
        $this->addSql('ALTER TABLE user DROP last_login_at, DROP login_count, DROP email_unsubscribe_token');
    }
}
