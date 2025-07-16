<?php

namespace NeuronAI\RAG\Embeddings;

use NeuronAI\RAG\DocumentInterface;

abstract class AbstractEmbeddingsProvider implements EmbeddingsProviderInterface
{
    public function embedDocuments(array $documents): array
    {
        /** @var DocumentInterface $document */
        foreach ($documents as $index => $document) {
            $documents[$index] = $this->embedDocument($document);
        }

        return $documents;
    }

    public function embedDocument(DocumentInterface $document): DocumentInterface
    {
        $text = $document->formattedContent ?? $document->getContent();
        $document->setEmbedding($this->embedText($text));

        return $document;
    }
}
