<?php

namespace NeuronAI\Observability\Events;

use NeuronAI\Chat\Messages\Message;
use NeuronAI\RAG\DocumentInterface;

class VectorStoreResult
{
    /**
     * @param DocumentInterface[] $documents
     */
    public function __construct(
        public Message $question,
        public array $documents,
    ) {
    }
}
