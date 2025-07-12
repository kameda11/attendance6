<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Tests\TestCase;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_all_their_attendance_records()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendance1 = $user->attendances()->create([
            'clock_in_time' => now()->subDays(5)->setTime(9, 0),
            'clock_out_time' => now()->subDays(5)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => now()->subDays(5),
        ]);

        $attendance2 = $user->attendances()->create([
            'clock_in_time' => now()->subDays(3)->setTime(8, 30),
            'clock_out_time' => now()->subDays(3)->setTime(17, 30),
            'status' => 'completed',
            'created_at' => now()->subDays(3),
        ]);

        $attendance3 = $user->attendances()->create([
            'clock_in_time' => now()->subDays(1)->setTime(9, 15),
            'clock_out_time' => now()->subDays(1)->setTime(18, 15),
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        $response->assertSee('09:00');
        $response->assertSee('18:00');

        $response->assertSee('08:30');
        $response->assertSee('17:30');

        $response->assertSee('09:15');
        $response->assertSee('勤怠一覧');
        $response->assertSee('日付');
        $response->assertSee('出勤');
        $response->assertSee('退勤');
        $response->assertSee('休憩');
        $response->assertSee('合計');
        $response->assertSee('詳細');
    }

    public function test_current_month_is_displayed()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        $currentMonth = now()->format('Y/m');
        $response->assertSee($currentMonth);
    }

    public function test_previous_month_button_works_correctly()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $prevMonth = now()->subMonth();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $prevMonth->copy()->setTime(9, 0),
            'clock_out_time' => $prevMonth->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $prevMonth,
        ]);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        $prevMonthYear = $prevMonth->year;
        $prevMonthNumber = $prevMonth->month;
        $response = $this->get("/attendance/list?year={$prevMonthYear}&month={$prevMonthNumber}");
        $response->assertStatus(200);

        $prevMonthDisplay = $prevMonth->format('Y/m');
        $response->assertSee($prevMonthDisplay);

        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }


    public function test_next_month_button_works_correctly()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $nextMonth = now()->addMonth();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $nextMonth->copy()->setTime(9, 0),
            'clock_out_time' => $nextMonth->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $nextMonth,
        ]);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        $nextMonthYear = $nextMonth->year;
        $nextMonthNumber = $nextMonth->month;
        $response = $this->get("/attendance/list?year={$nextMonthYear}&month={$nextMonthNumber}");
        $response->assertStatus(200);

        $nextMonthDisplay = $nextMonth->format('Y/m');
        $response->assertSee($nextMonthDisplay);

        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    public function test_detail_button_works_correctly()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendanceDate = now()->subDays(5);
        $attendance = $user->attendances()->create([
            'clock_in_time' => $attendanceDate->copy()->setTime(9, 0),
            'clock_out_time' => $attendanceDate->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $attendanceDate,
        ]);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        $detailUrl = "/attendance/detail/{$attendance->id}";
        $response = $this->get($detailUrl);
        $response->assertStatus(200);

        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }
}
