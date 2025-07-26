<?php

namespace NeuronAI\RAG\DataLoader;

use NeuronAI\RAG\Document;

class StringDataLoader extends AbstractDataLoader
{
    public function __construct(
        protected string $content,
        protected string $sourceType = 'text',
        protected string $sourceName = 'manual'
    ) {
        parent::__construct();
    }

    public function getDocuments(): array
    {
        return $this->splitter->splitDocument(
            new Document(content: $this->content, sourceType: $this->sourceType, sourceName: $this->sourceName)
        );
    }
}
