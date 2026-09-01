<?php

/*
 * Loader used by PHPStan (phpstan-doctrine extension) to read the Doctrine
 * mapping metadata. It boots the Symfony kernel in the "test" environment and
 * returns the EntityManager. No database connection is opened here: PHPStan only
 * reads the mapping (entities, repositoryClass, field types) statically.
 */

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel('test', false);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();

