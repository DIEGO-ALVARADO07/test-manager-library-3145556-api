<?php

use Library\Domain\Exceptions\InvalidDateRange;
use Library\Domain\Exceptions\InvalidValue;
use Library\Domain\ValueObjects\DateRange;
use Library\Domain\ValueObjects\DocumentNumber;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use Library\Domain\ValueObjects\Uuid;

it('accepts an ISBN of exactly 13 digits — CA-03.2', function () {
    expect((new Isbn('9780307474728'))->value)->toBe('9780307474728');
});

it('rejects an ISBN that is not 13 digits — T-03', function (string $value) {
    new Isbn($value);
})->with(['12 digits' => '978030747472', '14 digits' => '97803074747280', 'letters' => '97803074747AB'])
    ->throws(InvalidValue::class);

it('requires a quantity of at least 1 — CA-03.3', function () {
    expect((new Quantity(1))->value)->toBe(1);
    new Quantity(0);
})->throws(InvalidValue::class);

it('requires a document number', function () {
    new DocumentNumber('   ');
})->throws(InvalidValue::class);

it('accepts a range whose end is not before its start — T-03', function () {
    $range = DateRange::fromStrings('2026-10-01', '2026-10-01');

    expect($range->startsAt()->format('c'))->toBe('2026-10-01T00:00:00+00:00')
        ->and($range->endsBefore()->format('c'))->toBe('2026-10-02T00:00:00+00:00');
});

it('rejects a range whose end is before its start — CA-10.2', function () {
    DateRange::fromStrings('2026-10-05', '2026-10-01');
})->throws(InvalidDateRange::class);

it('generates valid UUID v4 values', function () {
    $id = Uuid::v4();

    expect(Uuid::isValid($id))->toBeTrue()
        ->and($id[14])->toBe('4')
        ->and(Uuid::v4())->not->toBe($id);
});
