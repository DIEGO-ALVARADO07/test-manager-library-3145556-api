<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Storage;

use Library\Application\Ports\CoverStorage;

/** Stores covers as files named by an opaque key inside a private directory (never served directly). */
final class DiskCoverStorage implements CoverStorage
{
    public function __construct(private readonly string $directory)
    {
    }

    public function put(string $key, string $contents): void
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0775, true) && ! is_dir($this->directory)) {
            throw new \RuntimeException('Cover directory is not writable.');
        }
        file_put_contents($this->path($key), $contents);
    }

    public function get(string $key): ?string
    {
        $path = $this->path($key);

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function path(string $key): string
    {
        // Keys are generated UUIDs; anything else never reaches the file system.
        return rtrim($this->directory, '/\\').DIRECTORY_SEPARATOR.preg_replace('/[^a-f0-9-]/i', '', $key);
    }
}
