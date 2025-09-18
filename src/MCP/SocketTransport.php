<?php

namespace NeuronAI\MCP;

final class SocketTransport implements McpTransportInterface
{
    private string $host;
    private int $port;
    private int $timeout;
    private array $env;
    private ?\Socket $socket = null;

    public function __construct(array $config)
    {
        $this->host = $config['host'] ?? '127.0.0.1';
        $this->port = $config['port'] ?? 3000;
        $this->timeout = $config['timeout'] ?? 30;
        $this->env = $config['env'] ?? [];
    }

    public function connect(): void
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            throw new McpException('Unable to create socket: ' . socket_strerror(socket_last_error()));
        }

        // Устанавливаем таймаут
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);

        $result = socket_connect($this->socket, $this->host, $this->port);
        if ($result === false) {
            $error = socket_strerror(socket_last_error($this->socket));
            socket_close($this->socket);
            $this->socket = null;
            throw new McpException('Unable to connect to server: ' . $error);
        }
    }

    public function send($data): void
    {
        if ($this->socket === null) {
            throw new McpException('Socket is not connected');
        }
        if (isset($data['params']['arguments'])) {
            $data['params']['arguments']['env'] = $this->env;
        } else {
            $data['env'] = $this->env;
        }


        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($jsonData === false) {
            throw new McpException('Failed to encode request data to JSON');
        }

        $message = $jsonData . "\n";
        $bytesToSend = strlen($message);
        $bytesSent = 0;

        while ($bytesSent < $bytesToSend) {
            $result = socket_write($this->socket, substr($message, $bytesSent));

            if ($result === false) {
                $error = socket_strerror(socket_last_error($this->socket));
                throw new McpException('Failed to write to socket: ' . $error);
            }

            if ($result === 0) {
                throw new McpException('Connection closed by remote host');
            }

            $bytesSent += $result;
        }
    }

    public function receive(): array
    {
        if ($this->socket === null) {
            throw new McpException('Socket is not connected');
        }

        $buffer = "";

        // Читаем до символа новой строки
        while (true) {
            $chunk = socket_read($this->socket, 4096, PHP_BINARY_READ);

            if ($chunk === false) {
                $error = socket_strerror(socket_last_error($this->socket));
                throw new McpException('Failed to read from socket: ' . $error);
            }

            if ($chunk === '') {
                throw new McpException('Connection closed by remote host');
            }

            $buffer .= $chunk;

            // Проверяем наличие полного сообщения
            $pos = strpos($buffer, "\n");
            if ($pos !== false) {
                $response = substr($buffer, 0, $pos);
                break;
            }
        }

        if (empty($response)) {
            throw new McpException('Empty response from MCP server');
        }

        $decoded = json_decode($response, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new McpException('Failed to decode JSON response: ' . json_last_error_msg());
        }

        return $decoded;
    }

    public function disconnect(): void
    {
        if ($this->socket !== null) {
            socket_close($this->socket);
            $this->socket = null;
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
