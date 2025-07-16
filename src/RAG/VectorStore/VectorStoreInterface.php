<?php

namespace NeuronAI\RAG\VectorStore;

use NeuronAI\RAG\DocumentInterface;

interface VectorStoreInterface
{
    /**
     * @param  DocumentInterface[]  $documents
     */
    public function addDocuments(array $documents): void;

    /**
     * Return docs most similar to the embedding.
     *
     * @param  float[]  $embedding
     * @return DocumentInterface[]
     */
    public function similaritySearch(array $embedding): iterable;
}
