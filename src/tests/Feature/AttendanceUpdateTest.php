<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\AttendanceRequest;
use App\Models\Admin;
use Tests\TestCase;

class AttendanceUpdateTest extends TestCase
{
    use RefreshDatabase;

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


    public function test_attendance_update_request_flow()
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

        $updateData = [
            'clock_in_time' => '08:30',
            'clock_out_time' => '17:30',
            'notes' => '修正申請のテスト用備考',
        ];

        $response = $this->put("/attendance/update/{$attendance->id}", $updateData);
        $response->assertStatus(302);

        $this->assertDatabaseHas('attendance_requests', [
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'request_type' => 'update',
            'status' => 'pending',
            'notes' => '修正申請のテスト用備考',
        ]);

        /** @var Admin $admin */
        $admin = Admin::factory()->create();

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $response = $this->get('/admin/requests');
        $response->assertStatus(200);

        $latestRequest = AttendanceRequest::latest()->first();
        if ($latestRequest) {
            $response = $this->get("/admin/requests/{$latestRequest->id}");
            $response->assertStatus(200);
        }

        $this->assertDatabaseHas('attendance_requests', [
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'request_type' => 'update',
            'status' => 'pending',
            'notes' => '修正申請のテスト用備考',
        ]);
    }


    public function test_attendance_update_request_is_displayed_in_all_requests()
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

        $updateData = [
            'clock_in_time' => '08:30',
            'clock_out_time' => '17:30',
            'notes' => '修正申請のテスト用備考',
        ];

        $response = $this->put("/attendance/update/{$attendance->id}", $updateData);
        $response->assertStatus(302);

        /** @var Admin $admin */
        $admin = Admin::factory()->create();

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $response = $this->get('/admin/requests');
        $response->assertStatus(200);

        $this->assertDatabaseHas('attendance_requests', [
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'request_type' => 'update',
            'status' => 'pending',
            'notes' => '修正申請のテスト用備考',
        ]);
    }

    public function test_approved_attendance_requests_are_displayed()
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

        $updateData = [
            'clock_in_time' => '08:30',
            'clock_out_time' => '17:30',
            'notes' => '承認済み申請のテスト用備考',
        ];

        $response = $this->put("/attendance/update/{$attendance->id}", $updateData);
        $response->assertStatus(302);

        /** @var Admin $admin */
        $admin = Admin::factory()->create();

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $latestRequest = AttendanceRequest::latest()->first();
        if ($latestRequest) {
            $approvalResponse = $this->post("/admin/requests/{$latestRequest->id}");
            $approvalResponse->assertStatus(302);
        }

        $response = $this->get('/admin/requests?status=approved');
        $response->assertStatus(200);

        $this->assertDatabaseHas('attendance_requests', [
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'request_type' => 'update',
            'status' => 'approved',
            'notes' => '承認済み申請のテスト用備考',
        ]);
    }

    public function test_attendance_update_request_detail_is_displayed_when_detail_button_is_clicked()
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

        $updateData = [
            'clock_in_time' => '08:30',
            'clock_out_time' => '17:30',
            'notes' => '承認済み申請のテスト用備考',
        ];

        $response = $this->put("/attendance/update/{$attendance->id}", $updateData);
        $response->assertStatus(302);

        /** @var Admin $admin */
        $admin = Admin::factory()->create();

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $response = $this->get('/admin/requests');
        $response->assertStatus(200);

        $latestRequest = AttendanceRequest::latest()->first();
        if ($latestRequest) {
            $response = $this->get("/admin/requests/{$latestRequest->id}");
            $response->assertStatus(200);

            $this->assertDatabaseHas('attendance_requests', [
                'user_id' => $user->id,
                'attendance_id' => $attendance->id,
                'request_type' => 'update',
                'status' => 'pending',
                'notes' => '承認済み申請のテスト用備考',
            ]);
        }
    }
}
