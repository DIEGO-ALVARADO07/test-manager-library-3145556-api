<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

use Library\Domain\Exceptions\InvalidValue;

final readonly class Quantity
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new InvalidValue('La cantidad de ejemplares debe ser al menos 1.', [
                ['field' => 'total_copies', 'issue' => 'debe ser mayor o igual a 1'],
            ]);
        }
    }
}
