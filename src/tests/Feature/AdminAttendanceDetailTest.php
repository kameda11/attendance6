<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Admin;
use Tests\TestCase;

class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_attendance_detail_with_selected_information()
    {
        $admin = Admin::factory()->create();
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $user = User::factory()->create(['name' => '田中太郎']);

        $selectedDate = Carbon::parse('2025-07-13');
        $attendance = $user->attendances()->create([
            'clock_in_time' => $selectedDate->copy()->setTime(9, 0),
            'clock_out_time' => $selectedDate->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $selectedDate,
            'notes' => 'テスト用の備考',
        ]);

        $attendance->breaks()->create([
            'start_time' => $selectedDate->copy()->setTime(12, 0),
            'end_time' => $selectedDate->copy()->setTime(13, 0),
        ]);

        $response = $this->get('/admin/attendance/' . $attendance->id);
        $response->assertStatus(200);

        $response->assertSee('田中太郎');
        $response->assertSee('2025');
        $response->assertSee('7');
        $response->assertSee('13');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('12:00');
        $response->assertSee('13:00');
        $response->assertSee('テスト用の備考');
    }

    public function test_validation_error_when_clock_in_time_is_after_clock_out_time()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendance = $user->attendances()->create([
            'clock_in_time' => now()->subDays(1)->setTime(9, 0),
            'clock_out_time' => now()->subDays(1)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->get("/attendance/detail/{$attendance->id}");
        $response->assertStatus(200);

        $updateData = [
            'clock_in_time' => '19:00',
            'clock_out_time' => '18:00',
            'notes' => 'テスト用の備考',
        ];

        $response = $this->put("/attendance/update/{$attendance->id}", $updateData);

        $response->assertSessionHasErrors();
        $response->assertRedirect();
        $this->followRedirects($response)->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }


    public function test_validation_error_when_break_time_is_outside_work_hours()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendance = $user->attendances()->create([
            'clock_in_time' => now()->subDays(1)->setTime(9, 0),
            'clock_out_time' => now()->subDays(1)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->get("/attendance/detail/{$attendance->id}");
        $response->assertStatus(200);

        $updateData1 = [
            'clock_in_time' => '09:00',
            'clock_out_time' => '18:00',
            'break1_start_time' => '08:00',
            'break1_end_time' => '09:30',
            'notes' => 'テスト用の備考',
        ];

        $response1 = $this->put("/attendance/update/{$attendance->id}", $updateData1);
        $response1->assertSessionHasErrors();
        $response1->assertRedirect();
        $this->followRedirects($response1)->assertSee('休憩時間が不適切な値です');

        $updateData2 = [
            'clock_in_time' => '09:00',
            'clock_out_time' => '18:00',
            'break1_start_time' => '17:00',
            'break1_end_time' => '19:00',
            'notes' => 'テスト用の備考',
        ];

        $response2 = $this->put("/attendance/update/{$attendance->id}", $updateData2);
        $response2->assertSessionHasErrors();
        $response2->assertRedirect();
        $this->followRedirects($response2)->assertSee('休憩時間が不適切な値です');
    }


    public function test_validation_error_when_notes_is_empty()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendance = $user->attendances()->create([
            'clock_in_time' => now()->subDays(1)->setTime(9, 0),
            'clock_out_time' => now()->subDays(1)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->get("/attendance/detail/{$attendance->id}");
        $response->assertStatus(200);

        $updateData = [
            'notes' => '',
        ];

        $response = $this->put("/attendance/update/{$attendance->id}", $updateData);

        $response->assertSessionHasErrors();
        $response->assertRedirect();
        $this->followRedirects($response)->assertSee('備考を記入してください');
    }
}
