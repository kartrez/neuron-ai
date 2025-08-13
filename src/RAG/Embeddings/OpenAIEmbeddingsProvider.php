<?php

namespace NeuronAI\RAG\Embeddings;

use GuzzleHttp\Client;

class OpenAIEmbeddingsProvider extends AbstractEmbeddingsProvider
{
    protected Client $client;
    protected string $baseUri = 'https://api.openai.com/v1/embeddings';

    public function __construct(
        protected string $key,
        protected string $model,
        protected int $dimensions = 1024,
    ) {
        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->key,
            ]
        ]);
    }

    /**
     * @return array{embedding: float[], total_tokens: int}
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function embedText(string $text): array
    {
        $response = $this->client->post('', [
            'json' => [
                'model' => $this->model,
                'input' => $text,
                'encoding_format' => 'float',
                'dimensions' => $this->dimensions,
            ]
        ]);

        $response = \json_decode($response->getBody()->getContents(), true);

        return [
            'embedding' => $response['data'][0]['embedding'],
            'total_tokens' => $response['usage']['total_tokens']
        ];
    }
}
