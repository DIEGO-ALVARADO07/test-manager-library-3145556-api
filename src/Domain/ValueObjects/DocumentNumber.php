<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

use Library\Domain\Exceptions\InvalidValue;

final readonly class DocumentNumber
{
    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 30) {
            throw new InvalidValue('El número de documento es obligatorio y admite hasta 30 caracteres.', [
                ['field' => 'document_number', 'issue' => 'obligatorio, máximo 30 caracteres'],
            ]);
        }
        $this->value = $value;
    }
}
