<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Breaktime;
use App\Models\Admin;
use Tests\TestCase;

class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者が勤怠詳細画面で勤怠情報を確認できるテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーにログインする
     * 2. 勤怠詳細画面を開く
     * 
     * 期待挙動:
     * 詳細画面の内容が選択した情報と一致する
     */
    public function test_admin_can_view_attendance_detail()
    {
        // テスト用の管理者とユーザーを作成
        $admin = Admin::factory()->create();
        $user = User::factory()->create(['name' => '田中太郎']);

        // 勤怠データを作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
            'status' => 'completed',
        ]);

        // 休憩データを作成
        $attendance->breaks()->create([
            'start_time' => Carbon::parse('2025-01-15 12:00:00'),
            'end_time' => Carbon::parse('2025-01-15 13:00:00'),
        ]);

        // 管理者としてログイン
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 勤怠詳細画面にアクセス
        $response = $this->get('/admin/attendance/' . $attendance->id);
        $response->assertStatus(200);

        // 勤怠情報が表示されることを確認
        $response->assertSee('田中太郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('12:00');
        $response->assertSee('13:00');
    }

    /**
     * 管理者が勤怠詳細画面で出勤時間を退勤時間より後に設定した場合のバリデーションテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーにログインする
     * 2. 勤怠詳細ページを開く
     * 3. 出勤時間を退勤時間より後に設定する
     * 4. 保存処理をする
     * 
     * 期待挙動:
     * 「出勤時間もしくは退勤時間が不適切な値です」というバリデーションメッセージが表示される
     */
    public function test_admin_cannot_set_clock_in_time_after_clock_out_time()
    {
        // テスト用の管理者とユーザーを作成
        $admin = Admin::factory()->create();
        $user = User::factory()->create(['name' => '田中太郎']);

        // 勤怠データを作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
            'status' => 'completed',
        ]);

        // 管理者としてログイン
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 出勤時間を退勤時間より後に設定する（不正なデータ）
        $invalidData = [
            'clock_in_time' => '19:00',  // 退勤時間（18:00）より後
            'clock_out_time' => '18:00',
            'notes' => 'テスト備考',
            'date' => '2025-01-15',
        ];

        // 勤怠情報を更新（バリデーションエラーが発生するはず）
        $response = $this->put('/admin/attendance/update/' . $attendance->id, $invalidData);

        // バリデーションエラーメッセージが表示されることを確認
        $response->assertSessionHasErrors();
        $response->assertSessionHasErrors('clock_in_time');

        // データベースの値が変更されていないことを確認
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
        ]);
    }

    /**
     * 管理者が勤怠詳細画面で休憩開始時間を退勤時間より後に設定した場合のバリデーションテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーにログインする
     * 2. 勤怠詳細ページを開く
     * 3. 休憩開始時間を退勤時間より後に設定する
     * 4. 保存処理をする
     * 
     * 期待挙動:
     * 「休憩時間が不適切な値です」というバリデーションメッセージが表示される
     */
    public function test_admin_cannot_set_break_start_time_after_clock_out_time()
    {
        // テスト用の管理者とユーザーを作成
        $admin = Admin::factory()->create();
        $user = User::factory()->create(['name' => '田中太郎']);

        // 勤怠データを作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
            'status' => 'completed',
        ]);

        // 管理者としてログイン
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 休憩開始時間を退勤時間より後に設定する（不正なデータ）
        $invalidData = [
            'clock_in_time' => '09:00',
            'clock_out_time' => '18:00',
            'break1_start_time' => '19:00',  // 退勤時間（18:00）より後
            'break1_end_time' => '20:00',
            'notes' => 'テスト備考',
            'date' => '2025-01-15',
        ];

        // 勤怠情報を更新（バリデーションエラーが発生するはず）
        $response = $this->put('/admin/attendance/update/' . $attendance->id, $invalidData);

        // バリデーションエラーメッセージが表示されることを確認
        $response->assertSessionHasErrors();
        $response->assertSessionHasErrors('break1_start_time');

        // データベースの値が変更されていないことを確認
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
        ]);
    }

    /**
     * 管理者が勤怠詳細画面で休憩終了時間を退勤時間より後に設定した場合のバリデーションテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーにログインする
     * 2. 勤怠詳細ページを開く
     * 3. 休憩終了時間を退勤時間より後に設定する
     * 4. 保存処理をする
     * 
     * 期待挙動:
     * 「休憩終了時間もしくは退勤時間が不適切な値です」というバリデーションメッセージが表示される
     */
    public function test_admin_cannot_set_break_end_time_after_clock_out_time()
    {
        // テスト用の管理者とユーザーを作成
        $admin = Admin::factory()->create();
        $user = User::factory()->create(['name' => '田中太郎']);

        // 勤怠データを作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
            'status' => 'completed',
        ]);

        // 管理者としてログイン
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 休憩終了時間を退勤時間より後に設定する（不正なデータ）
        $invalidData = [
            'clock_in_time' => '09:00',
            'clock_out_time' => '18:00',
            'break1_start_time' => '12:00',
            'break1_end_time' => '19:00',  // 退勤時間（18:00）より後
            'notes' => 'テスト備考',
            'date' => '2025-01-15',
        ];

        // 勤怠情報を更新（バリデーションエラーが発生するはず）
        $response = $this->put('/admin/attendance/update/' . $attendance->id, $invalidData);

        // バリデーションエラーメッセージが表示されることを確認
        $response->assertSessionHasErrors();
        $response->assertSessionHasErrors('break1_end_time');

        // データベースの値が変更されていないことを確認
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
        ]);
    }

    /**
     * 管理者が勤怠詳細画面で備考欄を未入力のまま保存処理をした場合のバリデーションテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーにログインする
     * 2. 勤怠詳細ページを開く
     * 3. 備考欄を未入力のまま保存処理をする
     * 
     * 期待挙動:
     * 「備考を記入してください」というバリデーションメッセージが表示される
     */
    public function test_admin_cannot_save_attendance_without_remarks()
    {
        // テスト用の管理者とユーザーを作成
        $admin = Admin::factory()->create();
        $user = User::factory()->create(['name' => '田中太郎']);

        // 勤怠データを作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
            'status' => 'completed',
        ]);

        // 管理者としてログイン
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 備考欄を未入力のまま保存処理をする（不正なデータ）
        $invalidData = [
            'clock_in_time' => '09:00',
            'clock_out_time' => '18:00',
            'notes' => '',  // 備考欄が未入力
            'date' => '2025-01-15',
        ];

        // 勤怠情報を更新（バリデーションエラーが発生するはず）
        $response = $this->put('/admin/attendance/update/' . $attendance->id, $invalidData);

        // バリデーションエラーメッセージが表示されることを確認
        $response->assertSessionHasErrors();
        $response->assertSessionHasErrors('notes');

        // データベースの値が変更されていないことを確認
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_time' => Carbon::parse('2025-01-15 09:00:00'),
            'clock_out_time' => Carbon::parse('2025-01-15 18:00:00'),
        ]);
    }
}
