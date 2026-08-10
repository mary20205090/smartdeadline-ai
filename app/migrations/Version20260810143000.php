<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email delivery tracking fields to notifications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification ADD email_sent_at DATETIME DEFAULT NULL, ADD email_failed_at DATETIME DEFAULT NULL, ADD email_error LONGTEXT DEFAULT NULL, ADD email_attempts INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP email_sent_at, DROP email_failed_at, DROP email_error, DROP email_attempts');
    }
}
