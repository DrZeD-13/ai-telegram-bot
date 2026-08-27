<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork;

use App\Application\Dto\ChatCompletionRequest;
use App\Application\Dto\ChatMessage;
use App\Application\Dto\ChatMessageCollection;
use App\Application\Dto\CompletionRequest;
use App\Application\Dto\CreateResponseRequest;
use App\Application\Dto\EmbeddingRequest;
use App\Application\Dto\MessagesRequest;
use App\Application\Dto\NativeChatRequest;
use App\Application\Dto\ToolDefinition;
use App\Application\Dto\ToolDefinitionCollection;
use App\Application\Exception\NeuralNetworkConfigurationException;
use App\Application\Exception\NeuralNetworkTransportException;
use App\Application\Exception\NeuralNetworkValidationException;
use App\Application\Logger\LoggerService;
use App\Infrastructure\Transport\NeuralNetwork\Config\ApiCredentialsDto;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\AssistantMessageMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ChatCompletionChoiceMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ChatCompletionResultMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\CompatibleModelsListMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\CompletionChoiceMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\CompletionResultMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\DownloadJobMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\DownloadStatusMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\EmbeddingVectorCollectionMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\EmbeddingVectorMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\LoadModelResultMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\MessagesContentBlockMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\MessagesResultMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\NativeChatResultMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\NativeModelsListMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\NeuralNetworkModelMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ResponseOutputTextMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ResponseResultMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ToolCallCollectionMapper;
use App\Infrastructure\Transport\NeuralNetwork\NeuralNetworkApiClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(NeuralNetworkApiClient::class)]
#[CoversMethod(NeuralNetworkApiClient::class, 'listNativeModels')]
#[CoversMethod(NeuralNetworkApiClient::class, 'nativeChat')]
#[CoversMethod(NeuralNetworkApiClient::class, 'loadModel')]
#[CoversMethod(NeuralNetworkApiClient::class, 'downloadModel')]
#[CoversMethod(NeuralNetworkApiClient::class, 'getDownloadStatus')]
#[CoversMethod(NeuralNetworkApiClient::class, 'listModels')]
#[CoversMethod(NeuralNetworkApiClient::class, 'createResponse')]
#[CoversMethod(NeuralNetworkApiClient::class, 'createChatCompletion')]
#[CoversMethod(NeuralNetworkApiClient::class, 'createCompletion')]
#[CoversMethod(NeuralNetworkApiClient::class, 'createEmbedding')]
#[CoversMethod(NeuralNetworkApiClient::class, 'createMessage')]
final class NeuralNetworkApiClientTest extends TestCase
{
    public function testListNativeModelsReturnsModels(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(
                json_encode([
                    'models' => [
                        ['id' => 'local-1', 'object' => 'model', 'owned_by' => 'lmstudio'],
                    ],
                ], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            ),
        ]);

        $models = $this->createClient($httpClient)->listNativeModels();

