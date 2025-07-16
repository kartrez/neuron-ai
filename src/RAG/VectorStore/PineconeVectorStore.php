<?php

namespace NeuronAI\RAG\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use NeuronAI\RAG\Document;
use NeuronAI\RAG\DocumentInterface;

class PineconeVectorStore implements VectorStoreInterface
{
    protected Client $client;

    /**
     * Metadata filters.
     *
     * https://docs.pinecone.io/reference/api/2025-04/data-plane/query#body-filter
     *
     * @var array
     */
    protected array $filters = [];

    public function __construct(
        string $key,
        protected string $indexUrl,
        protected int $topK = 4,
        string $version = '2025-04',
        protected string $namespace = '__default__'
    ) {
        $this->client = new Client([
            'base_uri' => trim($this->indexUrl, '/').'/',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Api-Key' => $key,
                'X-Pinecone-API-Version' => $version,
            ]
        ]);
    }

    /**
     * @param DocumentInterface[] $documents
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function addDocuments(array $documents): void
    {
        $this->client->post("vectors/upsert", [
            RequestOptions::JSON => [
                'namespace' => $this->namespace,
                'vectors' => \array_map(fn (DocumentInterface $document) => [
                    'id' => $document->getId(),
                    'values' => $document->getEmbedding(),
                    'metadata' => [
                        'content' => $document->getContent(),
                        'sourceType' => $document->getSourceType(),
                        'sourceName' => $document->getSourceName(),
                        ...$document->getMetadata(),
                    ],
                ], $documents)
            ]
        ]);
    }

    /**
     * @param list<float> $embedding
     * @return Document[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function similaritySearch(array $embedding): iterable
    {
        $result = $this->client->post("query", [
            RequestOptions::JSON => [
                'namespace' => $this->namespace,
                'includeMetadata' => true,
                'vector' => $embedding,
                'topK' => $this->topK,
                'filters' => $this->filters, // Hybrid search
            ]
        ])->getBody()->getContents();

        $result = \json_decode($result, true);

        return \array_map(function (array $item) {
            $document = (new Document(
                id: $item['id'],
                content: $item['metadata']['content'],
                sourceType: $item['metadata']['sourceType'],
                sourceName: $item['metadata']['sourceName'],
            ))
                ->setEmbedding($item['values'])
                ->setScore($item['score']);

            foreach ($item['metadata'] as $name => $value) {
                if (!\in_array($name, ['content', 'sourceType', 'sourceName', 'score', 'embedding', 'id'])) {
                    $document->addMetadata($name, $value);
                }
            }

            return $document;
        }, $result['matches']);
    }

    public function withFilters(array $filters): self
    {
        $this->filters = $filters;
        return $this;
    }
}
