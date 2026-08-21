<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Exception\PersistenceException;

interface UnitOfWork
{
    /**
     * @throws PersistenceException
     */
    public function persist(object $entity): void;

    /**
     * @throws PersistenceException
     */
    public function flush(): void;

    /**
     * @throws PersistenceException
     */
    public function clear(): void;
}
