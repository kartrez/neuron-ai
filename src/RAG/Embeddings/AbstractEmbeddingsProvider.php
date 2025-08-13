<?php

namespace NeuronAI\RAG\Embeddings;

use NeuronAI\RAG\DocumentInterface;

abstract class AbstractEmbeddingsProvider implements EmbeddingsProviderInterface
{
    /**
     * @return array{documents: list<DocumentInterface>, total_tokens: int}
     */
    public function embedDocuments(array $documents): array
    {
        $totalTokens = 0;
        /** @var DocumentInterface $document */
        foreach ($documents as $index => $document) {
            $embeddedDoc = $this->embedDocument($document);
            $documents[$index] = $embeddedDoc['document'];
            $totalTokens += $embeddedDoc['total_tokens'];
        }

        return [
            'documents' => $documents,
            'total_tokens' => $totalTokens
        ];
    }

    /**
     * @param DocumentInterface $document
     * @return array{document: DocumentInterface, total_tokens: int}
     */
    public function embedDocument(DocumentInterface $document): array
    {
        $text = $document->formattedContent ?? $document->getContent();
        $embedded = $this->embedText($text);
        $document->setEmbedding($embedded['embedding']);

        return [
            'document' => $document,
            'total_tokens' => $embedded['total_tokens']
        ];
    }
}
