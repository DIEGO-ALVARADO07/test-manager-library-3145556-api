<?php

// T-19 — the layering rules of the constitution (Art. I, II and III) as executable tests.

arch('Art. I: the domain depends on nothing but the PHP standard library')
    ->expect('Library\Domain')
    ->not->toUse([
        'Library\Application',
        'Library\Adapters',
        'Library\Bootstrap',
        'Illuminate',
        'Carbon',
        'Firebase',
        'Symfony',
        'App',
    ]);

arch('Art. I: domain classes are strict and final or abstract')
    ->expect('Library\Domain')
    ->toUseStrictTypes();

arch('Art. II: the application layer only knows the domain')
    ->expect('Library\Application')
    ->not->toUse([
        'Library\Adapters',
        'Library\Bootstrap',
        'Illuminate',
        'Firebase',
        'Symfony',
        'App',
    ]);

arch('Art. II: ports are interfaces or plain data holders defined by the application')
    ->expect('Library\Application\Ports')
    ->toBeInterfaces()
    ->ignoring([
        'Library\Application\Ports\IssuedToken',
        'Library\Application\Ports\TokenClaims',
    ]);

arch('Art. III: outbound adapters are only wired by the composition module')
    ->expect('Library\Adapters\Outbound')
    ->toOnlyBeUsedIn([
        'Library\Adapters\Outbound',
        'Library\Bootstrap\Composition',
    ]);

arch('Art. III: inbound adapters never touch outbound adapters directly')
    ->expect('Library\Adapters\Inbound')
    ->not->toUse('Library\Adapters\Outbound');
