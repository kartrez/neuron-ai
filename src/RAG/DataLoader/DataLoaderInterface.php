<?php

namespace NeuronAI\RAG\DataLoader;

use NeuronAI\RAG\DocumentInterface;

interface DataLoaderInterface
{
    /**
     * @return DocumentInterface[]
     */
    public function getDocuments(): array;
}
