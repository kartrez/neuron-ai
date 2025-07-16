<?php

namespace NeuronAI\RAG\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use NeuronAI\RAG\Document;
use NeuronAI\RAG\DocumentInterface;
use Ramsey\Uuid\Uuid;

class ChromaVectorStore implements VectorStoreInterface
{
    protected Client $client;

    public function __construct(
        protected string $collection,
        protected string $host = 'http://localhost:8000',
        protected int $topK = 5,
    ) {
    }

    protected function getClient(): Client
    {
        if (isset($this->client)) {
            return $this->client;
        }
        return $this->client = new Client([
            'base_uri' => trim($this->host, '/')."/api/v1/collections/{$this->collection}/",
            'headers' => [
                'Content-Type' => 'application/json',
            ]
        ]);
    }

    /**
     * @param DocumentInterface[] $documents
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function addDocuments(array $documents): void
    {
        $this->getClient()->post('upsert', [
            RequestOptions::JSON => $this->mapDocuments($documents),
        ])->getBody()->getContents();
    }

    /**
     * @param list<float> $embedding
     * @return Document[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function similaritySearch(array $embedding): iterable
    {
        $response = $this->getClient()->post('query', [
            RequestOptions::JSON => [
                'queryEmbeddings' => $embedding,
                'nResults' => $this->topK,
            ]
        ])->getBody()->getContents();

        $response = \json_decode($response, true);

        // Map the result
        $size = \count($response['distances']);
        $result = [];
        for ($i = 0; $i < $size; $i++) {
            $document = (new Document(
                id: $response['ids'][$i] ?? Uuid::uuid4()->toString(),
                content: $response['documents'][$i],
                sourceType: $response['metadatas'][$i]['sourceType'] ?? '',
                sourceName: $response['metadatas'][$i]['sourceName'] ?? '',
            ))
                ->setEmbedding($response['embeddings'][$i])
                ->setScore($response['distances'][$i]);

            foreach ($response['metadatas'][$i] as $name => $value) {
                if (!\in_array($name, ['content', 'sourceType', 'sourceName', 'score', 'embedding', 'id'])) {
                    $document->addMetadata($name, $value);
                }
            }

            $result[] = $document;
        }

        return $result;
    }

    /**
     * @param DocumentInterface[] $documents
     */
    protected function mapDocuments(array $documents): array
    {
        $payload = [
            'ids' => [],
            'documents' => [],
            'embeddings' => [],
            'metadatas' => [],

        ];
        foreach ($documents as $document) {
            $payload['ids'][] = $document->getId();
            $payload['documents'][] = $document->getContent();
            $payload['embeddings'][] = $document->getEmbedding();
            $payload['metadatas'][] = [
                'sourceType' => $document->getSourceType(),
                'sourceName' => $document->getSourceName(),
                ...$document->getMetadata(),
            ];
        }

        return $payload;
    }
}
