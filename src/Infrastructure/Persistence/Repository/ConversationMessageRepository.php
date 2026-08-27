<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\ConversationMessage;
use App\Domain\Entity\ConversationMessageCollection;
use App\Domain\Exception\CoreException;
use App\Domain\Repository\ConversationMessageRepository as ConversationMessageRepositoryInterface;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * @extends ServiceEntityRepository<ConversationMessage>
 */
#[AsAlias(ConversationMessageRepositoryInterface::class)]
class ConversationMessageRepository extends ServiceEntityRepository implements ConversationMessageRepositoryInterface
{
    private const int MAX_HISTORY = 40;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConversationMessage::class);
    }

    /**
     * @throws CoreException
     */
    public function findHistoryByChatId(Uuid $chatId): ConversationMessageCollection
    {
        try {
            /** @var list<ConversationMessage> $messages */
            $messages = $this->createQueryBuilder('message')
                ->where('message.chatId = :chatId')
                ->setParameter('chatId', $chatId)
                ->orderBy('message.createdAt', 'DESC')
                ->addOrderBy('message.id', 'DESC')
                ->setMaxResults(self::MAX_HISTORY)
                ->getQuery()
                ->getResult();

            return new ConversationMessageCollection(...array_reverse($messages));
        } catch (CoreException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CoreException(
                message: 'Не удалось загрузить историю диалога',
                previous: $exception,
            );
        }
    }

    /**
     * @throws CoreException
     */
    public function deleteOlderThan(DateTimeImmutable $cutoff): int
    {
        try {
            $deleted = $this->createQueryBuilder('message')
                ->delete()
                ->where('message.createdAt < :cutoff')
                ->setParameter('cutoff', $cutoff)
                ->getQuery()
                ->execute();

            return is_int($deleted) ? $deleted : 0;
        } catch (CoreException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CoreException(
                message: 'Не удалось удалить устаревшую историю диалогов',
                previous: $exception,
            );
        }
    }
}
