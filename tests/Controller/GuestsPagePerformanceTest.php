<?php

namespace App\Tests\Controller;

use App\Tests\AbstractWebTestCase;

/**
 * Non-regression guard for the "Invités" page performance fix.
 *
 * The page used to trigger an N+1 problem (one extra query per guest via
 * `guest.medias|length`). It must now run in a constant, small number of
 * queries regardless of how many guests exist.
 */
class GuestsPagePerformanceTest extends AbstractWebTestCase
{
    public function testGuestsPageRunsInConstantNumberOfQueries(): void
    {
        // The fixtures loaded in setUp() run many queries on the same shared
        // connection; reset the Doctrine debug collector so we only count the
        // queries emitted by the guests page request itself.
        $container = static::getContainer();
        if ($container->has('doctrine.debug_data_holder')) {
            $container->get('doctrine.debug_data_holder')->reset();
        }

        $this->client->enableProfiler();

        $this->client->request('GET', '/guests');

        self::assertResponseIsSuccessful();

        $profile = $this->client->getProfile();
        self::assertNotFalse($profile, 'The profiler must be available in the test environment.');

        $queryCount = $profile->getCollector('db')->getQueryCount();

        // A single aggregated query is expected; allow a small margin for any
        // framework-level query. The key point is that it must NOT scale with
        // the number of guests.
        self::assertLessThanOrEqual(
            2,
            $queryCount,
            sprintf('The guests page should run in <= 2 SQL queries, got %d.', $queryCount)
        );
    }
}


