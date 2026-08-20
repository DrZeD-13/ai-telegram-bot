<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\ProcessedTelegramMessage;
use App\Domain\Exception\CoreException;
use App\Domain\Repository\ProcessedTelegramMessageRepository as ProcessedTelegramMessageRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Throwable;

/**
 * @extends ServiceEntityRepository<ProcessedTelegramMessage>
 */
#[AsAlias(ProcessedTelegramMessageRepositoryInterface::class)]
class ProcessedTelegramMessageRepository extends ServiceEntityRepository implements ProcessedTelegramMessageRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessedTelegramMessage::class);
    }

    /**
     * @throws CoreException
     */
    public function findMaxUpdateId(): ?int
    {
        try {
            $maxUpdateId = $this->createQueryBuilder('message')
                ->select('MAX(message.updateId)')
                ->getQuery()
                ->getSingleScalarResult();

            if ($maxUpdateId === null) {
                return null;
            }

            return (int) $maxUpdateId;
        } catch (CoreException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CoreException(
                message: 'Не удалось получить максимальный идентификатор Telegram update',
                previous: $exception,
            );
        }
    }

    /**
     * @throws CoreException
     */
    public function findOneByChatAndMessageId(int $chatId, int $messageId): ?ProcessedTelegramMessage
    {
        try {
            return $this->findOneBy([
                'chatId' => $chatId,
                'messageId' => $messageId,
            ]);
        } catch (CoreException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CoreException(
                message: 'Не удалось найти обработанное сообщение Telegram',
                previous: $exception,
            );
        }
    }
}
