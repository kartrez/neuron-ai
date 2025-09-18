<?php

namespace NeuronAI\MCP;

use NeuronAI\StaticConstructor;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolInterface;

class McpConnector
{
    use StaticConstructor;

    protected McpClient $client;

    public function __construct(array $config, McpTransportInterface $transport = null)
    {
        $this->client = new McpClient($config, $transport);
    }

    /**
     * Get the list of available Tools from the server.
     *
     * @return ToolInterface[]
     * @throws \Exception
     */
    public function tools(): array
    {
        $tools = $this->client->listTools();

        return \array_map(fn ($tool) => $this->createTool($tool), $tools);
    }

    /**
     * Convert the list of tools from the MCP server to Neuron compatible entities.
     */
    protected function createTool(array $item): ToolInterface
    {
        $client = $this->client; // Создаем локальную ссылку для использования в замыкании

        $tool = Tool::make(
            name: $item['name'],
            description: $item['description'] ?? ''
        )->setCallable(function (...$arguments) use ($item, $client) {
            // Преобразуем аргументы в ассоциативный массив
            $args = [];
            if (isset($item['inputSchema']['properties'])) {
                $keys = array_keys($item['inputSchema']['properties']);
                foreach ($keys as $index => $key) {
                    if (isset($arguments[$index])) {
                        $args[$key] = $arguments[$index];
                    }
                }
            }
            $response = $client->callTool($item['name'], $args);
            if (\array_key_exists('error', $response)) {
                throw new McpException($response['error']['message']);
            }

            $content = $response['result']['content'][0] ?? null;
            if (!$content) {
                throw new McpException("Empty tool response");
            }
            if ($content['type'] === 'text') {
                return $content['text'];
            }
            if ($content['type'] === 'image') {
                return $content;
            }

            throw new McpException("Tool response format not supported: {$content['type']}");
        });

        if (isset($item['inputSchema']['properties'])) {
            foreach ($item['inputSchema']['properties'] as $name => $input) {
                $required = \in_array($name, $item['inputSchema']['required'] ?? []);
                $types = \is_array($input['type'] ?? 'string') ? $input['type'] : [$input['type'] ?? 'string'];

                foreach ($types as $type) {
                    try {
                        $type = PropertyType::from($type);
                        break;
                    } catch (\Throwable $e) {
                        // Игнорируем ошибки и используем STRING по умолчанию
                    }
                }

                $property = new ToolProperty(
                    name: $name,
                    type: $type ?? PropertyType::STRING,
                    description: $input['description'] ?? '',
                    required: $required,
                    enum: $input['items']['enum'] ?? []
                );

                $tool->addProperty($property);
            }
        }

        return $tool;
    }
}
