<?php

namespace NeuronAI\RAG;

interface DocumentInterface extends \JsonSerializable
{
    public function getId(): string|int;
    public function getContent(): string;

    public function getEmbedding(): array;
    public function getSourceType(): string;

    public function getSourceName(): string;

    public function getScore(): float;

    public function getMetadata(): array;

    public function addMetadata(string $key, string|int $value): DocumentInterface;

    public function setEmbedding(array $embedding): DocumentInterface;

    public function setScore(float $score): DocumentInterface;
}
