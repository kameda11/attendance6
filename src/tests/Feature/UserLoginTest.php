<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Tests\TestCase;

class UserLoginTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * メールアドレス未入力でログインした場合のバリデーションメッセージをテスト
     */
    public function test_login_validation_message_for_missing_email()
    {
        // 1. ユーザー登録
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // 2. メールアドレス以外のユーザー情報を入力してログイン処理
        $response = $this->post('/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        // 3. 期待挙動: 「メールアドレスを入力してください」というバリデーションメッセージが表示される
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    /**
     * パスワード未入力でログインした場合のバリデーションメッセージをテスト
     */
    public function test_login_validation_message_for_missing_password()
    {
        // 1. ユーザー登録
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // 2. パスワード以外のユーザー情報を入力してログイン処理
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => '',
        ]);

        // 3. 期待挙動: 「パスワードを入力してください」というバリデーションメッセージが表示される
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    /**
     * 誤ったメールアドレスでログインした場合のバリデーションメッセージをテスト
     */
    public function test_login_validation_message_for_wrong_email()
    {
        // 1. ユーザー登録
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // 2. 誤ったメールアドレスのユーザー情報を入力してログイン処理
        $response = $this->post('/login', [
            'email' => 'wrong@example.com',
            'password' => 'password123',
        ]);

        // 3. 期待挙動: 「ログイン情報が登録されていません」というバリデーションメッセージが表示される
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);
    }
}
 