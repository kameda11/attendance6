<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Admin;
use App\Models\AttendanceRequest;
use Tests\TestCase;

class AdminAttendanceUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_pending_update_requests()
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

        $attendance1 = $user1->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-07-13 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-07-13 18:00:00'),
            'status' => 'completed',
            'notes' => '元の備考1',
        ]);

        $attendance2 = $user2->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-07-14 08:30:00'),
            'clock_out_time' => Carbon::parse('2025-07-14 17:30:00'),
            'status' => 'completed',
            'notes' => '元の備考2',
        ]);

        $request1 = AttendanceRequest::create([
            'user_id' => $user1->id,
            'attendance_id' => $attendance1->id,
            'target_date' => '2025-07-13',
            'request_type' => 'update',
            'status' => 'pending',
            'clock_in_time' => '2025-07-13 08:30:00',
            'clock_out_time' => '2025-07-13 17:30:00',
            'notes' => '修正申請の備考1',
        ]);

        $request2 = AttendanceRequest::create([
            'user_id' => $user2->id,
            'attendance_id' => $attendance2->id,
            'target_date' => '2025-07-14',
            'request_type' => 'update',
            'status' => 'pending',
            'clock_in_time' => '2025-07-14 08:00:00',
            'clock_out_time' => '2025-07-14 17:00:00',
            'notes' => '修正申請の備考2',
        ]);

        $request3 = AttendanceRequest::create([
            'user_id' => $user1->id,
            'attendance_id' => $attendance1->id,
            'target_date' => '2025-07-13',
            'request_type' => 'update',
            'status' => 'approved',
            'clock_in_time' => '2025-07-13 09:30:00',
            'clock_out_time' => '2025-07-13 18:30:00',
            'notes' => '承認済みの備考',
        ]);

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $response = $this->get('/admin/requests?status=pending');
        $response->assertStatus(200);

        $response->assertSee('田中太郎');
        $response->assertSee('修正申請の備考1');
        $response->assertSee('佐藤花子');
        $response->assertSee('修正申請の備考2');

        $response->assertDontSee('承認済みの備考');
    }

    public function test_admin_can_view_approved_update_requests()
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

        $attendance1 = $user1->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-07-13 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-07-13 18:00:00'),
            'status' => 'completed',
            'notes' => '元の備考1',
        ]);

        $attendance2 = $user2->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-07-14 08:30:00'),
            'clock_out_time' => Carbon::parse('2025-07-14 17:30:00'),
            'status' => 'completed',
            'notes' => '元の備考2',
        ]);

        $request1 = AttendanceRequest::create([
            'user_id' => $user1->id,
            'attendance_id' => $attendance1->id,
            'target_date' => '2025-07-13',
            'request_type' => 'update',
            'status' => 'pending',
            'clock_in_time' => '2025-07-13 08:30:00',
            'clock_out_time' => '2025-07-13 17:30:00',
            'notes' => '修正申請の備考1',
        ]);

        $request2 = AttendanceRequest::create([
            'user_id' => $user2->id,
            'attendance_id' => $attendance2->id,
            'target_date' => '2025-07-14',
            'request_type' => 'update',
            'status' => 'pending',
            'clock_in_time' => '2025-07-14 08:00:00',
            'clock_out_time' => '2025-07-14 17:00:00',
            'notes' => '修正申請の備考2',
        ]);

        $request3 = AttendanceRequest::create([
            'user_id' => $user1->id,
            'attendance_id' => $attendance1->id,
            'target_date' => '2025-07-13',
            'request_type' => 'update',
            'status' => 'approved',
            'clock_in_time' => '2025-07-13 09:30:00',
            'clock_out_time' => '2025-07-13 18:30:00',
            'notes' => '修正申請の備考3',
        ]);

        $request4 = AttendanceRequest::create([
            'user_id' => $user2->id,
            'attendance_id' => $attendance2->id,
            'target_date' => '2025-07-14',
            'request_type' => 'update',
            'status' => 'approved',
            'clock_in_time' => '2025-07-14 08:00:00',
            'clock_out_time' => '2025-07-14 17:00:00',
            'notes' => '修正申請の備考2',
        ]);

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $response = $this->get('/admin/requests?status=approved');
        $response->assertStatus(200);

        $response->assertSee('田中太郎');
        $response->assertSee('修正申請の備考3');
        $response->assertSee('佐藤花子');
        $response->assertSee('修正申請の備考2');
    }

    public function test_admin_can_view_update_request_detail()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);

        $targetDate = now()->format('Y-m-d');
        $clockInTime = now()->setTime(9, 0);
        $clockOutTime = now()->setTime(18, 0);

        $attendance = $user->attendances()->create([
            'clock_in_time' => $clockInTime,
            'clock_out_time' => $clockOutTime,
            'status' => 'completed',
            'notes' => '元の備考',
        ]);

        $request = AttendanceRequest::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'target_date' => $targetDate,
            'request_type' => 'update',
            'status' => 'pending',
            'clock_in_time' => $targetDate . ' 08:30:00',
            'clock_out_time' => $targetDate . ' 17:30:00',
            'notes' => '修正申請の備考',
        ]);

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $response = $this->get('/admin/requests/' . $request->id);
        $response->assertStatus(200);

        $response->assertSee('田中太郎');
        $response->assertSee('修正申請の備考');
        $response->assertSee(now()->format('Y'));
        $response->assertSee(now()->format('n'));
        $response->assertSee(now()->format('j'));
    }


    public function test_admin_can_approve_update_request()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);

        $targetDate = now()->format('Y-m-d');
        $clockInTime = now()->setTime(9, 0);
        $clockOutTime = now()->setTime(18, 0);

        $attendance = $user->attendances()->create([
            'clock_in_time' => $clockInTime,
            'clock_out_time' => $clockOutTime,
            'status' => 'completed',
            'notes' => '元の備考',
        ]);

        $request = AttendanceRequest::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'target_date' => $targetDate,
            'request_type' => 'update',
            'status' => 'pending',
            'clock_in_time' => $targetDate . ' 08:30:00',
            'clock_out_time' => $targetDate . ' 17:30:00',
            'notes' => '修正申請の備考',
        ]);

        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $response = $this->post('/admin/requests/' . $request->id);
        $response->assertStatus(302);

        $request->refresh();
        $this->assertEquals('approved', $request->status);
    }
}
