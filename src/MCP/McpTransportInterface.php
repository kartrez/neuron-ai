<?php

namespace NeuronAI\MCP;

interface McpTransportInterface
{
    public function initialize(): void;
    public function isConnected(): bool;
    public function send(string $data): void;
    public function receive(): \Generator;
    public function close(): void;
}
