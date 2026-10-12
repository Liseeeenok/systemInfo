<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdminUser extends Command
{
    protected $signature = 'systeminfo:admin
                            {--email= : login email}
                            {--name= : display name}
                            {--password= : password (a strong one is generated when omitted)}';

    protected $description = 'Create or update the dashboard login user';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email', 'admin@legion.local');
        $name = $this->option('name') ?: $this->ask('Name', 'Administrator');
        $password = $this->option('password') ?: Str::password(18);
        $generated = ! $this->option('password');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password],
        );

        $this->info("User {$user->email} is ready.");
        if ($generated) {
            $this->newLine();
            $this->line('Generated password (change it after the first login):');
            $this->line('  '.$password);
        }

        return self::SUCCESS;
    }
}
