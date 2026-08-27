<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Dto\ChatTurnResult;
use App\Application\Exception\PersistenceException;
use App\Domain\Exception\CoreException;
use Symfony\Component\Uid\Uuid;

interface ChatTurnHandler
{
    public const string PROCESSING_NOTICE = 'Запрос обрабатывается, пожалуйста подождите…';
    public const string ERROR_NEURAL_NETWORK = 'сервис временно не доступен по пробуйте позднее';
    public const string ERROR_RESUME_SESSION = 'такого чата не существует';

    public function isResetCommand(string $text): bool;

    public function isResumeCommand(string $text): bool;

    public function parseResumeSessionId(string $text): ?Uuid;

    public function newSessionId(): Uuid;

    public function newSessionNotice(Uuid $previousSessionId, Uuid $currentSessionId): string;

    public function resumeSessionNotice(Uuid $currentSessionId): string;

    /**
     * @throws CoreException
     */
    public function reply(Uuid $sessionId, string $text): ChatTurnResult;

    /**
     * @throws PersistenceException
     */
    public function rememberTurn(Uuid $sessionId, string $userText, string $assistantText): void;
}
