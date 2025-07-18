<?php

namespace NeuronAI\RAG\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use NeuronAI\RAG\Document;
use NeuronAI\RAG\DocumentInterface;

final class QdrantVectorStore implements VectorStoreInterface
{
    protected Client $client;

    public function __construct(
        protected string $url,
        protected string $key,
        protected int $topK = 4,
        protected int $vectorSize = 1024,
    ) {
        $this->client = new Client([
            'base_uri' => trim($this->url, '/').'/',
            'headers' => [
                'Content-Type' => 'application/json',
                'api-key' => $this->key,
            ]
        ]);
    }

    protected function createCollection(string $collection): void
    {
        if ($this->hasCollection($collection)) {
            return;
        }

        $this->client->put("collections/{$collection}", [
            RequestOptions::JSON => [
                'vectors' => [
                    'size' => $this->vectorSize,
                    'distance' => 'Cosine',
                ]
            ]
        ]);
    }

    protected function hasCollection(string $collection): bool
    {
        try {
            $response = $this->client->get("collections/{$collection}");

            return json_decode($response->getBody()->getContents(), true, JSON_THROW_ON_ERROR)['status'] ?? null === 'ok';
        } catch (GuzzleException) {
            return false;
        }
    }

    /**
     * Bulk save documents.
     *
     * @param DocumentInterface[] $documents
     * @return void
     * @throws GuzzleException
     */
    public function addDocuments(array $documents, string $collection = 'default'): void
    {
        $this->createCollection($collection);

        $points = \array_map(fn (DocumentInterface $document) => [
            'id' => $document->getId(),
            'payload' => [
                'content' => $document->getContent(),
                'sourceType' => $document->getSourceType(),
                'sourceName' => $document->getSourceName(),
                ...$document->getMetadata(),
            ],
            'vector' => $document->getEmbedding(),
        ], $documents);

        $this->client->put("collections/{$collection}/points", [
            RequestOptions::JSON => [
                'points' => [
                    ...$points
                ],
            ]
        ]);
    }

    public function similaritySearch(array $embedding, string $collection = 'default'): iterable
    {
        $this->createCollection($collection);

        $response = $this->client->post("collections/{$collection}/points/search", [
            RequestOptions::JSON => [
                'vector' => $embedding,
                'limit' => $this->topK,
                'with_payload' => true,
                'with_vector' => true,
            ]
        ])->getBody()->getContents();

        $response = \json_decode($response, true);

        return \array_map(function (array $item) {
            $document = (new Document(
                id: $item['id'],
                content: $item['payload']['content'],
                sourceType: $item['payload']['sourceType'],
                sourceName: $item['payload']['sourceName'],
            ))
                ->setEmbedding($item['vector'])
                ->setScore($item['score']);

            foreach ($item['payload'] as $name => $value) {
                if (!\in_array($name, ['content', 'sourceType', 'sourceName', 'score', 'embedding', 'id'])) {
                    $document->addMetadata($name, $value);
                }
            }

            return $document;
        }, $response['result']);
    }
}
