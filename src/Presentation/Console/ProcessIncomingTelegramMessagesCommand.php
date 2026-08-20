<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\UseCase\ProcessIncomingTelegramMessages;
use App\Domain\Exception\CoreException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(
    name: 'telegram:process-incoming',
    description: 'Поллинг входящих сообщений Telegram: цикл с паузой 1 минута, один процесс',
)]
final class ProcessIncomingTelegramMessagesCommand extends Command
{
    use LockableTrait;

    private const int SLEEP_SECONDS = 60;

    public function __construct(
        private readonly ProcessIncomingTelegramMessages $processIncomingTelegramMessages,
        LockFactory $lockFactory,
    ) {
        $this->lockFactory = $lockFactory;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->lock()) {
            $io->warning('Команда telegram:process-incoming уже запущена.');

            return Command::FAILURE;
        }

        try {
            /** @phpstan-ignore while.alwaysTrue */
            while (true) {
                try {
                    $this->processIncomingTelegramMessages->execute();
                } catch (CoreException $exception) {
                    $io->error($exception->getMessage());
                }

                sleep(self::SLEEP_SECONDS);
            }
        } finally {
            $this->release();
        }
    }
}
