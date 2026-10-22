<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateBureauUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-bureau-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update a bureau user without exposing the password in command arguments';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = trim((string) $this->ask('Nom complet'));
        $email = strtolower(trim((string) $this->ask('Adresse email')));
        $password = (string) $this->secret('Mot de passe');
        $confirmation = (string) $this->secret('Confirmer le mot de passe');

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Le nom et une adresse email valide sont obligatoires.');

            return self::FAILURE;
        }

        if (strlen($password) < 12 || $password !== $confirmation) {
            $this->error('Le mot de passe doit contenir au moins 12 caractères et les deux saisies doivent correspondre.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)],
        );
        if ($user->roleNames() === []) {
            $user->assignRoles(['admin']);
        }

        $this->info("Le compte du bureau {$email} est prêt.");

        return self::SUCCESS;
    }
}
