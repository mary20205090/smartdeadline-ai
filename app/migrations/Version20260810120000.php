<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add update timestamps and soft-delete timestamps for academic records.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assignment ADD updated_at DATETIME DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE assignment SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql('ALTER TABLE assignment CHANGE updated_at updated_at DATETIME NOT NULL');

        $this->addSql('ALTER TABLE course ADD updated_at DATETIME DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE course SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql('ALTER TABLE course CHANGE updated_at updated_at DATETIME NOT NULL');

        $this->addSql('ALTER TABLE notification ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE notification SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql('ALTER TABLE notification CHANGE updated_at updated_at DATETIME NOT NULL');

        $this->addSql('ALTER TABLE prediction ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE prediction SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql('ALTER TABLE prediction CHANGE updated_at updated_at DATETIME NOT NULL');

        $this->addSql('ALTER TABLE user ADD updated_at DATETIME DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE user SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql('ALTER TABLE user CHANGE updated_at updated_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assignment DROP updated_at, DROP deleted_at');
        $this->addSql('ALTER TABLE course DROP updated_at, DROP deleted_at');
        $this->addSql('ALTER TABLE notification DROP updated_at');
        $this->addSql('ALTER TABLE prediction DROP updated_at');
        $this->addSql('ALTER TABLE user DROP updated_at, DROP deleted_at');
    }
}
