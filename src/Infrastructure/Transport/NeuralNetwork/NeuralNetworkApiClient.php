<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork;

use App\Application\Dto\ChatCompletionRequest;
use App\Application\Dto\ChatCompletionResult;
use App\Application\Dto\ChatMessageCollection;
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
use App\Application\Exception\NeuralNetworkValidationException;
use App\Application\Logger\LoggerService;
use App\Application\Port\NeuralNetworkGateway;
use App\Domain\Exception\CoreException;
use App\Infrastructure\Transport\NeuralNetwork\Config\ApiCredentialsDto;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ChatCompletionResultMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\CompatibleModelsListMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\CompletionResultMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\DownloadJobMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\DownloadStatusMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\EmbeddingVectorCollectionMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\LoadModelResultMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\MessagesResultMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\NativeChatResultMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\NativeModelsListMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ResponseResultMapper;
use JsonException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

#[AsAlias(NeuralNetworkGateway::class)]
final readonly class NeuralNetworkApiClient implements NeuralNetworkGateway
{
    public function __construct(
        #[Target('neural_network')]
        private HttpClientInterface $httpClient,
        private ApiCredentialsDto $credentials,
        private SerializerInterface&NormalizerInterface $serializer,
        private LoggerService $logger,
        private NativeModelsListMapper $nativeModelsListMapper,
        private CompatibleModelsListMapper $compatibleModelsListMapper,
        private NativeChatResultMapper $nativeChatResultMapper,
        private LoadModelResultMapper $loadModelResultMapper,
        private DownloadJobMapper $downloadJobMapper,
        private DownloadStatusMapper $downloadStatusMapper,
        private ResponseResultMapper $responseResultMapper,
        private ChatCompletionResultMapper $chatCompletionResultMapper,
        private CompletionResultMapper $completionResultMapper,
        private EmbeddingVectorCollectionMapper $embeddingVectorCollectionMapper,
        private MessagesResultMapper $messagesResultMapper,
    ) {
    }

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     */
    public function listNativeModels(): NeuralNetworkModelCollection
    {
        return $this->run('Не удалось получить список локальных моделей.', function (): NeuralNetworkModelCollection {
            $payload = $this->request(ApiUrlEnum::ListNativeModels, [], 'Failed to list native models.');

            return $this->nativeModelsListMapper->map($payload);
        });
    }

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkValidationException
     */
    public function nativeChat(NativeChatRequest $request): NativeChatResult
    {
        return $this->run('Не удалось выполнить native chat.', function () use ($request): NativeChatResult {
            $this->assertNonBlank($request->model, 'Model identifier must not be blank.');
            $this->assertMessages($request->messages);

            $payload = $this->request(
                ApiUrlEnum::NativeChat,
                ['body' => $this->encodeJson($request)],
                'Failed to run native chat.',
            );

            return $this->nativeChatResultMapper->map($payload);
        });
    }

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkValidationException
     */
    public function loadModel(string $modelId): LoadModelResult
    {
        return $this->run('Не удалось загрузить модель.', function () use ($modelId): LoadModelResult {
            $this->assertNonBlank($modelId, 'Model identifier must not be blank.');

            $payload = $this->request(
                ApiUrlEnum::LoadModel,
                ['body' => $this->encodeJson(['model' => $modelId])],
                'Failed to load model.',
            );

            return $this->loadModelResultMapper->map($payload);
        });
    }

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkValidationException
     */
    public function downloadModel(string $modelKey): DownloadJob
    {
        return $this->run('Не удалось начать загрузку модели.', function () use ($modelKey): DownloadJob {
            $this->assertNonBlank($modelKey, 'Model identifier must not be blank.');

            $payload = $this->request(
                ApiUrlEnum::DownloadModel,
                ['body' => $this->encodeJson(['model' => $modelKey])],
                'Failed to start model download.',
            );

            return $this->downloadJobMapper->map($payload);
        });
    }

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkValidationException
     */
    public function getDownloadStatus(string $jobId): DownloadStatus
    {
        return $this->run('Не удалось получить статус загрузки модели.', function () use ($jobId): DownloadStatus {
            $this->assertNonBlank($jobId, 'Job identifier must not be blank.');

            $payload = $this->request(
                ApiUrlEnum::GetDownloadStatus,
                ['vars' => ['job_id' => $jobId]],
                'Failed to get download status.',
            );

            return $this->downloadStatusMapper->map($payload);
        });
    }

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     */
    public function listModels(): NeuralNetworkModelCollection
    {
        return $this->run('Не удалось получить список совместимых моделей.', function (): NeuralNetworkModelCollection {
            $payload = $this->request(ApiUrlEnum::ListModels, [], 'Failed to list models.');

            return $this->compatibleModelsListMapper->map($payload);
        });
    }

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkValidationException
     */
    public function createResponse(CreateResponseRequest $request): ResponseResult
    {
        return $this->run('Не удалось создать response.', function () use ($request): ResponseResult {
            $this->assertNonBlank($request->model, 'Model identifier must not be blank.');
            $this->assertNonBlank($request->input, 'Input must not be blank.');

            $payload = $this->request(
                ApiUrlEnum::CreateResponse,
                ['body' => $this->encodeJson($request)],
                'Failed to create response.',
            );

            return $this->responseResultMapper->map($payload);
        });
    }

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkValidationException
     */
    public function createChatCompletion(ChatCompletionRequest $request): ChatCompletionResult
    {
        return $this->run('Не удалось создать chat completion.', function () use ($request): ChatCompletionResult {
            $this->assertNonBlank($request->model, 'Model identifier must not be blank.');
            $this->assertMessages($request->messages);

            $payload = $this->request(
                ApiUrlEnum::CreateChatCompletion,
                ['body' => $this->encodeJson($request)],
                'Failed to create chat completion.',
            );

            return $this->chatCompletionResultMapper->map($payload);
        });
    }

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkValidationException
     */
    public function createCompletion(CompletionRequest $request): CompletionResult
    {
        return $this->run('Не удалось создать completion.', function () use ($request): CompletionResult {
            $this->assertNonBlank($request->model, 'Model identifier must not be blank.');
            $this->assertNonBlank($request->prompt, 'Prompt must not be blank.');

            $payload = $this->request(
                ApiUrlEnum::CreateCompletion,
                ['body' => $this->encodeJson($request)],
                'Failed to create completion.',
            );

            return $this->completionResultMapper->map($payload);
        });
    }

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkValidationException
     */
    public function createEmbedding(EmbeddingRequest $request): EmbeddingVectorCollection
    {
        return $this->run('Не удалось создать embeddings.', function () use ($request): EmbeddingVectorCollection {
            $this->assertNonBlank($request->model, 'Model identifier must not be blank.');
            $this->assertNonBlank($request->input, 'Input must not be blank.');

            $payload = $this->request(
                ApiUrlEnum::CreateEmbedding,
                ['body' => $this->encodeJson($request)],
                'Failed to create embeddings.',
            );

            return $this->embeddingVectorCollectionMapper->map($payload);
        });
    }

    /**
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkValidationException
     */
    public function createMessage(MessagesRequest $request): MessagesResult
    {
        return $this->run('Не удалось создать message.', function () use ($request): MessagesResult {
            $this->assertNonBlank($request->model, 'Model identifier must not be blank.');
            $this->assertMessages($request->messages);
            if ($request->maxTokens <= 0) {
                throw new NeuralNetworkValidationException('Max tokens must be greater than zero.');
            }

            $payload = $this->request(
                ApiUrlEnum::CreateMessage,
                ['body' => $this->encodeJson($request)],
                'Failed to create message.',
            );

            return $this->messagesResultMapper->map($payload);
        });
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     *
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     * @throws NeuralNetworkValidationException
     */
    private function run(string $failureMessage, callable $operation): mixed
    {
        try {
            $this->assertConfigured();

            return $operation();
        } catch (CoreException $exception) {
            $this->logger->logException($failureMessage, $exception);

            throw $exception;
        } catch (Throwable $exception) {
            $this->logger->logException($failureMessage, $exception);

            throw new NeuralNetworkTransportException(
                message: $failureMessage,
                previous: $exception,
            );
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     *
     * @throws NeuralNetworkConfigurationException
     * @throws NeuralNetworkTransportException
     */
    private function request(ApiUrlEnum $url, array $options, string $transportFailureMessage): array
    {
        if ($this->credentials->apiKey !== '') {
            $options['auth_bearer'] = $this->credentials->apiKey;
        }

        $this->logger->info('Исходящий запрос к API нейросети', [
            'case' => $url->name,
            'uri' => $url->uri(),
            'has_bearer' => $this->credentials->apiKey !== '',
        ]);

        $response = $this->httpClient->request($url->method(), $url->uri(), $options);

        return $this->decodePayload($response, $transportFailureMessage);
    }

    /**
     * @throws NeuralNetworkConfigurationException
     */
    private function assertConfigured(): void
    {
        if (trim($this->credentials->host) === '') {
            throw new NeuralNetworkConfigurationException('NEURAL_NETWORK_API_HOST must not be empty.');
        }
    }

    /**
     * @throws NeuralNetworkValidationException
     */
    private function assertNonBlank(string $value, string $message): void
    {
        if (trim($value) === '') {
            throw new NeuralNetworkValidationException($message);
        }
    }

    /**
     * @throws NeuralNetworkValidationException
     */
    private function assertMessages(ChatMessageCollection $messages): void
    {
        if ($messages->count() === 0) {
            throw new NeuralNetworkValidationException('Messages must not be empty.');
        }
    }

    /**
     * @param object|array<string, mixed> $data
     *
     * @throws NeuralNetworkTransportException
     */
    private function encodeJson(object|array $data): string
    {
        try {
            return json_encode(
                $this->utf8Value($this->normalizeJson($data)),
                JSON_THROW_ON_ERROR
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $exception) {
            throw new NeuralNetworkTransportException(
                message: 'Failed to encode neural network request body.',
                previous: $exception,
            );
        }
    }

    /**
     * @param object|array<string, mixed> $data
     *
     * @return array<string, mixed>
     *
     * @throws NeuralNetworkTransportException
     */
    private function normalizeJson(object|array $data): array
    {
        $normalized = $this->serializer->normalize($data, 'json', [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);
        if (!is_array($normalized)) {
            throw new NeuralNetworkTransportException('Failed to normalize neural network request body.');
        }

        $result = [];
        foreach ($normalized as $key => $value) {
            if (!is_string($key)) {
                throw new NeuralNetworkTransportException('Normalized neural network request body is not an object.');
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function utf8Value(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->utf8String($value);
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[is_string($key) ? $this->utf8String($key) : $key] = $this->utf8Value($item);
            }

            return $result;
        }

        return $value;
    }

    private function utf8String(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws NeuralNetworkTransportException
     */
    private function decodePayload(ResponseInterface $response, string $transportFailureMessage): array
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new NeuralNetworkTransportException(
                sprintf('%s HTTP status %d.', $transportFailureMessage, $statusCode),
            );
        }

        $payload = $response->toArray();
        if (array_key_exists('error', $payload) && $payload['error'] !== null) {
            throw new NeuralNetworkTransportException($this->errorMessage($payload['error'], $transportFailureMessage));
        }

        $normalized = [];
        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                throw new NeuralNetworkTransportException('Neural network API returned a non-object JSON payload.');
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function errorMessage(mixed $error, string $fallback): string
    {
        if (is_string($error) && $error !== '') {
            return $error;
        }

        if (is_array($error)) {
            $message = $error['message'] ?? null;
            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return $fallback;
    }
}
