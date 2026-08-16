<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\InMemoryUser;

class UserCheckerTest extends TestCase
{
    public function testActiveUserPassesPreAuth(): void
    {
        $user = new User();
        $user->setActive(true);

        $checker = new UserChecker();
        $checker->checkPreAuth($user);

        $this->expectNotToPerformAssertions();
    }

    public function testBlockedUserIsRejected(): void
    {
        $user = new User();
        $user->setActive(false);

        $checker = new UserChecker();

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Votre accès a été révoqué.');

        $checker->checkPreAuth($user);
    }

    public function testNonAppUserIsIgnored(): void
    {
        $checker = new UserChecker();

        // A user that is not our entity must not trigger the active check.
        $checker->checkPreAuth(new InMemoryUser('foo', 'bar'));

        $this->expectNotToPerformAssertions();
    }

    public function testCheckPostAuthIsNoop(): void
    {
        $checker = new UserChecker();
        $checker->checkPostAuth(new User());

        $this->expectNotToPerformAssertions();
    }
}

