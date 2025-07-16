<?php

namespace NeuronAI\RAG\VectorStore\Doctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use NeuronAI\RAG\DocumentInterface;

#[ORM\MappedSuperclass]
class DoctrineEmbeddingEntityBase implements DocumentInterface
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    public int $id;

    #[ORM\Column(type: VectorType::VECTOR, length: 3072)]
    public array $embedding = [];
    #[ORM\Column(type: Types::JSON)]
    protected array $metadata = [];

    #[ORM\Column(type: Types::FLOAT)]
    protected float $score = 0.0;

    public function __construct(
        #[ORM\Column(type: Types::TEXT)]
        public string $content,
        #[ORM\Column(type: Types::TEXT)]
        public string $sourceType = 'manual',
        #[ORM\Column(type: Types::TEXT)]
        public string $sourceName = 'manual',
        #[ORM\Column(type: Types::INTEGER)]
        public int $chunkNumber = 0,
    ) {
    }

    public function getId(): string|int
    {
        return $this->id;
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

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function addMetadata(string $key, int|string $value): DocumentInterface
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function setEmbedding(array $embedding): DocumentInterface
    {
        $this->embedding = $embedding;
        return $this;
    }

    public function setScore(float $score): DocumentInterface
    {
        $this->score = $score;
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
            'metadata' => $this->metadata,
        ];
    }
}
