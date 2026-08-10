<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user email notification preference.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD email_notifications_enabled TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP email_notifications_enabled');
    }
}
