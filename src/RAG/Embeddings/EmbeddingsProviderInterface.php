<?php

namespace NeuronAI\RAG\Embeddings;

use NeuronAI\RAG\DocumentInterface;

interface EmbeddingsProviderInterface
{
    /**
     * @return float[]
     */
    public function embedText(string $text): array;

    public function embedDocument(DocumentInterface $document): DocumentInterface;

    public function embedDocuments(array $documents): array;
}
