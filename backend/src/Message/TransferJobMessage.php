<?php

namespace App\Message;

final class TransferJobMessage
{
    public function __construct(
        public readonly int $transferJobId,
    ) {}
}
