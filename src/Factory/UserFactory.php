<?php

namespace App\Factory;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return User::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->name(),
            'email' => self::faker()->unique()->safeEmail(),
            'description' => self::faker()->optional()->sentence(),
            'admin' => false,
            'active' => true,
            // Plain password: hashed in initialize() below.
            'password' => 'password',
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (User $user): void {
            $plainPassword = $user->getPassword() ?? 'password';
            $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        });
    }

    /**
     * The photographer who owns the public portfolio.
     */
    public function admin(): static
    {
        return $this->with([
            'admin' => true,
            'active' => true,
        ]);
    }

    /**
     * A guest whose photos are publicly visible.
     */
    public function activeGuest(): static
    {
        return $this->with([
            'admin' => false,
            'active' => true,
        ]);
    }

    /**
     * A revoked guest whose photos must stay hidden.
     */
    public function blockedGuest(): static
    {
        return $this->with([
            'admin' => false,
            'active' => false,
        ]);
    }
}