        self::assertCount(1, $models);
        self::assertSame('local-1', $models->all()[0]->id);
        self::assertSame('model', $models->all()[0]->object);
        self::assertSame('lmstudio', $models->all()[0]->ownedBy);
    }

    public function testListNativeModelsReturnsEmptyCollection(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(
                json_encode(['models' => []], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            ),
        ]);

        self::assertCount(0, $this->createClient($httpClient)->listNativeModels());
    }

    public function testListModelsReturnsEmptyCollection(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(
                json_encode(['object' => 'list', 'data' => []], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            ),
        ]);

        self::assertCount(0, $this->createClient($httpClient)->listModels());
    }

    public function testListModelsReturnsModels(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(
                json_encode([
                    'data' => [
                        ['id' => 'gpt-4', 'object' => 'model', 'owned_by' => 'openai'],
                    ],
                ], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            ),
        ]);

        $models = $this->createClient($httpClient)->listModels();

        self::assertCount(1, $models);
        self::assertSame('gpt-4', $models->all()[0]->id);
    }

    public function testNativeChatReturnsAssistantText(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringContainsString('/api/v1/chat', $url);
            self::assertSame(
                [
                    'model' => 'local-1',
                    'messages' => [['role' => 'user', 'content' => 'hi']],
                ],
                self::jsonBody($options),
            );

            return new MockResponse(
                json_encode(['id' => 'chat-1', 'output' => 'hello'], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $result = $this->createClient($httpClient)->nativeChat(new NativeChatRequest(
            'local-1',
            new ChatMessageCollection(new ChatMessage('user', 'hi')),
        ));

        self::assertSame('chat-1', $result->id);
        self::assertSame('hello', $result->text);
    }

    public function testNativeChatRejectsBlankModelWithoutHttpCall(): void
    {
        $httpClient = $this->failingHttpClient();

        $this->expectException(NeuralNetworkValidationException::class);
        $this->expectExceptionMessageIs('Model identifier must not be blank.');

        $this->createClient($httpClient)->nativeChat(new NativeChatRequest(
            '  ',
            new ChatMessageCollection(new ChatMessage('user', 'hi')),
        ));
    }

    public function testNativeChatRejectsEmptyMessagesWithoutHttpCall(): void
    {
        $httpClient = $this->failingHttpClient();

        $this->expectException(NeuralNetworkValidationException::class);
        $this->expectExceptionMessageIs('Messages must not be empty.');

        $this->createClient($httpClient)->nativeChat(new NativeChatRequest(
            'local-1',
            new ChatMessageCollection(),
        ));
    }

    public function testLoadModelReturnsStatus(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringContainsString('/api/v1/models/load', $url);
            self::assertSame(
                ['model' => 'local-1'],
                self::jsonBody($options),
            );

            return new MockResponse(
                json_encode(['status' => 'loaded', 'message' => 'ok'], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $result = $this->createClient($httpClient)->loadModel('local-1');

        self::assertSame('loaded', $result->status);
        self::assertSame('ok', $result->message);
    }

    public function testLoadModelRejectsBlankIdWithoutHttpCall(): void
    {
        $this->expectException(NeuralNetworkValidationException::class);
        $this->expectExceptionMessageIs('Model identifier must not be blank.');

        $this->createClient($this->failingHttpClient())->loadModel("\n");
    }

    public function testDownloadModelReturnsJob(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringContainsString('/api/v1/models/download', $url);
            self::assertSame(
                ['model' => 'org/model'],
                self::jsonBody($options),
            );

            return new MockResponse(
                json_encode(['job_id' => 'job-1', 'status' => 'queued'], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $job = $this->createClient($httpClient)->downloadModel('org/model');

        self::assertSame('job-1', $job->jobId);
        self::assertSame('queued', $job->status);
    }

    public function testDownloadModelRejectsBlankKeyWithoutHttpCall(): void
    {
        $this->expectException(NeuralNetworkValidationException::class);
        $this->expectExceptionMessageIs('Model identifier must not be blank.');

        $this->createClient($this->failingHttpClient())->downloadModel('');
    }

    public function testGetDownloadStatusReturnsStatus(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('GET', $method);
            self::assertStringContainsString('/api/v1/models/download/status/{job_id}', $url);
            self::assertSame(['job_id' => 'job-1'], $options['vars']);

            return new MockResponse(
                json_encode(['job_id' => 'job-1', 'status' => 'completed'], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $status = $this->createClient($httpClient)->getDownloadStatus('job-1');

        self::assertSame('job-1', $status->jobId);
        self::assertSame('completed', $status->status);
    }

    public function testGetDownloadStatusRejectsBlankJobIdWithoutHttpCall(): void
    {
        $this->expectException(NeuralNetworkValidationException::class);
        $this->expectExceptionMessageIs('Job identifier must not be blank.');

        $this->createClient($this->failingHttpClient())->getDownloadStatus(' ');
    }

    public function testCreateResponseReturnsResult(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringContainsString('/v1/responses', $url);
            self::assertSame(
                ['model' => 'gpt-4', 'input' => 'hi', 'stream' => false],
                self::jsonBody($options),
            );

            return new MockResponse(
                json_encode(['id' => 'resp-1', 'output_text' => 'there'], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $result = $this->createClient($httpClient)->createResponse(new CreateResponseRequest('gpt-4', 'hi'));

        self::assertSame('resp-1', $result->id);
        self::assertSame('there', $result->text);
    }

    public function testCreateResponseRejectsBlankInputWithoutHttpCall(): void
    {
        $this->expectException(NeuralNetworkValidationException::class);
        $this->expectExceptionMessageIs('Input must not be blank.');

        $this->createClient($this->failingHttpClient())->createResponse(new CreateResponseRequest('gpt-4', ''));
    }

    public function testCreateChatCompletionSendsNonStreamingBody(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringContainsString('/v1/chat/completions', $url);
            self::assertSame(
                [
                    'model' => 'gpt-4',
                    'messages' => [['role' => 'user', 'content' => 'hi']],
                    'stream' => false,
                ],
                self::jsonBody($options),
            );

            return new MockResponse(
                json_encode([
                    'id' => 'cmpl-1',
                    'choices' => [
                        ['message' => ['role' => 'assistant', 'content' => 'hello']],
                    ],
                ], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $result = $this->createClient($httpClient)->createChatCompletion(new ChatCompletionRequest(
            'gpt-4',
            new ChatMessageCollection(new ChatMessage('user', 'hi')),
        ));

        self::assertSame('cmpl-1', $result->id);
        self::assertSame('hello', $result->text);
        self::assertFalse($result->hasToolCalls());
    }

    public function testCreateChatCompletionEncodesInvalidUtf8InsteadOfFailing(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            $body = self::jsonBody($options);
            self::assertSame('gpt-4', $body['model']);
            self::assertIsArray($body['messages']);
            self::assertArrayHasKey(0, $body['messages']);
            $message = $body['messages'][0];
            self::assertIsArray($message);
            self::assertIsString($message['content']);
            self::assertTrue(mb_check_encoding($message['content'], 'UTF-8'));
            self::assertStringStartsWith('hi', $message['content']);

            return new MockResponse(
                json_encode([
                    'id' => 'cmpl-utf8',
                    'choices' => [
                        ['message' => ['role' => 'assistant', 'content' => 'ok']],
                    ],
                ], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $result = $this->createClient($httpClient)->createChatCompletion(new ChatCompletionRequest(
            'gpt-4',
            new ChatMessageCollection(new ChatMessage('user', "hi\xFF\xFEbroken")),
        ));

        self::assertSame('cmpl-utf8', $result->id);
        self::assertSame('ok', $result->text);
    }

    public function testCreateChatCompletionSendsToolsAndMapsToolCalls(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            $body = self::jsonBody($options);
            self::assertSame('gpt-4', $body['model']);
            self::assertIsArray($body['messages']);
            self::assertArrayHasKey(0, $body['messages']);
            $userMessage = $body['messages'][0];
            self::assertIsArray($userMessage);
            self::assertSame('привет меня зовут Павел, а тебя как?', $userMessage['content']);
            self::assertIsArray($body['tools']);
            self::assertArrayHasKey(0, $body['tools']);
            $firstTool = $body['tools'][0];
            self::assertIsArray($firstTool);
            self::assertSame('function', $firstTool['type']);
            self::assertIsArray($firstTool['function']);
            self::assertSame('shell', $firstTool['function']['name']);
            self::assertSame(
                'Выполнить команду в shell (оболочке) хоста и получить stdout, stderr и код возврата.',
                $firstTool['function']['description'],
            );

            return new MockResponse(
                json_encode([
                    'id' => 'cmpl-tools',
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => null,
                                'tool_calls' => [
                                    [
                                        'id' => 'call_1',
                                        'type' => 'function',
                                        'function' => ['name' => 'shell', 'arguments' => '{"command":"ls"}'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $result = $this->createClient($httpClient)->createChatCompletion(new ChatCompletionRequest(
            model: 'gpt-4',
            messages: new ChatMessageCollection(new ChatMessage('user', 'привет меня зовут Павел, а тебя как?')),
            tools: new ToolDefinitionCollection(new ToolDefinition(
                name: 'shell',
                description: 'Выполнить команду в shell (оболочке) хоста и получить stdout, stderr и код возврата.',
                parameters: ['type' => 'object'],
            )),
        ));

        self::assertSame('cmpl-tools', $result->id);
        self::assertTrue($result->hasToolCalls());
        self::assertNotNull($result->toolCalls);
        self::assertSame('call_1', $result->toolCalls->all()[0]->id);
        self::assertSame('shell', $result->toolCalls->all()[0]->name);
    }

    public function testCreateChatCompletionRejectsEmptyMessagesWithoutHttpCall(): void
    {
        $this->expectException(NeuralNetworkValidationException::class);
        $this->expectExceptionMessageIs('Messages must not be empty.');

        $this->createClient($this->failingHttpClient())->createChatCompletion(new ChatCompletionRequest(
            'gpt-4',
            new ChatMessageCollection(),
        ));
    }

    public function testCreateCompletionReturnsText(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringContainsString('/v1/completions', $url);
            self::assertSame(
                ['model' => 'gpt-4', 'prompt' => 'Once', 'stream' => false],
                self::jsonBody($options),
            );

            return new MockResponse(
                json_encode([
                    'id' => 'cmpl-2',
                    'choices' => [['text' => ' upon a time']],
                ], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $result = $this->createClient($httpClient)->createCompletion(new CompletionRequest('gpt-4', 'Once'));

        self::assertSame('cmpl-2', $result->id);
        self::assertSame(' upon a time', $result->text);
    }

    public function testCreateCompletionRejectsBlankPromptWithoutHttpCall(): void
    {
        $this->expectException(NeuralNetworkValidationException::class);
        $this->expectExceptionMessageIs('Prompt must not be blank.');

        $this->createClient($this->failingHttpClient())->createCompletion(new CompletionRequest('gpt-4', '  '));
    }

    public function testCreateEmbeddingReturnsVectors(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringContainsString('/v1/embeddings', $url);
            self::assertSame(
                ['model' => 'emb-1', 'input' => 'hello'],
                self::jsonBody($options),
            );

            return new MockResponse(
                json_encode([
                    'data' => [
                        ['embedding' => [0.1, 1, -0.5]],
                    ],
                ], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $vectors = $this->createClient($httpClient)->createEmbedding(new EmbeddingRequest('emb-1', 'hello'));

        self::assertCount(1, $vectors);
        self::assertSame([0.1, 1.0, -0.5], $vectors->all()[0]->values);
    }

    public function testCreateEmbeddingRejectsBlankInputWithoutHttpCall(): void
    {
        $this->expectException(NeuralNetworkValidationException::class);
        $this->expectExceptionMessageIs('Input must not be blank.');

        $this->createClient($this->failingHttpClient())->createEmbedding(new EmbeddingRequest('emb-1', ''));
    }

    public function testCreateMessageSendsMaxTokensAndStreamFalse(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringContainsString('/v1/messages', $url);
            self::assertSame(
                [
                    'model' => 'claude',
                    'messages' => [['role' => 'user', 'content' => 'hi']],
                    'max_tokens' => 32,
                    'stream' => false,
                ],
                self::jsonBody($options),
            );

            return new MockResponse(
                json_encode([
                    'id' => 'msg-1',
                    'content' => [['type' => 'text', 'text' => 'hey']],
                ], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $result = $this->createClient($httpClient)->createMessage(new MessagesRequest(
            'claude',
            new ChatMessageCollection(new ChatMessage('user', 'hi')),
            32,
        ));

        self::assertSame('msg-1', $result->id);
        self::assertSame('hey', $result->text);
    }

    public function testCreateMessageRejectsNonPositiveMaxTokensWithoutHttpCall(): void
    {
        $this->expectException(NeuralNetworkValidationException::class);
        $this->expectExceptionMessageIs('Max tokens must be greater than zero.');

        $this->createClient($this->failingHttpClient())->createMessage(new MessagesRequest(
            'claude',
            new ChatMessageCollection(new ChatMessage('user', 'hi')),
            0,
        ));
    }

    public function testEmptyHostFailsWithoutHttpCall(): void
    {
        $this->expectException(NeuralNetworkConfigurationException::class);
        $this->expectExceptionMessageIs('NEURAL_NETWORK_API_HOST must not be empty.');

        $this->createClient($this->failingHttpClient(), host: '')->listNativeModels();
    }

    public function testHttpFailureIsTransportError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('gateway timeout', ['http_code' => 504]),
        ]);

        $this->expectException(NeuralNetworkTransportException::class);
        $this->expectExceptionMessageIs('Failed to list native models. HTTP status 504.');

        $this->createClient($httpClient)->listNativeModels();
    }

    public function testProviderErrorPayloadIsTransportError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(
                json_encode(['error' => ['message' => 'model not found']], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            ),
        ]);

        $this->expectException(NeuralNetworkTransportException::class);
        $this->expectExceptionMessageIs('model not found');

        $this->createClient($httpClient)->listNativeModels();
    }

    public function testUnexpectedThrowableIsWrapped(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{not-json', ['http_code' => 200]),
        ]);

        try {
            $this->createClient($httpClient)->listNativeModels();
            self::fail('Expected a transport exception.');
        } catch (NeuralNetworkTransportException $exception) {
            self::assertSame('Не удалось получить список локальных моделей.', $exception->getMessage());
            self::assertNotNull($exception->getPrevious());
        }
    }

    public function testEmptyKeyDoesNotSendAuthorization(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertArrayNotHasKey('auth_bearer', $options);
            self::assertSame([], self::authorizationHeaders($options));

            return new MockResponse(
                json_encode(['models' => []], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $this->createClient($httpClient, apiKey: '')->listNativeModels();
    }

    public function testNonEmptyKeySendsBearer(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $authorization = self::authorizationHeaders($options);
            self::assertNotSame([], $authorization);
            self::assertStringContainsString('Bearer secret-key', $authorization[0]);

            return new MockResponse(
                json_encode(['models' => []], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $this->createClient($httpClient, apiKey: 'secret-key')->listNativeModels();
    }

    /**
     * @param array<mixed> $options
     *
     * @return array<mixed>
     */
    private static function jsonBody(array $options): array
    {
        self::assertIsString($options['body']);
        $decoded = json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param array<mixed> $options
     *
     * @return list<string>
     */
    private static function authorizationHeaders(array $options): array
    {
        $headers = $options['normalized_headers'] ?? [];
        self::assertIsArray($headers);
        $authorization = $headers['authorization'] ?? [];
        self::assertIsArray($authorization);

        $values = [];
        foreach ($authorization as $header) {
            self::assertIsString($header);
            $values[] = $header;
        }

        return $values;
    }

    private function failingHttpClient(): MockHttpClient
    {
        return new MockHttpClient(static function (): MockResponse {
            self::fail('HTTP must not be called.');
        });
    }

    private function createClient(
        HttpClientInterface $httpClient,
        string $host = 'http://127.0.0.1:1234',
        string $apiKey = '',
    ): NeuralNetworkApiClient {
        $serializer = $this->createSerializer();
        $modelMapper = new NeuralNetworkModelMapper();
        $choiceMapper = new ChatCompletionChoiceMapper(new AssistantMessageMapper());

        return new NeuralNetworkApiClient(
            $httpClient,
            new ApiCredentialsDto($host, $apiKey),
            $serializer,
            new LoggerService(new NullLogger(), $serializer),
            new NativeModelsListMapper($modelMapper),
            new CompatibleModelsListMapper($modelMapper),
            new NativeChatResultMapper($choiceMapper),
            new LoadModelResultMapper(),
            new DownloadJobMapper(),
            new DownloadStatusMapper(),
            new ResponseResultMapper(new ResponseOutputTextMapper()),
            new ChatCompletionResultMapper($choiceMapper, new ToolCallCollectionMapper()),
            new CompletionResultMapper(new CompletionChoiceMapper()),
            new EmbeddingVectorCollectionMapper(new EmbeddingVectorMapper()),
            new MessagesResultMapper(new MessagesContentBlockMapper()),
        );
    }

    private function createSerializer(): Serializer
    {
        $objectNormalizer = new ObjectNormalizer(
            new ClassMetadataFactory(new AttributeLoader()),
            new CamelCaseToSnakeCaseNameConverter(),
            null,
            new ReflectionExtractor(),
        );

        return new Serializer(
            [new JsonSerializableNormalizer(), $objectNormalizer],
            [new JsonEncoder()],
        );
    }
}
