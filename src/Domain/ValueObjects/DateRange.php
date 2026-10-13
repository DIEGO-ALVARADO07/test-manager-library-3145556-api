<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

use DateTimeImmutable;
use DateTimeZone;
use Library\Domain\Exceptions\InvalidDateRange;

/** Calendar days in UTC, both ends inclusive (decision H-11). */
final readonly class DateRange
{
    public DateTimeImmutable $from;

    public DateTimeImmutable $to;

    public function __construct(DateTimeImmutable $from, DateTimeImmutable $to)
    {
        $utc = new DateTimeZone('UTC');
        $this->from = $from->setTimezone($utc)->setTime(0, 0);
        $this->to = $to->setTimezone($utc)->setTime(0, 0);

        if ($this->to < $this->from) {
            throw new InvalidDateRange('La fecha final no puede ser anterior a la inicial.', [
                ['field' => 'to', 'issue' => 'anterior a from'],
            ]);
        }
    }

    public static function fromStrings(string $from, string $to): self
    {
        $utc = new DateTimeZone('UTC');

        return new self(new DateTimeImmutable($from, $utc), new DateTimeImmutable($to, $utc));
    }

    /** First instant included. */
    public function startsAt(): DateTimeImmutable
    {
        return $this->from;
    }

    /** First instant excluded (midnight after the last day). */
    public function endsBefore(): DateTimeImmutable
    {
        return $this->to->modify('+1 day');
    }
}
