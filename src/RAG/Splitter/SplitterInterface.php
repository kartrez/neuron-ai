<?php

namespace NeuronAI\RAG\Splitter;

use NeuronAI\RAG\DocumentInterface;

interface SplitterInterface
{
    /**
     * @param  DocumentInterface  $document
     * @return array<DocumentInterface>
     */
    public function splitDocument(DocumentInterface $document): array;

    /**
     * @param  array<DocumentInterface>  $documents
     * @return array<DocumentInterface>
     */
    public function splitDocuments(array $documents): array;
}
