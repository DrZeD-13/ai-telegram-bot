<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork;

enum ApiUrlEnum: string
{
    case ListNativeModels = 'GET:/api/v1/models';
    case NativeChat = 'POST:/api/v1/chat';
    case LoadModel = 'POST:/api/v1/models/load';
    case DownloadModel = 'POST:/api/v1/models/download';
    case GetDownloadStatus = 'GET:/api/v1/models/download/status/{job_id}';
    case ListModels = 'GET:/v1/models';
    case CreateResponse = 'POST:/v1/responses';
    case CreateChatCompletion = 'POST:/v1/chat/completions';
    case CreateCompletion = 'POST:/v1/completions';
    case CreateEmbedding = 'POST:/v1/embeddings';
    case CreateMessage = 'POST:/v1/messages';

    public function method(): string
    {
        return explode(':', $this->value, 2)[0];
    }

    public function uri(): string
    {
        return explode(':', $this->value, 2)[1];
    }
}
