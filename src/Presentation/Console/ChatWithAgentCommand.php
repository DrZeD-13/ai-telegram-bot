<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Exception\PersistenceException;
use App\Application\Port\ChatTurnHandler;
use App\Domain\Exception\CoreException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'agent:chat',
    description: 'Интерактивный диалог с ИИ-агентом в консоли: как в Telegram, /new сбрасывает сессию',
)]
final class ChatWithAgentCommand extends Command
{
    private const int DEFAULT_SESSION_ID = 0;

    public function __construct(
        private readonly ChatTurnHandler $handleChatTurn,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'session-id',
            null,
            InputOption::VALUE_REQUIRED,
            'Идентификатор сессии диалога (история отдельно от Telegram-чатов при значении 0)',
            (string) self::DEFAULT_SESSION_ID,
        );
    }

    /**
     * @throws CoreException
     * @throws PersistenceException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sessionId = $this->sessionId($input);

        $io->title('Диалог с ИИ-агентом');
        $io->writeln('Пишите сообщение и нажимайте Enter. Ответ придёт так же, как в Telegram.');
        $io->writeln('/new — сброс сессии. /exit — выход. Ctrl+D — выход.');
        $io->newLine();

        /** @phpstan-ignore while.alwaysTrue */
        while (true) {
            try {
                $raw = $io->ask('Вы');
            } catch (MissingInputException) {
                $io->newLine();

                return Command::SUCCESS;
            }

            if ($raw === null) {
                return Command::SUCCESS;
            }

            if (!is_string($raw)) {
                continue;
            }

            $text = trim($raw);
            if ($text === '') {
                continue;
            }

            if ($this->isExitCommand($text)) {
                return Command::SUCCESS;
            }

            if ($this->handleChatTurn->isResetCommand($text)) {
                $this->handleChatTurn->resetSession($sessionId);
                $io->writeln(ChatTurnHandler::RESET_NOTICE);
                $io->newLine();

                continue;
            }

            $io->writeln(ChatTurnHandler::PROCESSING_NOTICE);

            $result = $this->handleChatTurn->reply($sessionId, $text);
            foreach ($result->messages as $message) {
                $io->writeln($message->text);
            }

            if (!$result->failed && $result->assistantText !== null) {
                $this->handleChatTurn->rememberTurn($sessionId, $text, $result->assistantText);
            }

            $io->newLine();
        }
    }

    private function sessionId(InputInterface $input): int
    {
        $value = $input->getOption('session-id');
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return self::DEFAULT_SESSION_ID;
    }

    private function isExitCommand(string $text): bool
    {
        $normalized = strtolower($text);

        return $normalized === '/exit' || $normalized === '/quit';
    }
}
