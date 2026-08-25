<?php

/**
 * Lightweight performance probe for Front Office pages.
 *
 * Boots the Symfony kernel (dev, debug) and issues real in-process requests,
 * then reads the Symfony Profiler collectors to report, per URL:
 *   - number of SQL queries executed,
 *   - controller/template wall-clock duration (ms),
 *   - peak memory (MB).
 *
 * Usage:
 *   php scripts/perf-measure.php /guests /portfolio /about /
 */

require dirname(__DIR__).'/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$uris = array_slice($argv, 1);
if ([] === $uris) {
    $uris = ['/guests'];
}

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

/** @var \Symfony\Component\HttpKernel\Profiler\Profiler $profiler */
$profiler = $container->get('profiler');

printf("%-16s %10s %14s %12s\n", 'URL', 'SQL queries', 'Duration (ms)', 'Mem (MB)');
printf("%s\n", str_repeat('-', 56));

const ITERATIONS = 5;

foreach ($uris as $uri) {
    // Warm-up request (prime the route/twig cache) — not measured.
    $kernel->handle(Request::create($uri));

    $queries = '-';
    $memory = '-';
    $durations = [];

    for ($i = 0; $i < ITERATIONS; ++$i) {
        $profiler->enable();

        $request = Request::create($uri);
        $start = microtime(true);
        $response = $kernel->handle($request);
        $durations[] = (microtime(true) - $start) * 1000;

        // Collect synchronously (no storage / debug token needed).
        $profile = $profiler->collect($request, $response);

        if ($profile) {
            if ($profile->hasCollector('db')) {
                $queries = $profile->getCollector('db')->getQueryCount();
            }
            if ($profile->hasCollector('memory')) {
                $memory = number_format($profile->getCollector('memory')->getMemory() / (1024 * 1024), 1);
            }
        }
    }

    sort($durations);
    $median = $durations[(int) floor(count($durations) / 2)];

    printf("%-16s %10s %14s %12s   [HTTP %d]\n", $uri, $queries, number_format($median, 1), $memory, $response->getStatusCode());
}

$kernel->shutdown();



