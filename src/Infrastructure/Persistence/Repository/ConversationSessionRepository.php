<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\ConversationSession;
use App\Domain\Exception\CoreException;
use App\Domain\Repository\ConversationSessionRepository as ConversationSessionRepositoryInterface;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * @extends ServiceEntityRepository<ConversationSession>
 */
#[AsAlias(ConversationSessionRepositoryInterface::class)]
class ConversationSessionRepository extends ServiceEntityRepository implements ConversationSessionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConversationSession::class);
    }

    /**
     * @throws CoreException
     */
    public function findById(Uuid $id): ?ConversationSession
    {
        try {
            return $this->findOneBy(['id' => $id]);
        } catch (Throwable $exception) {
            throw new CoreException(
                message: 'Не удалось загрузить сессию диалога',
                previous: $exception,
            );
        }
    }

    /**
     * @throws CoreException
     */
    public function findCurrentByTelegramChatId(int $telegramChatId): ?ConversationSession
    {
        try {
            /** @var ConversationSession|null $session */
            $session = $this->createQueryBuilder('session')
                ->where('session.telegramChatId = :telegramChatId')
                ->setParameter('telegramChatId', $telegramChatId)
                ->orderBy('session.lastActiveAt', 'DESC')
                ->addOrderBy('session.id', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            return $session;
        } catch (Throwable $exception) {
            throw new CoreException(
                message: 'Не удалось загрузить текущую сессию диалога Telegram',
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
            $deleted = $this->createQueryBuilder('session')
                ->delete()
                ->where('session.lastActiveAt < :cutoff')
                ->setParameter('cutoff', $cutoff)
                ->getQuery()
                ->execute();

            return is_int($deleted) ? $deleted : 0;
        } catch (CoreException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CoreException(
                message: 'Не удалось удалить устаревшие сессии диалогов',
                previous: $exception,
            );
        }
    }
}
