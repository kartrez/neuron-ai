<?php

namespace NeuronAI\Providers\Anthropic;

use GuzzleHttp\Exception\GuzzleException;
use NeuronAI\Chat\Messages\Message;

trait HandleStructured
{
    /**
     * @param array<Message> $messages
     * @param string $class
     * @param array $response_format
     * @return Message
     * @throws GuzzleException
     */
    public function structured(
        array $messages,
        string $class,
        array $response_format
    ): Message {
        $this->system .= PHP_EOL."# OUTPUT CONSTRAINTS".PHP_EOL.
            "Your response should be a JSON string following this schema: ".PHP_EOL.
            \json_encode($response_format, JSON_UNESCAPED_UNICODE);

        return $this->chat($messages);
    }
}
