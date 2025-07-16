<?php

namespace NeuronAI\RAG\VectorStore;

use NeuronAI\Exceptions\VectorStoreException;
use NeuronAI\RAG\Document;
use NeuronAI\RAG\DocumentInterface;
use NeuronAI\RAG\VectorStore\Search\SimilaritySearch;

class FileVectorStore implements VectorStoreInterface
{
    public function __construct(
        protected string $directory,
        protected int $topK = 4,
        protected string $name = 'neuron',
        protected string $ext = '.store'
    ) {
        if (!\is_dir($this->directory)) {
            throw new VectorStoreException("Directory '{$this->directory}' does not exist");
        }
    }

    protected function getFilePath(): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $this->name.$this->ext;
    }

    /**
     * @param DocumentInterface[] $documents
     */
    public function addDocuments(array $documents): void
    {
        $this->appendToFile(
            \array_map(fn (DocumentInterface $document) => $document->jsonSerialize(), $documents)
        );
    }

    public function similaritySearch(array $embedding): array
    {
        $topItems = [];

        foreach ($this->getLine($this->getFilePath()) as $document) {
            $document = \json_decode($document, true);

            if (empty($document['embedding'])) {
                throw new VectorStoreException("Document with the following content has no embedding: {$document['content']}");
            }
            $dist = $this->cosineSimilarity($embedding, $document['embedding']);

            $topItems[] = compact('dist', 'document');

            \usort($topItems, fn ($a, $b) => $a['dist'] <=> $b['dist']);

            if (\count($topItems) > $this->topK) {
                $topItems = \array_slice($topItems, 0, $this->topK, true);
            }
        }

        return \array_map(function ($item) {
            $document = (new Document(
                id: $item['document']['id'],
                content: $item['document']['content'],
                sourceType: $item['document']['sourceType'],
                sourceName: $item['document']['sourceName'],
            ))
                ->setEmbedding($item['document']['embedding'])
                ->setScore(1 - $item['dist']);

            foreach ($item['document']['metadata'] ?? [] as $name => $value) {
                if (!\in_array($name, ['content', 'sourceType', 'sourceName', 'score', 'embedding', 'id'])) {
                    $document->addMetadata($name, $value);
                }
            }

            return $document;
        }, $topItems);
    }


    protected function cosineSimilarity(array $vector1, array $vector2): float
    {
        return SimilaritySearch::cosine($vector1, $vector2);
    }

    protected function appendToFile(array $documents): void
    {
        \file_put_contents(
            $this->getFilePath(),
            implode(PHP_EOL, \array_map(fn (array $vector) => \json_encode($vector, JSON_UNESCAPED_UNICODE), $documents)).PHP_EOL,
            FILE_APPEND
        );
    }

    protected function getLine($file): \Generator
    {
        $f = fopen($file, 'r');

        try {
            while ($line = fgets($f)) {
                yield $line;
            }
        } finally {
            fclose($f);
        }
    }
}
