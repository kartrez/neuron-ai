<?php

namespace NeuronAI\RAG\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use NeuronAI\RAG\Document;

final class QdrantVectorStore implements VectorStoreInterface
{
    protected Client $client;
    protected int $topK = 4;

    private function __construct(
        protected string $url,
        protected string $key,
        protected string $collection = 'default'
    ) {
        $this->client = new Client([
            'base_uri' => trim($this->url, '/').'/',
            'headers' => [
                'Content-Type' => 'application/json',
                'api-key' => $this->key,
            ]
        ]);
    }

    public static function create(string $url, string $key, string $collection, int $vectorSize = 1024): self
    {
        $collectionStore = new self($url, $key, $collection);
        $collectionStore->createCollection($vectorSize);

        return $collectionStore;
    }

    protected function createCollection(int $vectorSize): void
    {
        if ($this->hasCollection($this->collection)) {
            return;
        }

        $this->client->put("collections/{$this->collection}", [
            RequestOptions::JSON => [
                'vectors' => [
                    'size' => $vectorSize,
                    'distance' => 'Cosine',
                ]
            ]
        ]);
    }

    protected function hasCollection(string $collection): bool
    {
        $response = $this->client->get("collections/{$collection}");

        return json_decode($response->getBody()->getContents(), true, JSON_THROW_ON_ERROR)['status'] ?? null === 'ok';
    }

    public function addDocument(Document $document): void
    {
        $this->client->put("collections/{$this->collection}/points", [
            RequestOptions::JSON => [
                'points' => [
                    [
                        'id' => $document->getId(),
                        'payload' => [
                            'content' => $document->getContent(),
                            'sourceType' => $document->getSourceType(),
                            'sourceName' => $document->getSourceName(),
                            'metadata' => $document->metadata,
                        ],
                        'vector' => $document->getEmbedding(),
                    ]
                ]
            ]
        ]);
    }

    /**
     * Bulk save documents.
     *
     * @param Document[] $documents
     * @return void
     * @throws GuzzleException
     */
    public function addDocuments(array $documents): void
    {
        $points = \array_map(fn ($document) => [
            'id' => $document->getId(),
            'payload' => [
                'content' => $document->getContent(),
                'sourceType' => $document->getSourceType(),
                'sourceName' => $document->getSourceName(),
                ...$document->metadata,
            ],
            'vector' => $document->getEmbedding(),
        ], $documents);

        $this->client->put("collections/{$this->collection}/points", [
            RequestOptions::JSON => [
                'points' => [
                    ...$points
                ],
            ]
        ]);
    }

    public function similaritySearch(array $embedding): iterable
    {
        $response = $this->client->post("collections/{$this->collection}/points/search", [
            RequestOptions::JSON => [
                'vector' => $embedding,
                'limit' => $this->topK,
                'with_payload' => true,
                'with_vector' => true,
            ]
        ])->getBody()->getContents();

        $response = \json_decode($response, true);

        return \array_map(function (array $item) {
            $document = new Document($item['payload']['content']);
            $document->id = $item['id'];
            $document->embedding = $item['vector'];
            $document->sourceType = $item['payload']['sourceType'];
            $document->sourceName = $item['payload']['sourceName'];
            $document->score = $item['score'];

            foreach ($item['payload'] as $name => $value) {
                if (!\in_array($name, ['content', 'sourceType', 'sourceName', 'score', 'embedding', 'id'])) {
                    $document->addMetadata($name, $value);
                }
            }

            return $document;
        }, $response['result']);
    }
}
