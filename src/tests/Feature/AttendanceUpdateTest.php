<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Breaktime;
use App\Models\AttendanceRequest;
use App\Models\Admin;
use Tests\TestCase;

class AttendanceUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 出勤時間が退勤時間より後の場合のバリデーションテスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠詳細ページを開く
     * 3. 出勤時間を退勤時間よりも後に設定する
     * 4. 保存処理をする
     * 
     * 期待挙動:
     * 「出勤時間もしくは退勤時間が不適切な値です」というバリデーションメッセージが表示される
     */
    public function test_validation_error_when_clock_in_time_is_after_clock_out_time()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠記録を作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => now()->subDays(1)->setTime(9, 0),
            'clock_out_time' => now()->subDays(1)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        // 2. 勤怠詳細ページを開く
        $response = $this->get("/attendance/detail/{$attendance->id}");
        $response->assertStatus(200);

        // 3. 出勤時間を退勤時間よりも後に設定する
        // 4. 保存処理をする
        $updateData = [
            'clock_in_time' => '19:00',  // 退勤時間（18:00）より後の時間
            'clock_out_time' => '18:00',
            'notes' => 'テスト用の備考',
        ];

        $response = $this->put("/attendance/update/{$attendance->id}", $updateData);

        // 期待挙動: 「出勤時間もしくは退勤時間が不適切な値です」というバリデーションメッセージが表示される
        $response->assertSessionHasErrors();
        $response->assertRedirect();
        $this->followRedirects($response)->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }

    /**
     * 休憩開始時間が退勤時間より後の場合のバリデーションテスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠詳細ページを開く
     * 3. 休憩開始時間を退勤時間よりも後に設定する
     * 4. 保存処理をする
     * 
     * 期待挙動:
     * 「休憩時間が不適切な値です」というバリデーションメッセージが表示される
     */
    public function test_validation_error_when_break_start_time_is_after_break_end_time()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠記録を作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => now()->subDays(1)->setTime(9, 0),
            'clock_out_time' => now()->subDays(1)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        // 2. 勤怠詳細ページを開く
        $response = $this->get("/attendance/detail/{$attendance->id}");
        $response->assertStatus(200);

        // 3. 休憩開始時間を退勤時間よりも後に設定する
        // 4. 保存処理をする

        $updateData = [
            'clock_in_time' => '09:00',  // 出勤時間を設定
            'clock_out_time' => '18:00', // 退勤時間を設定
            'break1_start_time' => '19:00',  // 退勤時間（18:00）より後の時間
            'break1_end_time' => '18:00',
            'notes' => 'テスト用の備考',
        ];

        $response = $this->put("/attendance/update/{$attendance->id}", $updateData);

        // 期待挙動: 「休憩時間が不適切な値です」というバリデーションメッセージが表示される
        $response->assertSessionHasErrors();
        $response->assertRedirect();
        $this->followRedirects($response)->assertSee('休憩時間が不適切な値です');
    }

    /**
     * 休憩終了時間を退勤時間より後の場合のバリデーションテスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠詳細ページを開く
     * 3. 休憩終了時間を退勤時間よりも後に設定する
     * 4. 保存処理をする
     * 
     * 期待挙動:
     * 「休憩終了時間もしくは退勤時間が不適切な値です」というバリデーションメッセージが表示される
     */
    public function test_validation_error_when_break_end_time_is_after_clock_out_time()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠記録を作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => now()->subDays(1)->setTime(9, 0),
            'clock_out_time' => now()->subDays(1)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        // 2. 勤怠詳細ページを開く
        $response = $this->get("/attendance/detail/{$attendance->id}");
        $response->assertStatus(200);

        // 3. 休憩終了時間を退勤時間よりも後に設定する
        // 4. 保存処理をする
        $updateData = [
            'clock_in_time' => '09:00',  // 出勤時間を設定
            'clock_out_time' => '18:00', // 退勤時間を設定
            'break1_start_time' => '18:00',
            'break1_end_time' => '19:00',
            'notes' => 'テスト用の備考',
        ];

        $response = $this->put("/attendance/update/{$attendance->id}", $updateData);

        // 期待挙動: 「休憩終了時間もしくは退勤時間が不適切な値です」というバリデーションメッセージが表示される
        $response->assertSessionHasErrors();
        $response->assertRedirect();
        $this->followRedirects($response)->assertSee('休憩終了時間もしくは退勤時間が不適切な値です');
    }

    /**
     * 備考欄が未入力の場合のバリデーションテスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠詳細ページを開く
     * 3. 備考欄が未入力のまま保存処理をする
     * 
     * 期待挙動:
     * 「備考を記入してください」というバリデーションメッセージが表示される
     */
    public function test_validation_error_when_notes_is_empty()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠記録を作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => now()->subDays(1)->setTime(9, 0),
            'clock_out_time' => now()->subDays(1)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        // 2. 勤怠詳細ページを開く
        $response = $this->get("/attendance/detail/{$attendance->id}");
        $response->assertStatus(200);

        // 3. 備考欄が未入力のまま保存処理をする
        // 4. 保存処理をする
        $updateData = [
            'notes' => '',
        ];

        $response = $this->put("/attendance/update/{$attendance->id}", $updateData);

        // 期待挙動: 「備考を記入してください」というバリデーションメッセージが表示される
        $response->assertSessionHasErrors();
        $response->assertRedirect();
        $this->followRedirects($response)->assertSee('備考を記入してください');
    }

    /**
     * 修正申請の承認フローテスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠詳細を修正し保存処理する
     * 3. 管理者ユーザーで承認画面と申請一覧画面を確認する
     * 
     * 期待挙動:
     * 修正申請が実行され、管理者の承認画面と申請一覧画面に表示される
     */
    public function test_attendance_update_request_flow()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠記録を作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => now()->subDays(1)->setTime(9, 0),
            'clock_out_time' => now()->subDays(1)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        // 2. 勤怠詳細を修正し保存処理する
        $updateData = [
            'clock_in_time' => '08:30',  // 修正された出勤時間
            'clock_out_time' => '17:30', // 修正された退勤時間
            'notes' => '修正申請のテスト用備考',
        ];

        $response = $this->put("/attendance/update/{$attendance->id}", $updateData);
        $response->assertStatus(302); // リダイレクトされることを確認

        // データベースで申請が作成されたことを確認
        $this->assertDatabaseHas('attendance_requests', [
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'request_type' => 'update',
            'status' => 'pending',
            'notes' => '修正申請のテスト用備考',
        ]);

        // 3. 管理者ユーザーで承認画面と申請一覧画面を確認する
        // 管理者ユーザーを作成してログイン
        /** @var Admin $admin */
        $admin = Admin::factory()->create();

        // 管理者セッションを設定
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 申請一覧画面を確認
        $response = $this->get('/admin/requests');
        $response->assertStatus(200);

        // 最新の申請を取得して承認画面を確認
        $latestRequest = AttendanceRequest::latest()->first();
        if ($latestRequest) {
            $response = $this->get("/admin/requests/{$latestRequest->id}");
            $response->assertStatus(200);
        }

        // 期待挙動: 修正申請が実行され、管理者の承認画面と申請一覧画面に表示される
        $this->assertDatabaseHas('attendance_requests', [
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'request_type' => 'update',
            'status' => 'pending',
            'notes' => '修正申請のテスト用備考',
        ]);
    }

    /**
     * 修正申請の承認フローテスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠詳細を修正し保存処理する
     * 3. 申請一覧画面を確認する
     * 
     * 期待挙動:
     * 申請一覧に自分の申請が全て表示されている
     */
    public function test_attendance_update_request_is_displayed_in_all_requests()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠記録を作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => now()->subDays(1)->setTime(9, 0),
            'clock_out_time' => now()->subDays(1)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        // 2. 勤怠詳細を修正し保存処理する
        $updateData = [
            'clock_in_time' => '08:30',  // 修正された出勤時間
            'clock_out_time' => '17:30', // 修正された退勤時間
            'notes' => '修正申請のテスト用備考',
        ];

        $response = $this->put("/attendance/update/{$attendance->id}", $updateData);
        $response->assertStatus(302); // リダイレクトされることを確認

        // 3. 申請一覧画面を確認する
        // 管理者ユーザーでログイン
        /** @var Admin $admin */
        $admin = Admin::factory()->create();

        // 管理者セッションを設定
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $response = $this->get('/admin/requests');
        $response->assertStatus(200);

        // 期待挙動: 申請一覧に自分の申請が全て表示されている
        $this->assertDatabaseHas('attendance_requests', [
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'request_type' => 'update',
            'status' => 'pending',
            'notes' => '修正申請のテスト用備考',
        ]);
    }

    /**
     * 管理者が承認した修正申請の表示確認テスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠詳細を修正し保存処理をする
     * 3. 申請一覧画面を開く
     * 4. 管理者が承認した修正申請がすべて表示されていることを確認
     * 
     * 期待挙動:
     * 承認済みに管理者が承認した申請がすべて表示されている
     */
    public function test_approved_attendance_requests_are_displayed()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠記録を作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => now()->subDays(1)->setTime(9, 0),
            'clock_out_time' => now()->subDays(1)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        // 2. 勤怠詳細を修正し保存処理をする
        $updateData = [
            'clock_in_time' => '08:30',  // 修正された出勤時間
            'clock_out_time' => '17:30', // 修正された退勤時間
            'notes' => '承認済み申請のテスト用備考',
        ];

        $response = $this->put("/attendance/update/{$attendance->id}", $updateData);
        $response->assertStatus(302); // リダイレクトされることを確認

        // 管理者が承認処理を実行
        /** @var Admin $admin */
        $admin = Admin::factory()->create();

        // 管理者セッションを設定
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 最新の申請を取得して承認
        $latestRequest = AttendanceRequest::latest()->first();
        if ($latestRequest) {
            $approvalResponse = $this->post("/admin/requests/{$latestRequest->id}");
            $approvalResponse->assertStatus(302); // リダイレクトされることを確認
        }

        // 3. 申請一覧画面を開く
        $response = $this->get('/admin/requests?status=approved');
        $response->assertStatus(200);

        // 4. 管理者が承認した修正申請がすべて表示されていることを確認
        // 期待挙動: 承認済みに管理者が承認した申請がすべて表示されている
        $this->assertDatabaseHas('attendance_requests', [
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'request_type' => 'update',
            'status' => 'approved',
            'notes' => '承認済み申請のテスト用備考',
        ]);
    }

    /**
     * 修正申請の承認フローテスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠詳細を修正し保存処理をする
     * 3. 申請一覧画面を開く
     * 4．「詳細」ボタンを押す
     * 
     * 期待挙動:
     * 申請詳細画面に遷移する
     */
    public function test_attendance_update_request_detail_is_displayed_when_detail_button_is_clicked()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠記録を作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => now()->subDays(1)->setTime(9, 0),
            'clock_out_time' => now()->subDays(1)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        // 2. 勤怠詳細を修正し保存処理をする
        $updateData = [
            'clock_in_time' => '08:30',  // 修正された出勤時間
            'clock_out_time' => '17:30', // 修正された退勤時間
            'notes' => '承認済み申請のテスト用備考',
        ];

        $response = $this->put("/attendance/update/{$attendance->id}", $updateData);
        $response->assertStatus(302); // リダイレクトされることを確認

        // 3. 申請一覧画面を開く
        // 管理者ユーザーでログイン
        /** @var Admin $admin */
        $admin = Admin::factory()->create();

        // 管理者セッションを設定
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        $response = $this->get('/admin/requests');
        $response->assertStatus(200);

        // 4．「詳細」ボタンを押す
        $latestRequest = AttendanceRequest::latest()->first();
        if ($latestRequest) {
            $response = $this->get("/admin/requests/{$latestRequest->id}");
            $response->assertStatus(200);

            // 期待挙動: 申請詳細画面に遷移する
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
