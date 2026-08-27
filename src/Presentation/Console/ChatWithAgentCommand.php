<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Exception\PersistenceException;
use App\Application\Port\ChatTurnHandler;
use App\Domain\Exception\CoreException;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'agent:chat',
    description: 'Интерактивный диалог с ИИ-агентом в консоли: как в Telegram, /new начинает новую сессию',
)]
final class ChatWithAgentCommand extends Command
{
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
            'UUID сохранённой сессии. По умолчанию создаётся новая',
        );
    }

    /**
     * @throws CoreException
     * @throws PersistenceException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $sessionId = $this->sessionId($input);
        } catch (InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->title('Диалог с ИИ-агентом');
        $this->writeSession($io, $sessionId);
        $io->writeln('Пишите сообщение и нажимайте Enter. Ответ придёт так же, как в Telegram.');
        $io->writeln('/new — новая сессия (история старой сохраняется). /open <uuid> — вернуться. /exit — выход.');
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

            $text = trim($this->utf8String($raw));
            if ($text === '') {
                continue;
            }

            if ($this->isExitCommand($raw) || $this->isExitCommand($text)) {
                return Command::SUCCESS;
            }

            if ($this->handleChatTurn->isResetCommand($raw) || $this->handleChatTurn->isResetCommand($text)) {
                $previous = $sessionId;
                $sessionId = $this->handleChatTurn->newSessionId();
                $io->writeln($this->handleChatTurn->newSessionNotice($previous, $sessionId));
                $io->newLine();

                continue;
            }

            if ($this->handleChatTurn->isResumeCommand($raw) || $this->handleChatTurn->isResumeCommand($text)) {
                $opened = $this->handleChatTurn->parseResumeSessionId($raw);
                if ($opened === null) {
                    $opened = $this->handleChatTurn->parseResumeSessionId($text);
                }
                if ($opened === null) {
                    $io->writeln(ChatTurnHandler::ERROR_RESUME_SESSION);
                    $io->newLine();

                    continue;
                }

                $sessionId = $opened;
                $io->writeln($this->handleChatTurn->resumeSessionNotice($sessionId));
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

    private function sessionId(InputInterface $input): Uuid
    {
        $value = $input->getOption('session-id');
        if ($value === null || $value === '') {
            return $this->handleChatTurn->newSessionId();
        }

        if (!is_string($value) || !Uuid::isValid($value)) {
            throw new InvalidArgumentException('Опция --session-id должна быть UUID сохранённой сессии.');
        }

        return Uuid::fromString($value);
    }

    private function writeSession(SymfonyStyle $io, Uuid $sessionId): void
    {
        $io->writeln(sprintf('Сессия: %s', $sessionId->toRfc4122()));
    }

    private function isExitCommand(string $text): bool
    {
        $token = $this->slashCommandToken($text);

        return $token === '/exit' || $token === '/quit';
    }

    private function slashCommandToken(string $text): string
    {
        $text = trim($text);
        if (str_starts_with($text, "\u{FEFF}")) {
            $text = substr($text, strlen("\u{FEFF}"));
        }
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        $text = strtolower($text);
        if ($text === '' || !str_starts_with($text, '/')) {
            return $text;
        }

        $ascii = preg_replace('/[^a-z0-9\/@_]/', '', $text);

        return is_string($ascii) ? $ascii : $text;
    }

    private function utf8String(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
