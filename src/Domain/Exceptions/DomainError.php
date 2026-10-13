<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

/**
 * Base class of every business-rule violation. Only the HTTP adapter translates
 * these into the single error body of the API contract.
 */
abstract class DomainError extends \RuntimeException
{
    /** @param list<array{field: string, issue: string}> $details */
    public function __construct(string $message, private readonly array $details = [])
    {
        parent::__construct($message);
    }

    /** @return list<array{field: string, issue: string}> */
    public function details(): array
    {
        return $this->details;
    }
}
