<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Container\ContainerInterface;

/**
 * Recreates the test schema and loads {@see AppFixtures} so every database test
 * starts from a clean, well-known state.
 */
trait FixturesTrait
{
    protected ?EntityManagerInterface $entityManager = null;

    /**
     * Drops and recreates the schema, then loads the application fixtures.
     */
    protected function resetDatabase(ContainerInterface $container): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->entityManager = $em;

        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($em);
        $schemaTool->dropDatabase();

        if ([] !== $metadata) {
            $schemaTool->createSchema($metadata);
        }

        $loader = new Loader();
        $loader->addFixture(new AppFixtures());

        $executor = new ORMExecutor($em, new ORMPurger());
        $executor->execute($loader->getFixtures());

        return $em;
    }

    protected function findUserByEmail(string $email): ?User
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);
    }
}


