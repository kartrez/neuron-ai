<?php

namespace NeuronAI\Chat\Messages;

use NeuronAI\Chat\Enums\MessageRole;

final class SystemMessage extends Message
{
    public function __construct(array|string|int|float|null $content)
    {
        parent::__construct(MessageRole::SYSTEM, $content);
    }
}
