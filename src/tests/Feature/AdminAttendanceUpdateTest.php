<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Admin;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\Breaktime;
use Tests\TestCase;

class AdminAttendanceUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者が修正申請一覧ページで承認待ちの申請を確認できるテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーでログインする
     * 2. 修正申請一覧ページを開き、承認待ちのタブを開く
     * 
     * 期待挙動:
     * 全ユーザーの未認証の修正申請が表示される
     */
    public function test_admin_can_view_pending_update_requests()
    {
        // テスト用の管理者とユーザーを作成
        $admin = Admin::factory()->create();
        $user1 = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);
        $user2 = User::factory()->create([
            'name' => '佐藤花子',
            'email' => 'sato@example.com'
        ]);

        // 勤怠データを作成
        $attendance1 = $user1->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
            'status' => 'completed',
            'notes' => '元の備考1',
        ]);

        $attendance2 = $user2->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-16 08:30:00'),
            'clock_out_time' => Carbon::parse('2025-01-16 17:30:00'),
            'status' => 'completed',
            'notes' => '元の備考2',
        ]);

        // 承認待ちの修正申請を作成
        $request1 = AttendanceRequest::create([
            'user_id' => $user1->id,
            'attendance_id' => $attendance1->id,
            'target_date' => '2025-01-15',
            'request_type' => 'update',
            'status' => 'pending',
            'clock_in_time' => '2025-01-15 08:30:00',
            'clock_out_time' => '2025-01-15 17:30:00',
            'notes' => '修正申請の備考1',
        ]);

        $request2 = AttendanceRequest::create([
            'user_id' => $user2->id,
            'attendance_id' => $attendance2->id,
            'target_date' => '2025-01-16',
            'request_type' => 'update',
            'status' => 'pending',
            'clock_in_time' => '2025-01-16 08:00:00',
            'clock_out_time' => '2025-01-16 17:00:00',
            'notes' => '修正申請の備考2',
        ]);

        // 承認済みの修正申請も作成（表示されないことを確認するため）
        $request3 = AttendanceRequest::create([
            'user_id' => $user1->id,
            'attendance_id' => $attendance1->id,
            'target_date' => '2025-01-15',
            'request_type' => 'update',
            'status' => 'approved',
            'clock_in_time' => '2025-01-15 09:30:00',
            'clock_out_time' => '2025-01-15 18:30:00',
            'notes' => '承認済みの備考',
        ]);

        // 1. 管理者ユーザーでログインする
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 2. 修正申請一覧ページを開き、承認待ちのタブを開く
        $response = $this->get('/admin/requests?status=pending');
        $response->assertStatus(200);

        // 期待挙動: 全ユーザーの未認証の修正申請が表示される
        $response->assertSee('田中太郎');
        $response->assertSee('修正申請の備考1');
        $response->assertSee('佐藤花子');
        $response->assertSee('修正申請の備考2');

        // 承認済みの申請は表示されないことを確認
        $response->assertDontSee('承認済みの備考');
    }

    /**
     * 管理者が修正申請一覧ページで承認待ちの申請を確認できるテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーでログインする
     * 2. 修正申請一覧ページを開き、承認済みのタブを開く
     * 
     * 期待挙動:
     * 全ユーザーの承認済みの修正申請が表示される
     */
    public function test_admin_can_view_approved_update_requests()
    {
        // テスト用の管理者とユーザーを作成
        $admin = Admin::factory()->create();
        $user1 = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);
        $user2 = User::factory()->create([
            'name' => '佐藤花子',
            'email' => 'sato@example.com'
        ]);

        // 勤怠データを作成
        $attendance1 = $user1->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
            'status' => 'completed',
            'notes' => '元の備考1',
        ]);

        $attendance2 = $user2->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-16 08:30:00'),
            'clock_out_time' => Carbon::parse('2025-01-16 17:30:00'),
            'status' => 'completed',
            'notes' => '元の備考2',
        ]);

        // 承認待ちの修正申請を作成
        $request1 = AttendanceRequest::create([
            'user_id' => $user1->id,
            'attendance_id' => $attendance1->id,
            'target_date' => '2025-01-15',
            'request_type' => 'update',
            'status' => 'pending',
            'clock_in_time' => '2025-01-15 08:30:00',
            'clock_out_time' => '2025-01-15 17:30:00',
            'notes' => '修正申請の備考1',
        ]);

        $request2 = AttendanceRequest::create([
            'user_id' => $user2->id,
            'attendance_id' => $attendance2->id,
            'target_date' => '2025-01-16',
            'request_type' => 'update',
            'status' => 'pending',
            'clock_in_time' => '2025-01-16 08:00:00',
            'clock_out_time' => '2025-01-16 17:00:00',
            'notes' => '修正申請の備考2',
        ]);

        // 承認済みの修正申請を作成
        $request3 = AttendanceRequest::create([
            'user_id' => $user1->id,
            'attendance_id' => $attendance1->id,
            'target_date' => '2025-01-15',
            'request_type' => 'update',
            'status' => 'approved',
            'clock_in_time' => '2025-01-15 09:30:00',
            'clock_out_time' => '2025-01-15 18:30:00',
            'notes' => '修正申請の備考3',
        ]);

        $request4 = AttendanceRequest::create([
            'user_id' => $user2->id,
            'attendance_id' => $attendance2->id,
            'target_date' => '2025-01-16',
            'request_type' => 'update',
            'status' => 'approved',
            'clock_in_time' => '2025-01-16 08:00:00',
            'clock_out_time' => '2025-01-16 17:00:00',
            'notes' => '修正申請の備考2',
        ]);

        // 1. 管理者ユーザーでログインする
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 2. 修正申請一覧ページを開き、承認済みのタブを開く
        $response = $this->get('/admin/requests?status=approved');
        $response->assertStatus(200);

        // 期待挙動: 全ユーザーの承認済みの修正申請が表示される
        $response->assertSee('田中太郎');
        $response->assertSee('修正申請の備考3');
        $response->assertSee('佐藤花子');
        $response->assertSee('修正申請の備考2');
    }

    /**
     * 管理者が修正申請一覧ページから詳細画面を確認できるテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーでログインする
     * 2. 修正申請一覧ページを開き、詳細ボタンを押す
     * 
     * 期待挙動:
     * 申請内容が正しく表示されている
     */
    public function test_admin_can_view_update_request_detail()
    {
        // テスト用の管理者とユーザーを作成
        $admin = Admin::factory()->create();
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);

        // 勤怠データを作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
            'status' => 'completed',
            'notes' => '元の備考',
        ]);

        // 承認待ちの修正申請を作成
        $request = AttendanceRequest::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'target_date' => '2025-01-15',
            'request_type' => 'update',
            'status' => 'pending',
            'clock_in_time' => '2025-01-15 08:30:00',
            'clock_out_time' => '2025-01-15 17:30:00',
            'notes' => '修正申請の備考',
        ]);

        // 1. 管理者ユーザーでログインする
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 2. 修正申請一覧ページを開き、詳細ボタンを押す
        $response = $this->get('/admin/requests/' . $request->id);
        $response->assertStatus(200);

        // 期待挙動: 申請内容が正しく表示されている
        $response->assertSee('田中太郎');
        $response->assertSee('修正申請の備考');
        $response->assertSee('2025');
        $response->assertSee('7');
        $response->assertSee('12');
    }

    /**
     * 管理者が修正申請一覧ページから承認ボタンを押して承認できるテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーでログインする
     * 2. 修正申請の詳細画面で「承認」ボタンを押す
     * 
     * 期待挙動:
     * 修正申請が承認され、勤怠情報が更新される
     */
    public function test_admin_can_approve_update_request()
    {
        // テスト用の管理者とユーザーを作成
        $admin = Admin::factory()->create();
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);

        // 勤怠データを作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
            'status' => 'completed',
            'notes' => '元の備考',
        ]);

        // 承認待ちの修正申請を作成
        $request = AttendanceRequest::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'target_date' => '2025-01-15',
            'request_type' => 'update',
            'status' => 'pending',
            'clock_in_time' => '2025-01-15 08:30:00',
            'clock_out_time' => '2025-01-15 17:30:00',
            'notes' => '修正申請の備考',
        ]);

        // 1. 管理者ユーザーでログインする
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 2. 修正申請の詳細画面で「承認」ボタンを押す
        $response = $this->post('/admin/requests/' . $request->id);
        $response->assertStatus(302);

        // 期待挙動: 修正申請が承認され、勤怠情報が更新される
        $request->refresh();
        $this->assertEquals('approved', $request->status);
    }
}
