<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Admin;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_all_users_name_and_email()
    {
        $admin = Admin::factory()->create();
        $user1 = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);
        $user2 = User::factory()->create([
            'name' => '佐藤花子',
            'email' => 'sato@example.com'
        ]);
        $user3 = User::factory()->create([
            'name' => '鈴木一郎',
            'email' => 'suzuki@example.com'
        ]);

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $response = $this->get('/admin/users');
        $response->assertStatus(200);

        $response->assertSee('田中太郎');
        $response->assertSee('tanaka@example.com');
        $response->assertSee('佐藤花子');
        $response->assertSee('sato@example.com');
        $response->assertSee('鈴木一郎');
        $response->assertSee('suzuki@example.com');
    }

    public function test_admin_can_view_user_attendance_list()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);

        $currentMonth = now();
        $attendance1 = $user->attendances()->create([
            'clock_in_time' => $currentMonth->copy()->setDay(15)->setTime(9, 0),
            'clock_out_time' => $currentMonth->copy()->setDay(15)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $currentMonth->copy()->setDay(15),
        ]);

        $attendance2 = $user->attendances()->create([
            'clock_in_time' => $currentMonth->copy()->setDay(16)->setTime(8, 30),
            'clock_out_time' => $currentMonth->copy()->setDay(16)->setTime(17, 30),
            'status' => 'completed',
            'created_at' => $currentMonth->copy()->setDay(16),
        ]);

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $response = $this->get('/admin/user/' . $user->id . '/attendances');
        $response->assertStatus(200);

        $response->assertSee('田中太郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('08:30');
        $response->assertSee('17:30');
    }

    public function test_admin_can_view_previous_month_attendance()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);

        $previousMonth = now()->subMonth();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $previousMonth->copy()->setDay(15)->setTime(9, 0),
            'clock_out_time' => $previousMonth->copy()->setDay(15)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $previousMonth->copy()->setDay(15),
        ]);

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $response = $this->get('/admin/attendances');
        $response->assertStatus(200);

        $previousDate = now()->subDay();
        $response = $this->get("/admin/attendances?date={$previousDate->format('Y-m-d')}");
        $response->assertStatus(200);

        $response->assertSee($previousDate->format('Y年m月d日'));
        $response->assertSee('田中太郎');
    }

    public function test_admin_can_view_next_month_attendance()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);

        $nextMonth = now()->addMonth();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $nextMonth->copy()->setDay(15)->setTime(9, 0),
            'clock_out_time' => $nextMonth->copy()->setDay(15)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $nextMonth->copy()->setDay(15),
        ]);

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $response = $this->get('/admin/attendances');
        $response->assertStatus(200);

        $nextDate = now()->addDay();
        $response = $this->get("/admin/attendances?date={$nextDate->format('Y-m-d')}");
        $response->assertStatus(200);

        $response->assertSee($nextDate->format('Y年m月d日'));
        $response->assertSee('田中太郎');
    }


    public function test_admin_can_navigate_to_attendance_detail()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);

        $today = now();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $today->copy()->setTime(9, 0),
            'clock_out_time' => $today->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $today,
        ]);

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $response = $this->get('/admin/attendances');
        $response->assertStatus(200);

        $response = $this->get('/admin/attendance/' . $attendance->id);
        $response->assertStatus(200);

        $response->assertSee('勤怠詳細');
        $response->assertSee('田中太郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }
}
