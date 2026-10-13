<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Console;

use Illuminate\Console\Command;
use Library\Application\Services\BootstrapAdminService;

/** D-10: creates the initial admin from ADMIN_USERNAME / ADMIN_PASSWORD. Safe to run on every start. */
final class BootstrapAdminCommand extends Command
{
    protected $signature = 'library:bootstrap-admin';

    protected $description = 'Create the initial admin user from the environment (idempotent)';

    public function handle(BootstrapAdminService $service): int
    {
        $username = (string) config('library.admin_username');
        $password = (string) config('library.admin_password');
        if ($username === '' || $password === '') {
            $this->error('ADMIN_USERNAME and ADMIN_PASSWORD must be set.');

            return self::FAILURE;
        }

        $this->info($service->ensure($username, $password) ? 'Admin user created.' : 'Admin user already exists.');

        return self::SUCCESS;
    }
}
