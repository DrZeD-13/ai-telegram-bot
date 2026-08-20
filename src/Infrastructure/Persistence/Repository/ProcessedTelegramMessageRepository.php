<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\ProcessedTelegramMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProcessedTelegramMessage>
 */
class ProcessedTelegramMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessedTelegramMessage::class);
    }

    public function save(ProcessedTelegramMessage $message): void
    {
        $this->getEntityManager()->persist($message);
        $this->getEntityManager()->flush();
    }

    public function findOneByChatAndMessageId(int $chatId, int $messageId): ?ProcessedTelegramMessage
    {
        return $this->findOneBy([
            'chatId' => $chatId,
            'messageId' => $messageId,
        ]);
    }
}
