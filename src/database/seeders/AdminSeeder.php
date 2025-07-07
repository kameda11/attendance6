<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $admins = [
            [
                'email' => 'admin@example.com',
                'password' => Hash::make('admin123'),
            ],
            [
                'email' => 'admin2@example.com',
                'password' => Hash::make('admin123'),
            ],
            [
                'email' => 'admin3@example.com',
                'password' => Hash::make('admin123'),
            ],
        ];

        foreach ($admins as $adminData) {
            // 既に同じメールアドレスの管理者が存在するかチェック
            $existingAdmin = Admin::where('email', $adminData['email'])->first();

            if (!$existingAdmin) {
                Admin::create($adminData);
            }
        }
    }
}
