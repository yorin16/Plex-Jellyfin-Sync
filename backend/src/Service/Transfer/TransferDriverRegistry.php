<?php

namespace App\Service\Transfer;

class TransferDriverRegistry
{
    /** @var TransferDriverInterface[] */
    private array $drivers = [];

    public function register(TransferDriverInterface $driver): void
    {
        $this->drivers[$driver->getName()] = $driver;
    }

    public function get(string $method): TransferDriverInterface
    {
        if (!isset($this->drivers[$method])) {
            throw new \InvalidArgumentException("No transfer driver registered for method '{$method}'");
        }
        return $this->drivers[$method];
    }
}
