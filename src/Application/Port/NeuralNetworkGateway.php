<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Dto\ChatCompletionRequest;
use App\Application\Dto\ChatCompletionResult;
use App\Application\Dto\CompletionRequest;
use App\Application\Dto\CompletionResult;
use App\Application\Dto\CreateResponseRequest;
use App\Application\Dto\DownloadJob;
use App\Application\Dto\DownloadStatus;
use App\Application\Dto\EmbeddingRequest;
use App\Application\Dto\EmbeddingVectorCollection;
use App\Application\Dto\LoadModelResult;
use App\Application\Dto\MessagesRequest;
use App\Application\Dto\MessagesResult;
use App\Application\Dto\NativeChatRequest;
use App\Application\Dto\NativeChatResult;
use App\Application\Dto\NeuralNetworkModelCollection;
use App\Application\Dto\ResponseResult;
use App\Application\Exception\NeuralNetworkConfigurationException;
use App\Application\Exception\NeuralNetworkTransportException;
use App\Application\Exception\NeuralNetworkUnsupportedOperationException;
use App\Application\Exception\NeuralNetworkValidationException;

interface NeuralNetworkGateway
{
    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkUnsupportedOperationException
     */
    public function listNativeModels(): NeuralNetworkModelCollection;

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkUnsupportedOperationException
     * @throws NeuralNetworkValidationException
     */
    public function nativeChat(NativeChatRequest $request): NativeChatResult;

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkUnsupportedOperationException
     * @throws NeuralNetworkValidationException
     */
    public function loadModel(string $modelId): LoadModelResult;

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkUnsupportedOperationException
     * @throws NeuralNetworkValidationException
     */
    public function downloadModel(string $modelKey): DownloadJob;

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkUnsupportedOperationException
     * @throws NeuralNetworkValidationException
     */
    public function getDownloadStatus(string $jobId): DownloadStatus;

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkUnsupportedOperationException
     */
    public function listModels(): NeuralNetworkModelCollection;

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkUnsupportedOperationException
     * @throws NeuralNetworkValidationException
     */
    public function createResponse(CreateResponseRequest $request): ResponseResult;

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkUnsupportedOperationException
     * @throws NeuralNetworkValidationException
     */
    public function createChatCompletion(ChatCompletionRequest $request): ChatCompletionResult;

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkUnsupportedOperationException
     * @throws NeuralNetworkValidationException
     */
    public function createCompletion(CompletionRequest $request): CompletionResult;

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkUnsupportedOperationException
     * @throws NeuralNetworkValidationException
     */
    public function createEmbedding(EmbeddingRequest $request): EmbeddingVectorCollection;

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkUnsupportedOperationException
     * @throws NeuralNetworkValidationException
     */
    public function createMessage(MessagesRequest $request): MessagesResult;
}
