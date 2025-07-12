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

    public function test_admin_can_view_attendance_detail()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create(['name' => '田中太郎']);

        $attendance = $user->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
            'status' => 'completed',
        ]);

        $attendance->breaks()->create([
            'start_time' => Carbon::parse('2025-01-15 12:00:00'),
            'end_time' => Carbon::parse('2025-01-15 13:00:00'),
        ]);

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $response = $this->get('/admin/attendance/' . $attendance->id);
        $response->assertStatus(200);

        $response->assertSee('田中太郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('12:00');
        $response->assertSee('13:00');
    }

    public function test_admin_cannot_set_clock_in_time_after_clock_out_time()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create(['name' => '田中太郎']);

        $attendance = $user->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
            'status' => 'completed',
        ]);

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $invalidData = [
            'clock_in_time' => '19:00',
            'clock_out_time' => '18:00',
            'notes' => 'テスト備考',
            'date' => '2025-01-15',
        ];

        $response = $this->put('/admin/attendance/update/' . $attendance->id, $invalidData);

        $response->assertSessionHasErrors();
        $response->assertSessionHasErrors('clock_in_time');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
        ]);
    }

    public function test_admin_cannot_set_break_start_time_after_clock_out_time()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create(['name' => '田中太郎']);

        $attendance = $user->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
            'status' => 'completed',
        ]);

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $invalidData = [
            'clock_in_time' => '09:00',
            'clock_out_time' => '18:00',
            'break1_start_time' => '19:00',
            'break1_end_time' => '20:00',
            'notes' => 'テスト備考',
            'date' => '2025-01-15',
        ];

        $response = $this->put('/admin/attendance/update/' . $attendance->id, $invalidData);

        $response->assertSessionHasErrors();
        $response->assertSessionHasErrors('break1_start_time');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
        ]);
    }

    public function test_admin_cannot_set_break_end_time_after_clock_out_time()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create(['name' => '田中太郎']);

        $attendance = $user->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
            'status' => 'completed',
        ]);

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $invalidData = [
            'clock_in_time' => '09:00',
            'clock_out_time' => '18:00',
            'break1_start_time' => '12:00',
            'break1_end_time' => '19:00',
            'notes' => 'テスト備考',
            'date' => '2025-01-15',
        ];

        $response = $this->put('/admin/attendance/update/' . $attendance->id, $invalidData);

        $response->assertSessionHasErrors();
        $response->assertSessionHasErrors('break1_end_time');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
        ]);
    }


    public function test_admin_cannot_save_attendance_without_remarks()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create(['name' => '田中太郎']);

        $attendance = $user->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
            'status' => 'completed',
        ]);

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $invalidData = [
            'clock_in_time' => '09:00',
            'clock_out_time' => '18:00',
            'notes' => '',
            'date' => '2025-01-15',
        ];

        $response = $this->put('/admin/attendance/update/' . $attendance->id, $invalidData);

        $response->assertSessionHasErrors();
        $response->assertSessionHasErrors('notes');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
        ]);
    }
}
