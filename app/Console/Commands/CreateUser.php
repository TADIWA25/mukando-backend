<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateUser extends Command
{
    protected $signature = 'mukando:create-user
                            {--name= : The user\'s full name}
                            {--phone= : The user\'s phone number (e.g. 263771234567)}
                            {--password= : The user\'s password}';

    protected $description = 'Create a new Mukando user';

    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('Full name');
        $phone = $this->option('phone') ?? $this->ask('Phone number (e.g. 263771234567)');
        $password = $this->option('password') ?? $this->secret('Password');

        $validator = Validator::make(
            ['name' => $name, 'phone' => $phone, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'unique:users,phone'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'phone' => $phone,
            'password' => Hash::make($password),
        ]);

        $this->info('User created successfully.');
        $this->table(['ID', 'Name', 'Phone'], [[$user->id, $user->name, $user->phone]]);

        return self::SUCCESS;
    }
}
