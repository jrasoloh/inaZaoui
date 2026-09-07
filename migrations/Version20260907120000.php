<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Data migration: align existing users' passwords with the seeded convention.
 *
 * Context: historically the database was seeded from SQL dumps whose guest rows
 * had a NULL password, and only the admin (ina@zaoui.com) got a password from
 * {@see Version20260803180840}. As a result those guests could not log in.
 *
 * This migration backfills every account so it matches the fixtures convention:
 *   - the admin ina@zaoui.com  -> "password"
 *   - every other (guest) user -> "test"
 *
 * The hashes below are bcrypt (cost 13), verifiable by the "auto" password
 * hasher configured in security.yaml. No schema change is performed.
 */
final class Version20260907120000 extends AbstractMigration
{
    /**
     * bcrypt('password', cost: 13) — reused from Version20260803180840.
     */
    private const ADMIN_HASH = '$2y$13$7JS0ehfU8vZhB3Q8o1sPGuoQxkiPGXRGgrAizmNfI5Sgy.Dqt9xoW';

    /**
     * bcrypt('test', cost: 13).
     */
    private const GUEST_HASH = '$2y$13$GjwiRE7FKb.8OBkksVRO3.LLdYNnbht7P7dxZmVwptXMyXdQ6tyUa';

    public function getDescription(): string
    {
        return 'Backfill user passwords: admin ina@zaoui.com -> "password", all other users -> "test".';
    }

    public function up(Schema $schema): void
    {
        // Every guest account gets the shared "test" password.
        $this->addSql(sprintf(
            "UPDATE `user` SET password = '%s' WHERE email <> 'ina@zaoui.com'",
            self::GUEST_HASH,
        ));

        // Ensure the admin password is set even if the earlier migration did not
        // match (e.g. a database seeded without the ina@zaoui.com row yet).
        $this->addSql(sprintf(
            "UPDATE `user` SET password = '%s' WHERE email = 'ina@zaoui.com'",
            self::ADMIN_HASH,
        ));
    }

    public function down(Schema $schema): void
    {
        // Revert the guests to their previous (password-less) state. The admin
        // password predates this migration, so it is intentionally left intact.
        $this->addSql("UPDATE `user` SET password = NULL WHERE email <> 'ina@zaoui.com'");
    }
}


