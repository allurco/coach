<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('coach.seeder.admin_email');

        if (! $email) {
            $this->command->error('SEEDER_ADMIN_EMAIL is not set in .env — aborting seed.');

            return;
        }

        $name = config('coach.seeder.admin_name');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $password = Str::password(20, symbols: false);

            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $this->command->newLine();
            $this->command->warn('================================================================');
            $this->command->warn('  INITIAL ADMIN CREDENTIALS — copy now, this is shown only once');
            $this->command->warn('================================================================');
            $this->command->info("  Email:    {$email}");
            $this->command->info("  Password: {$password}");
            $this->command->warn('================================================================');
            $this->command->newLine();
        } else {
            $this->command->info("Admin user [{$email}] already exists — password unchanged.");
        }

        $this->call([
            InitialPlanSeeder::class,
        ]);
    }
}
