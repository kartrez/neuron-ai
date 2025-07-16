<?php

namespace NeuronAI\RAG;

use Ramsey\Uuid\Uuid;

final class Document implements DocumentInterface
{
    private readonly string|int $id;

    private array $embedding = [];

    private float $score = 0;

    private array $metadata = [];

    public function __construct(
        string|int $id = '',
        private readonly string $content = '',
        private readonly string $sourceType = 'manual',
        private readonly string $sourceName = 'manual',
    ) {
        $this->id = $id ?: Uuid::uuid4()->toString();
    }

    public function getId(): string|int
    {
        return $this->id;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getEmbedding(): array
    {
        return $this->embedding;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): DocumentInterface
    {
        $this->score = $score;
        return $this;
    }

    public function addMetadata(string $key, string|int $value): DocumentInterface
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function setEmbedding(array $embedding): DocumentInterface
    {
        $this->embedding = $embedding;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'embedding' => $this->embedding,
            'sourceType' => $this->sourceType,
            'sourceName' => $this->sourceName,
            'score' => $this->score,
            'metadata' => $this->metadata,
        ];
    }
}
