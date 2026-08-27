<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Dto\ChatTurnResult;
use App\Application\Exception\PersistenceException;
use App\Domain\Exception\CoreException;

interface ChatTurnHandler
{
    public const string PROCESSING_NOTICE = 'Запрос обрабатывается, пожалуйста подождите…';
    public const string RESET_NOTICE = 'Сессия сброшена. Можете начать новый диалог.';
    public const string ERROR_NEURAL_NETWORK = 'сервис временно не доступен по пробуйте позднее';

    public function isResetCommand(string $text): bool;

    /**
     * @throws CoreException
     */
    public function resetSession(int $sessionId): void;

    /**
     * @throws CoreException
     */
    public function reply(int $sessionId, string $text): ChatTurnResult;

    /**
     * @throws PersistenceException
     */
    public function rememberTurn(int $sessionId, string $userText, string $assistantText): void;
}
