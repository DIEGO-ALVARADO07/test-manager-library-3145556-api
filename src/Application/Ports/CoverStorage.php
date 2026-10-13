<?php

declare(strict_types=1);

namespace Library\Application\Ports;

interface CoverStorage
{
    public function put(string $key, string $contents): void;

    public function get(string $key): ?string;

    public function delete(string $key): void;
}
