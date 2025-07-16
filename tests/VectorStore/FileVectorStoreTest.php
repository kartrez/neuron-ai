<?php

namespace NeuronAI\Tests\VectorStore;

use NeuronAI\RAG\Document;
use NeuronAI\RAG\VectorStore\FileVectorStore;
use PHPUnit\Framework\TestCase;

class FileVectorStoreTest extends TestCase
{
    public function test_store_documents()
    {
        $document = new Document(
            id: 1,
            content: 'Hello!',
            sourceType: 'test',
            sourceName: 'string'
        );
        $document->addMetadata('customProperty', 'customValue');
        $document->setEmbedding([1, 2, 3]);

        $document2 = new Document(id: 2, content: 'Hello 2!', sourceType: 'test', sourceName: 'string');
        $document2->setEmbedding([3, 4, 5]);

        $store = new FileVectorStore(__DIR__, 1);
        $store->addDocuments([$document, $document2]);

        $results = $store->similaritySearch([1, 2, 3]);

        $this->assertCount(1, $results);
        $this->assertEquals($document->getId(), $results[0]->getId());
        $this->assertEquals($document->getContent(), $results[0]->getContent());
        $this->assertEquals($document->getEmbedding(), $results[0]->getEmbedding());
        $this->assertEquals($document->getSourceType(), $results[0]->getSourceType());
        $this->assertEquals($document->getSourceName(), $results[0]->getSourceName());
        $this->assertEquals($document->getMetadata(), $results[0]->getMetadata());

        unlink(__DIR__.'/neuron.store');
    }
}
