<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use App\Notifications\CustomVerifyEmailNotification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;


    public function test_email_verification_notification_is_sent_on_registration()
    {
        Notification::fake();

        $userData = [
            'name' => '田中太郎',
            'email' => 'tanaka@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->post('/register', $userData);
        $response->assertRedirect('/email/verify');

        $user = User::where('email', 'tanaka@example.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo(
            $user,
            CustomVerifyEmailNotification::class
        );
    }


    public function test_email_verification_guidance_redirects_to_verification_site()
    {
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com',
            'email_verified_at' => null,
        ]);

        /** @var User $user */
        $this->actingAs($user);
        $response = $this->get('/email/verify');
        $response->assertStatus(200);
        $response->assertSee('登録していただいたメールアドレスに認証メールを送付しました');
        $response->assertSee('認証はこちらから');
        $response->assertSee('認証メールを再送する');

        $response = $this->post('/email/verification-notification');

        $response->assertRedirect();
        $response->assertSessionHas('status', '認証メールを再送信しました');
    }


    public function test_attendance_page_is_accessible_after_email_verification()
    {
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com',
            'email_verified_at' => null,
        ]);

        $user->update(['email_verified_at' => now()]);

        /** @var User $user */
        $this->actingAs($user);
        $response = $this->get('/attendance');

        $response->assertStatus(302);
        $response->assertRedirect('/email/verify');
    }
}
