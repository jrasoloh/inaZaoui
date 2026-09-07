<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Base class for functional tests: boots a client and loads a fresh database
 * with the application fixtures before each test.
 */
abstract class AbstractWebTestCase extends WebTestCase
{
    use Factories;
    use FixturesTrait;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase(static::getContainer());
    }
}

