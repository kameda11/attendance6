<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Support\Facades\URL;

class UserAuthenticationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * 名前未入力で会員登録した場合のバリデーションメッセージをテスト
     */
    public function test_register_validation_message_for_missing_name()
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください',
        ]);
    }

    /**
     * メールアドレス未入力で会員登録した場合のバリデーションメッセージをテスト
     */
    public function test_register_validation_message_for_missing_email()
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    /**
     * パスワードが8文字未満で会員登録した場合のバリデーションメッセージをテスト
     */
    public function test_register_validation_message_for_short_password()
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'パスワードは8文字以上で入力してください',
        ]);
    }

    /**
     * パスワードとパスワード確認が一致しない場合のバリデーションメッセージをテスト 
     */
    public function test_register_validation_message_for_password_confirmation()
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password456',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'パスワードと一致しません',
        ]);
    }

    /**
     * 有効なユーザー情報で会員登録が成功し、データベースに保存されることをテスト
     */
    public function test_user_can_register_with_valid_data()
    {
        $userData = [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->post('/register', $userData);

        $response->assertRedirect('/email/verify');
        $this->assertDatabaseHas('users', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertNull($user->email_verified_at);
    }
}
