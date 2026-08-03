<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260803180840 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add password column to user and set the admin (ina@zaoui.com) password.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD password VARCHAR(255) DEFAULT NULL');

        // Bootstrap the admin password so login keeps working after switching
        // from the in-memory provider to the database (entity) provider.
        // Hash = bcrypt('password') (reused from the former in-memory user).
        $this->addSql("UPDATE `user` SET password = '\$2y\$13\$7JS0ehfU8vZhB3Q8o1sPGuoQxkiPGXRGgrAizmNfI5Sgy.Dqt9xoW' WHERE email = 'ina@zaoui.com'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP password');
    }
}
