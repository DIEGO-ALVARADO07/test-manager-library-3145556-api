<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

use Library\Domain\Exceptions\InvalidValue;

final readonly class Isbn
{
    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if (preg_match('/^[0-9]{13}$/', $value) !== 1) {
            throw new InvalidValue('El ISBN debe tener exactamente 13 dígitos.', [
                ['field' => 'isbn', 'issue' => 'debe tener 13 dígitos'],
            ]);
        }
        $this->value = $value;
    }
}
