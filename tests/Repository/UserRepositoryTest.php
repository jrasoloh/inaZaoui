<?php

namespace App\Tests\Repository;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Tests\FixturesTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

class UserRepositoryTest extends KernelTestCase
{
    use Factories;
    use FixturesTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->resetDatabase(static::getContainer());
    }

    public function testFindActiveGuestsWithMediaCountReturnsOnlyActiveGuests(): void
    {
        /** @var \App\Repository\UserRepository $repository */
        $repository = $this->entityManager->getRepository(User::class);

        $rows = $repository->findActiveGuestsWithMediaCount();

        $byName = [];
        foreach ($rows as $row) {
            $byName[$row['guest']->getName()] = $row['mediaCount'];
        }

        // Two active guests are returned, ordered by name.
        self::assertSame(
            [AppFixtures::GUEST_ACTIVE_NAME, AppFixtures::GUEST_SECOND_NAME],
            array_map(static fn (array $r) => $r['guest']->getName(), $rows)
        );

        // Alice owns exactly one media; Carol owns none.
        self::assertSame(1, $byName[AppFixtures::GUEST_ACTIVE_NAME]);
        self::assertSame(0, $byName[AppFixtures::GUEST_SECOND_NAME]);

        // The blocked guest and the admin are excluded.
        self::assertArrayNotHasKey(AppFixtures::GUEST_BLOCKED_NAME, $byName);
        self::assertArrayNotHasKey('Ina Zaoui', $byName);
    }
}

