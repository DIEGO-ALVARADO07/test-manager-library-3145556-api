<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Library\Domain\Exceptions\BookHasOpenLoans;
use Library\Domain\Exceptions\BookNotAvailable;
use Library\Domain\Exceptions\DocumentTaken;
use Library\Domain\Exceptions\DomainError;
use Library\Domain\Exceptions\DuplicateBookInLoan;
use Library\Domain\Exceptions\Forbidden;
use Library\Domain\Exceptions\InvalidCredentials;
use Library\Domain\Exceptions\InvalidDateRange;
use Library\Domain\Exceptions\InvalidValue;
use Library\Domain\Exceptions\IsbnTaken;
use Library\Domain\Exceptions\ItemAlreadyReturned;
use Library\Domain\Exceptions\MemberHasOverdue;
use Library\Domain\Exceptions\MemberLimitExceeded;
use Library\Domain\Exceptions\NotFound;
use Library\Domain\Exceptions\UsernameTaken;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The only place that turns exceptions into the single error body {code, message, details}
 * (api-contract §1, RNF-4). Nothing internal (trace, SQL, paths) ever reaches the client.
 */
final class ErrorMapper
{
    /** @var array<class-string<DomainError>, array{0: string, 1: int}> */
    private const DOMAIN = [
        InvalidCredentials::class => ['INVALID_CREDENTIALS', 401],
        Forbidden::class => ['FORBIDDEN_ROLE', 403],
        NotFound::class => ['NOT_FOUND', 404],
        UsernameTaken::class => ['USERNAME_TAKEN', 409],
        IsbnTaken::class => ['ISBN_TAKEN', 409],
        DocumentTaken::class => ['DOCUMENT_TAKEN', 409],
        BookHasOpenLoans::class => ['BOOK_HAS_OPEN_LOANS', 409],
        BookNotAvailable::class => ['BOOK_NOT_AVAILABLE', 409],
        MemberHasOverdue::class => ['MEMBER_HAS_OVERDUE', 409],
        ItemAlreadyReturned::class => ['ITEM_ALREADY_RETURNED', 409],
        MemberLimitExceeded::class => ['MEMBER_LIMIT_EXCEEDED', 422],
        DuplicateBookInLoan::class => ['DUPLICATE_BOOK_IN_LOAN', 422],
        InvalidValue::class => ['VALIDATION_ERROR', 422],
        InvalidDateRange::class => ['INVALID_DATE_RANGE', 422],
    ];

    public static function respond(Throwable $e): JsonResponse
    {
        if ($e instanceof DomainError) {
            [$code, $status] = self::DOMAIN[$e::class] ?? ['VALIDATION_ERROR', 422];

            return self::body($code, $e->getMessage(), $e->details(), $status);
        }
        if ($e instanceof ValidationException) {
            $details = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $details[] = ['field' => (string) $field, 'issue' => $message];
                }
            }

            return self::body('VALIDATION_ERROR', 'Los datos enviados no son válidos.', $details, 422);
        }
        if ($e instanceof AuthenticationException) {
            return self::body('INVALID_CREDENTIALS', 'Autenticación requerida o sesión vencida.', [], 401);
        }
        if ($e instanceof NotFoundHttpException || $e instanceof MethodNotAllowedHttpException || $e instanceof ModelNotFoundException) {
            return self::body('NOT_FOUND', 'El recurso solicitado no existe.', [], 404);
        }
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return self::body('VALIDATION_ERROR', 'La petición no es válida.', [], 422);
        }

        return self::body('INTERNAL_ERROR', 'Ocurrió un error interno. Intente de nuevo más tarde.', [], 500);
    }

    /** @param list<array{field: string, issue: string}> $details */
    private static function body(string $code, string $message, array $details, int $status): JsonResponse
    {
        return new JsonResponse(['code' => $code, 'message' => $message, 'details' => $details], $status);
    }
}
