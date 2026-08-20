<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\UseCase\ProcessIncomingTelegramMessages;
use App\Domain\Exception\CoreException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'telegram:process-incoming',
    description: 'Обработать входящие сообщения Telegram нейросетью',
)]
final class ProcessIncomingTelegramMessagesCommand extends Command
{
    public function __construct(
        private readonly ProcessIncomingTelegramMessages $processIncomingTelegramMessages,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->processIncomingTelegramMessages->execute();
        } catch (CoreException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
