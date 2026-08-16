<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $fakePassword = 'FinBankLab123!';

        $this->seedUser(1, 'Michael', 'michael@finbank.test', 'user', $fakePassword);
        $this->seedUser(2, 'David', 'david@finbank.test', 'user', $fakePassword);
        $this->seedUser(3, 'Sarah', 'sarah@finbank.test', 'user', $fakePassword);
        $this->seedUser(4, 'James', 'james@finbank.test', 'user', $fakePassword);
        $this->seedUser(5, 'Grace', 'grace@finbank.test', 'user', $fakePassword);

        $this->seedUser(6, 'Admin One', 'admin1@finbank.test', 'admin', $fakePassword);
        $this->seedUser(7, 'Admin Two', 'admin2@finbank.test', 'admin', $fakePassword);
        $this->seedUser(8, 'Admin Three', 'admin3@finbank.test', 'admin', $fakePassword);
    }

    private function seedUser(int $id, string $name, string $email, string $role, string $password): void
    {
        $user = User::find($id);

        if ($user === null) {
            $user = new User();
            $user->id = $id;
        }

        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = $role;
        $user->is_active = true;
        $user->save();
    }
}
