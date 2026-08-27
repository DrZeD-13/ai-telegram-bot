<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\ConversationMessage;
use App\Domain\Entity\ConversationMessageCollection;
use App\Domain\Exception\CoreException;
use App\Domain\Repository\ConversationMessageRepository as ConversationMessageRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
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
    public function findHistoryByChatId(int $chatId): ConversationMessageCollection
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
                message: 'Не удалось загрузить историю диалога Telegram',
                previous: $exception,
            );
        }
    }

    /**
     * @throws CoreException
     */
    public function deleteByChatId(int $chatId): int
    {
        try {
            $deleted = $this->createQueryBuilder('message')
                ->delete()
                ->where('message.chatId = :chatId')
                ->setParameter('chatId', $chatId)
                ->getQuery()
                ->execute();

            return is_int($deleted) ? $deleted : 0;
        } catch (CoreException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CoreException(
                message: 'Не удалось очистить историю диалога Telegram',
                previous: $exception,
            );
        }
    }
}
