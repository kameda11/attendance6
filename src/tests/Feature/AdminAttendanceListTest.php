<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Admin;
use Tests\TestCase;

class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_accurate_daily_attendance_list()
    {
        /** @var Admin $admin */
        $admin = Admin::factory()->create();
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $user1 = User::factory()->create(['name' => '田中太郎']);
        $user2 = User::factory()->create(['name' => '佐藤花子']);
        $user3 = User::factory()->create(['name' => '鈴木一郎']);

        $targetDate = now()->format('Y-m-d');

        $attendance1 = $user1->attendances()->create([
            'clock_in_time' => Carbon::parse($targetDate . ' 09:00:00'),
            'clock_out_time' => Carbon::parse($targetDate . ' 18:00:00'),
            'status' => 'completed',
            'created_at' => Carbon::parse($targetDate),
        ]);

        $attendance1->breaks()->create([
            'start_time' => Carbon::parse($targetDate . ' 12:00:00'),
            'end_time' => Carbon::parse($targetDate . ' 13:00:00'),
        ]);

        $attendance2 = $user2->attendances()->create([
            'clock_in_time' => Carbon::parse($targetDate . ' 08:30:00'),
            'status' => 'working',
            'created_at' => Carbon::parse($targetDate),
        ]);

        $attendance3 = $user3->attendances()->create([
            'clock_in_time' => Carbon::parse($targetDate . ' 08:00:00'),
            'clock_out_time' => Carbon::parse($targetDate . ' 17:00:00'),
            'status' => 'completed',
            'created_at' => Carbon::parse($targetDate),
        ]);

        $attendance3->breaks()->create([
            'start_time' => Carbon::parse($targetDate . ' 10:00:00'),
            'end_time' => Carbon::parse($targetDate . ' 10:15:00'),
        ]);
        $attendance3->breaks()->create([
            'start_time' => Carbon::parse($targetDate . ' 12:00:00'),
            'end_time' => Carbon::parse($targetDate . ' 13:00:00'),
        ]);

        $response = $this->get('/admin/attendances?date=' . $targetDate);
        $response->assertStatus(200);

        $response->assertSee('田中太郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('01:00');
        $response->assertSee('08:00');

        $response->assertSee('佐藤花子');
        $response->assertSee('08:30');

        $response->assertSee('鈴木一郎');
        $response->assertSee('08:00');
        $response->assertSee('17:00');
        $response->assertSee('01:15');
        $response->assertSee('07:45');

        $response->assertSee('田中太郎');
        $response->assertSee('佐藤花子');
        $response->assertSee('鈴木一郎');
    }

    public function test_admin_can_view_current_date_in_attendance_list()
    {
        /** @var Admin $admin */
        $admin = Admin::factory()->create();
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $user = User::factory()->create(['name' => '田中太郎']);

        $today = now();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $today->copy()->setTime(9, 0),
            'clock_out_time' => $today->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $today,
        ]);

        $response = $this->get('/admin/attendances');
        $response->assertStatus(200);

        $response->assertSee($today->format('Y年m月d日') . 'の勤怠');

        $response->assertSee($today->format('Y-m-d'));

        $response->assertSee($today->format('Y/m/d'));
    }

    public function test_admin_can_view_previous_day_attendance_list()
    {
        /** @var Admin $admin */
        $admin = Admin::factory()->create();
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $user = User::factory()->create(['name' => '田中太郎']);

        $yesterday = now()->subDay();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $yesterday->copy()->setTime(9, 0),
            'clock_out_time' => $yesterday->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $yesterday,
        ]);

        $attendance->breaks()->create([
            'start_time' => $yesterday->copy()->setTime(12, 0),
            'end_time' => $yesterday->copy()->setTime(13, 0),
        ]);

        $response = $this->get('/admin/attendances');
        $response->assertStatus(200);

        $response = $this->get('/admin/attendances?date=' . $yesterday->format('Y-m-d'));
        $response->assertStatus(200);

        $response->assertSee($yesterday->format('Y年m月d日') . 'の勤怠');
        $response->assertSee($yesterday->format('Y-m-d'));
        $response->assertSee($yesterday->format('Y/m/d'));

        $response->assertSee('田中太郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('01:00');
        $response->assertSee('08:00');
    }

    public function test_admin_can_view_next_day_attendance_list()
    {
        /** @var Admin $admin */
        $admin = Admin::factory()->create();
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $user = User::factory()->create(['name' => '田中太郎']);

        $tomorrow = now()->addDay();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $tomorrow->copy()->setTime(8, 30),
            'clock_out_time' => $tomorrow->copy()->setTime(17, 30),
            'status' => 'completed',
            'created_at' => $tomorrow,
        ]);

        $attendance->breaks()->create([
            'start_time' => $tomorrow->copy()->setTime(11, 30),
            'end_time' => $tomorrow->copy()->setTime(12, 30),
        ]);

        $response = $this->get('/admin/attendances');
        $response->assertStatus(200);

        $response = $this->get('/admin/attendances?date=' . $tomorrow->format('Y-m-d'));
        $response->assertStatus(200);

        $response->assertSee($tomorrow->format('Y年m月d日') . 'の勤怠');
        $response->assertSee($tomorrow->format('Y-m-d'));
        $response->assertSee($tomorrow->format('Y/m/d'));

        $response->assertSee('田中太郎');
        $response->assertSee('08:30');
        $response->assertSee('17:30');
        $response->assertSee('01:00');
        $response->assertSee('08:00');
    }
}
