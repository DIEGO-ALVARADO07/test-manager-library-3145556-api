<?php

declare(strict_types=1);

namespace Library\Domain;

use Library\Domain\Exceptions\InvalidValue;
use Library\Domain\ValueObjects\DocumentNumber;

final class Member
{
    public readonly string $fullName;

    public function __construct(
        public readonly string $id,
        string $fullName,
        public readonly DocumentNumber $document,
    ) {
        $fullName = trim($fullName);
        if ($fullName === '' || mb_strlen($fullName) > 200) {
            throw new InvalidValue('El nombre es obligatorio y admite hasta 200 caracteres.', [
                ['field' => 'full_name', 'issue' => 'obligatorio, máximo 200 caracteres'],
            ]);
        }
        $this->fullName = $fullName;
    }
}
