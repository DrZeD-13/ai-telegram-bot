<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Config;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ApiCredentialsDto
{
    public function __construct(
        #[Autowire('%env(NEURAL_NETWORK_API_HOST)%')]
        public string $host,
        #[Autowire('%env(NEURAL_NETWORK_API_KEY)%')]
        public string $apiKey,
    ) {
    }
}
