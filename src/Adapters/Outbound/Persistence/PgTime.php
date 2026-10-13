<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Persistence;

use DateTimeImmutable;
use DateTimeZone;

/** Conversions between PHP instants and PostgreSQL timestamptz values (always UTC). */
final class PgTime
{
    public static function parse(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
    }

    public static function parseRequired(string $value): DateTimeImmutable
    {
        return self::parse($value) ?? throw new \LogicException('Unexpected null timestamp.');
    }

    public static function format(?DateTimeImmutable $value): ?string
    {
        return $value?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    /** Escapes LIKE wildcards so user text is matched literally. */
    public static function likeContains(string $text): string
    {
        $backslash = chr(92);

        return '%'.str_replace([$backslash, '%', '_'], [$backslash.$backslash, $backslash.'%', $backslash.'_'], $text).'%';
    }
}
