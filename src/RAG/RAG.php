<?php

namespace NeuronAI\RAG;

use NeuronAI\Agent;
use NeuronAI\AgentInterface;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\SystemMessage;
use NeuronAI\Exceptions\AgentException;
use NeuronAI\Observability\Events\PostProcessed;
use NeuronAI\Observability\Events\PostProcessing;
use NeuronAI\Observability\Events\VectorStoreResult;
use NeuronAI\Observability\Events\VectorStoreSearching;
use NeuronAI\Exceptions\MissingCallbackParameter;
use NeuronAI\Exceptions\ToolCallableNotSet;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\RAG\PostProcessor\PostProcessorInterface;

/**
 * @method RAG withProvider(AIProviderInterface $provider)
 */
class RAG extends Agent
{
    use ResolveVectorStore;
    use ResolveEmbeddingProvider;

    /**
     * @var PostprocessorInterface[]
     */
    protected array $postProcessors = [];

    /**
     * @param Message $question
     * @param list<SystemMessage|AssistantMessage> $messages
     * @return Message
     * @throws \Throwable
     */
    public function answer(Message $question, array $messages = []): Message
    {
        $this->notify('rag-start');

        $this->retrieval($question);
        $messages[] = $question;
        $response = $this->chat($messages);

        $this->notify('rag-stop');
        return $response;
    }

    public function streamAnswer(Message $question): \Generator
    {
        $this->notify('rag-start');

        $this->retrieval($question);

        yield from $this->stream($question);

        $this->notify('rag-stop');
    }

    protected function retrieval(Message $question): void
    {
        $this->withDocumentsContext(
            $this->retrieveDocuments($question)
        );
    }

    /**
     * Set the system message based on the context.
     *
     * @param DocumentInterface[] $documents
     */
    public function withDocumentsContext(array $documents): AgentInterface
    {
        $originalInstructions = $this->instructions();

        // Remove the old context to avoid infinite grow
        $newInstructions = $this->removeDelimitedContent($originalInstructions, '<EXTRA-CONTEXT>', '</EXTRA-CONTEXT>');

        $newInstructions .= '<EXTRA-CONTEXT>';
        foreach ($documents as $document) {
            $newInstructions .= $document->getContent().PHP_EOL.PHP_EOL;
        }
        $newInstructions .= '</EXTRA-CONTEXT>';

        $this->withInstructions(\trim($newInstructions));

        return $this;
    }

    /**
     * Retrieve relevant documents from the vector store.
     *
     * @return DocumentInterface[]
     */
    public function retrieveDocuments(Message $question, string $collection = 'default'): array
    {
        $this->notify('rag-vectorstore-searching', new VectorStoreSearching($question));

        $documents = $this->resolveVectorStore()->similaritySearch(
            $this->resolveEmbeddingsProvider()->embedText($question->getContent()), $collection
        );

        $retrievedDocs = [];

        foreach ($documents as $document) {
            //md5 for removing duplicates
            $retrievedDocs[\md5($document->getContent())] = $document;
        }

        $retrievedDocs = \array_values($retrievedDocs);

        $this->notify('rag-vectorstore-result', new VectorStoreResult($question, $retrievedDocs));

        return $this->applyPostProcessors($question, $retrievedDocs);
    }

    /**
     * Apply a series of postprocessors to the retrieved documents.
     *
     * @param Message $question The question to process the documents for.
     * @param DocumentInterface[] $documents The documents to process.
     * @return DocumentInterface[] The processed documents.
     */
    protected function applyPostProcessors(Message $question, array $documents): array
    {
        foreach ($this->postProcessors() as $processor) {
            $this->notify('rag-postprocessing', new PostProcessing($processor::class, $question, $documents));
            $documents = $processor->process($question, $documents);
            $this->notify('rag-postprocessed', new PostProcessed($processor::class, $question, $documents));
        }

        return $documents;
    }

    /**
     * Feed the vector store with documents.
     *
     * @param DocumentInterface[] $documents
     * @return void
     */
    public function addDocuments(array $documents, string $collection = 'default'): void
    {
        $this->resolveVectorStore()->addDocuments(
            $this->resolveEmbeddingsProvider()->embedDocuments($documents), $collection
        );
    }

    /**
     * @throws AgentException
     */
    public function setPostProcessors(array $postProcessors): RAG
    {
        foreach ($postProcessors as $processor) {
            if (! $processor instanceof PostProcessorInterface) {
                throw new AgentException($processor::class." must implement PostProcessorInterface");
            }

            $this->postProcessors[] = $processor;
        }

        return $this;
    }

    /**
     * @return PostProcessorInterface[]
     */
    protected function postProcessors(): array
    {
        return $this->postProcessors;
    }
}
