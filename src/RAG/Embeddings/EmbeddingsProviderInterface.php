<?php

namespace NeuronAI\RAG\Embeddings;

use NeuronAI\RAG\DocumentInterface;

interface EmbeddingsProviderInterface
{
    /**
     * @return array{prompt_tokens: int, total_tokens: int}
     */
    public function getUsage(): array;

    /**
     * @return float[]
     */
    public function embedText(string $text): array;

    public function embedDocument(DocumentInterface $document): DocumentInterface;

    public function embedDocuments(array $documents): array;
}
