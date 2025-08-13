<?php

namespace NeuronAI\RAG\Embeddings;

use NeuronAI\RAG\DocumentInterface;

interface EmbeddingsProviderInterface
{
    /**
     * @return array{embedding: float[], total_tokens: int}
     */
    public function embedText(string $text): array;

    /**
     * @param DocumentInterface $document
     * @return array{document: DocumentInterface, total_tokens: int}
     */
    public function embedDocument(DocumentInterface $document): array;

    /**
     * @return array{documents: list<DocumentInterface>, total_tokens: int}
     */
    public function embedDocuments(array $documents): array;
}
