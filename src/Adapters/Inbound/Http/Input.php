<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Format-level validation of request data, with Spanish messages. Business rules live in the domain. */
final class Input
{
    private const MESSAGES = [
        'required' => 'El campo :attribute es obligatorio.',
        'string' => 'El campo :attribute debe ser texto.',
        'integer' => 'El campo :attribute debe ser un entero.',
        'array' => 'El campo :attribute debe ser una lista.',
        'uuid' => 'El campo :attribute debe ser un UUID válido.',
        'in' => 'El valor de :attribute no es válido.',
        'date_format' => 'El campo :attribute debe ser una fecha con formato AAAA-MM-DD.',
        'max' => 'El campo :attribute excede el máximo permitido (:max).',
        'min' => 'El campo :attribute no alcanza el mínimo permitido (:min).',
        'between' => 'El campo :attribute debe estar entre :min y :max.',
    ];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @return array<string, mixed>
     */
    public static function validate(array $data, array $rules): array
    {
        return Validator::make($data, $rules, self::MESSAGES)->validate();
    }

    /** @return array{0: int, 1: int} page and size (size at most 50) */
    public static function pagination(Request $request): array
    {
        $data = self::validate($request->query(), [
            'page' => 'nullable|integer|min:1',
            'size' => 'nullable|integer|min:1|max:50',
        ]);

        return [(int) ($data['page'] ?? 1), (int) ($data['size'] ?? 20)];
    }
}
