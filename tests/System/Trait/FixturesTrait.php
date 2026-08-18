<?php

declare(strict_types=1);

namespace App\Tests\System\Trait;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Component\DependencyInjection\Container;

/**
 * @method static Container getContainer()
 */
trait FixturesTrait
{
    /**
     * @param array<string> $classes
     */
    private function loadFixtures(array $classes): void
    {
        if (empty($classes)) {
            return;
        }

        // Если ядро ещё не загружено, загружаем его
        if (!static::$kernel) {
            static::bootKernel();
        }

        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');

        $loader = new Loader();
        foreach ($classes as $class) {
            $loader->addFixture(new $class());
        }

        $purger = new ORMPurger($em);
        $executor = new ORMExecutor($em, $purger);
        $executor->execute($loader->getFixtures());
    }
}
