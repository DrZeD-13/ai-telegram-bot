<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Exception\PersistenceException;
use App\Application\Port\UnitOfWork;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Throwable;

#[AsAlias(UnitOfWork::class)]
final readonly class DoctrineUnitOfWork implements UnitOfWork
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws PersistenceException
     */
    public function persist(object $entity): void
    {
        try {
            $this->entityManager->persist($entity);
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PersistenceException(
                message: 'Не удалось зарегистрировать сущность в unit of work',
                previous: $exception,
            );
        }
    }

    /**
     * @throws PersistenceException
     */
    public function flush(): void
    {
        try {
            $this->entityManager->flush();
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PersistenceException(
                message: 'Не удалось сохранить изменения в хранилище',
                previous: $exception,
            );
        }
    }
}
