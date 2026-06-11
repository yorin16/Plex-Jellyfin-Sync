<?php

namespace App\Message;

final class TranscodeJobMessage
{
    public function __construct(
        public readonly int $transcodeJobId,
    ) {}
}
