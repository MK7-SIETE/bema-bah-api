<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// Intentionally does nothing.
//
// This project runs against the LIVE database, and the old seeder created accounts whose passwords
// were written in the code, plus sample cars and messages. Accounts are now created like this:
//   - the super admin: by the first login (see AuthController::superLogin), only when none exists
//   - dealers and rental dealers: in the Super admin panel
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn('Nothing to seed. Accounts are created in the Super admin panel.');
    }
}
