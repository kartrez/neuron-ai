<?php

namespace NeuronAI\RAG\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use NeuronAI\RAG\Document;
use NeuronAI\RAG\DocumentInterface;
use Ramsey\Uuid\Uuid;

class MeilisearchVectorStore implements VectorStoreInterface
{
    protected Client $client;

    public function __construct(
        string $indexUid,
        string $host = 'http://localhost:7700',
        ?string $key = null,
        protected string $embedder = 'default',
        protected int $topK = 5,
    ) {
        $this->client = new Client([
            'base_uri' => trim($host, '/').'/indexes/'.$indexUid.'/',
            'headers' => [
                'Content-Type' => 'application/json',
                ...(!is_null($key) ? ['Authorization' => "Bearer {$key}"] : [])
            ]
        ]);

        try {
            $this->client->get('');
        } catch (\Exception $exception) {
            $this->client->post(trim($host, '/').'/indexes/', [
                RequestOptions::JSON => [
                    'uid' => $indexUid,
                    'primaryKey' => 'id',
                ]
            ]);
        }
    }

    /**
     * @param DocumentInterface[] $documents
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function addDocuments(array $documents): void
    {
        $this->client->put('documents', [
            RequestOptions::JSON => \array_map(function (DocumentInterface $document) {
                return [
                    'id' => $document->getId(),
                    'content' => $document->getContent(),
                    'sourceType' => $document->getSourceType(),
                    'sourceName' => $document->getSourceName(),
                    ...$document->getMetadata(),
                    '_vectors' => [
                        'default' => [
                            'embeddings' => $document->getEmbedding(),
                            'regenerate' => false,
                        ],
                    ]
                ];
            }, $documents),
        ]);
    }

    /**
     * @return Document[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function similaritySearch(array $embedding): iterable
    {
        $response = $this->client->post('search', [
            RequestOptions::JSON => [
                'vector' => $embedding,
                'limit' => min($this->topK, 20),
                'retrieveVectors' => true,
                'showRankingScore' => true,
                'hybrid' => [
                    'semanticRatio' => 1.0,
                    'embedder' => $this->embedder,
                ],
            ]
        ])->getBody()->getContents();

        $response = \json_decode($response, true);

        return \array_map(function (array $item) {
            $document = (new Document(
                id: $item['id'] ?? Uuid::uuid4()->toString(),
                content: $item['content'],
                sourceType: $item['sourceType'] ?? '',
                sourceName: $item['sourceName'] ?? '',
            ))
                ->setEmbedding($item['_vectors']['default']['embeddings'])
                ->setScore($item['_rankingScore']);

            foreach ($item as $name => $value) {
                if (!\in_array($name, ['_vectors', '_rankingScore', 'content', 'sourceType', 'sourceName', 'score', 'embedding', 'id'])) {
                    $document->addMetadata($name, $value);
                }
            }

            return $document;
        }, $response['hits']);
    }
}
