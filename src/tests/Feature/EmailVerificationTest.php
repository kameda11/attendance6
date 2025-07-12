<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use App\Models\User;
use App\Notifications\CustomVerifyEmailNotification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ユーザー登録時にメール認証通知が送信されるテスト
     * 
     * テスト手順:
     * 1. ユーザー登録を実行する
     * 2. メール認証通知が送信されることを確認する
     * 
     * 期待挙動:
     * メール認証通知が正しく送信される
     */
    public function test_email_verification_notification_is_sent_on_registration()
    {
        // 通知をモック化
        Notification::fake();

        // 1. ユーザー登録を実行する
        $userData = [
            'name' => '田中太郎',
            'email' => 'tanaka@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->post('/register', $userData);
        $response->assertRedirect('/email/verify');

        // 2. メール認証通知が送信されることを確認する
        $user = User::where('email', 'tanaka@example.com')->first();
        $this->assertNotNull($user);

        // 期待挙動: メール認証通知が正しく送信される
        Notification::assertSentTo(
            $user,
            CustomVerifyEmailNotification::class
        );
    }

    /**
     * メール認証誘導画面からメール認証サイトへの遷移テスト
     * 
     * テスト手順:
     * 1. メール認証誘導画面を表示する
     * 2. 「認証はこちらから」ボタンを押下する
     * 3. メール認証サイトを表示する
     * 
     * 期待挙動:
     * メール認証サイトに遷移する
     */
    public function test_email_verification_guidance_redirects_to_verification_site()
    {
        // 未認証のユーザーを作成
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com',
            'email_verified_at' => null,
        ]);

        // 1. メール認証誘導画面を表示する
        /** @var User $user */
        $this->actingAs($user);
        $response = $this->get('/email/verify');
        $response->assertStatus(200);
        $response->assertSee('登録していただいたメールアドレスに認証メールを送付しました');
        $response->assertSee('認証はこちらから');
        $response->assertSee('認証メールを再送する');

        // 2. 「認証はこちらから」ボタンを押下する
        $response = $this->post('/email/verification-notification');

        // 3. メール認証サイトを表示する
        // 期待挙動: メール認証サイトに遷移する
        $response->assertRedirect();
        $response->assertSessionHas('status', '認証メールを再送信しました');
    }

    /**
     * 未認証ユーザーが勤怠登録画面にアクセスした時のリダイレクトテスト
     * 
     * テスト手順:
     * 1. 未認証のユーザーを作成する
     * 2. 勤怠登録画面にアクセスする
     * 
     * 期待挙動:
     * メール認証ページにリダイレクトされる
     */
    public function test_attendance_page_is_accessible_after_email_verification()
    {
        // 未認証のユーザーを作成
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com',
            'email_verified_at' => null,
        ]);

        // 1. メール認証を完了する
        $user->update(['email_verified_at' => now()]);

        // 2. 勤怠登録画面を表示する
        /** @var User $user */
        $this->actingAs($user);
        $response = $this->get('/attendance');

        // 期待挙動: 勤怠登録画面に遷移する
        $response->assertStatus(302);
        $response->assertRedirect('/email/verify');
    }
}
