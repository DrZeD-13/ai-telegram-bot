<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\UseCase\PurgeOldConversationHistory;
use App\Domain\Exception\CoreException;
use DateTimeInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(
    name: 'conversation:purge-old-history',
    description: 'Удаляет историю диалогов с нейросетью старше одного месяца (для cron)',
)]
final class PurgeOldConversationHistoryCommand extends Command
{
    use LockableTrait;

    public function __construct(
        private readonly PurgeOldConversationHistory $purgeOldConversationHistory,
        LockFactory $lockFactory,
    ) {
        $this->lockFactory = $lockFactory;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->lock()) {
            $io->warning('Команда conversation:purge-old-history уже запущена.');

            return Command::FAILURE;
        }

        try {
            $result = $this->purgeOldConversationHistory->execute();
        } catch (CoreException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } finally {
            $this->release();
        }

        $io->success(sprintf(
            'Удалено сообщений: %d, сессий: %d (старше %s).',
            $result->deletedMessages,
            $result->deletedSessions,
            $result->cutoff->format(DateTimeInterface::ATOM),
        ));

        return Command::SUCCESS;
    }
}
