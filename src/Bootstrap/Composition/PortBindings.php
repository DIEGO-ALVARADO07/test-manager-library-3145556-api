<?php

declare(strict_types=1);

namespace Library\Bootstrap\Composition;

use Illuminate\Support\ServiceProvider;
use Library\Adapters\Inbound\Console\BootstrapAdminCommand;
use Library\Adapters\Outbound\Persistence\PgBookRepository;
use Library\Adapters\Outbound\Persistence\PgCategoryReader;
use Library\Adapters\Outbound\Persistence\PgLoanRepository;
use Library\Adapters\Outbound\Persistence\PgMemberRepository;
use Library\Adapters\Outbound\Persistence\PgReportReader;
use Library\Adapters\Outbound\Persistence\PgUserRepository;
use Library\Adapters\Outbound\Security\Argon2idPasswordHasher;
use Library\Adapters\Outbound\Security\JwtTokenIssuer;
use Library\Adapters\Outbound\Storage\DiskCoverStorage;
use Library\Adapters\Outbound\Time\SystemClock;
use Library\Application\Ports\BookRepository;
use Library\Application\Ports\CategoryReader;
use Library\Application\Ports\Clock;
use Library\Application\Ports\CoverStorage;
use Library\Application\Ports\LoanRepository;
use Library\Application\Ports\MemberRepository;
use Library\Application\Ports\PasswordHasher;
use Library\Application\Ports\ReportReader;
use Library\Application\Ports\TokenIssuer;
use Library\Application\Ports\UserRepository;

/**
 * Art. III: the ONLY place where a port meets its adapter. Nothing else instantiates infrastructure.
 */
final class PortBindings extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, fn () => new SystemClock((int) config('library.clock_offset_days')));
        $this->app->singleton(PasswordHasher::class, Argon2idPasswordHasher::class);
        $this->app->singleton(TokenIssuer::class, fn ($app) => new JwtTokenIssuer(
            (string) config('library.jwt_secret'),
            (int) config('library.token_ttl_seconds'),
            $app->make(Clock::class),
        ));
        $this->app->singleton(CoverStorage::class, fn () => new DiskCoverStorage((string) config('library.covers_dir')));

        $this->app->bind(BookRepository::class, PgBookRepository::class);
        $this->app->bind(CategoryReader::class, PgCategoryReader::class);
        $this->app->bind(MemberRepository::class, PgMemberRepository::class);
        $this->app->bind(UserRepository::class, PgUserRepository::class);
        $this->app->bind(LoanRepository::class, PgLoanRepository::class);
        $this->app->bind(ReportReader::class, PgReportReader::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapAdminCommand::class]);
        }
    }
}
