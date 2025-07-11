<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;
use Tests\TestCase;

class ClockInTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 出勤機能のテスト
     * 
     * テスト手順:
     * 1. ステータスが勤務外のユーザーにログインする
     * 2. 画面に「出勤」ボタンが表示されていることを確認する
     * 3. 出勤の処理を行う
     * 
     * 期待挙動:
     * 画面上に「出勤」ボタンが表示され、処理後に画面上に表示されるステータスが「出勤中」になる
     */
    public function test_clock_in_functionality()
    {
        // 1. ステータスが勤務外のユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        // 2. 画面に「出勤」ボタンが表示されていることを確認する
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤');
        $response->assertSee('clockInBtn');

        // 3. 出勤の処理を行う
        $clockInResponse = $this->post('/attendance/clock-in');
        $clockInResponse->assertStatus(200);
        $clockInResponse->assertJson(['success' => true]);

        // 期待挙動: 処理後に画面上に表示されるステータスが「出勤中」になる
        $afterClockInResponse = $this->get('/attendance');
        $afterClockInResponse->assertStatus(200);
        $afterClockInResponse->assertSee('出勤中');
    }

    /**
     * 退勤済ユーザーで出勤ボタンが表示されないことを確認するテスト
     * 
     * テスト手順:
     * 1. ステータスが退勤済のユーザーにログインする
     * 2. 出勤ボタンが表示されていないことを確認する
     * 
     * 期待挙動:
     * 画面上に「出勤」ボタンが表示されない
     */
    public function test_clock_in_button_not_displayed_for_completed_user()
    {
        // 1. ステータスが退勤済のユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        // 退勤済の勤怠記録を作成（今日の日付で作成）
        $today = now();
        $user->attendances()->create([
            'clock_in_time' => $today->copy()->subHours(8),
            'clock_out_time' => $today,
            'status' => 'completed',
            'created_at' => $today,
        ]);

        // 2. 出勤ボタンが表示されていないことを確認する
        $response = $this->get('/attendance');
        $response->assertStatus(200);

        // 期待挙動: 画面上に「出勤」ボタンが表示されない
        $response->assertDontSee('id="clockInBtn"');
        $response->assertDontSee('onclick="clockIn()"');
        $response->assertSee('退勤済');
        $response->assertSee('お疲れ様でした');
    }

    /**
     * 勤怠一覧画面で出勤時刻が正確に記録されていることを確認するテスト
     * 
     * テスト手順:
     * 1. ステータスが勤務外のユーザーにログインする
     * 2. 出勤の処理を行う
     * 3. 勤怠一覧画面から出勤の日時を確認する
     * 
     * 期待挙動:
     * 勤怠一覧画面に出勤時刻が正確に記録されている
     */
    public function test_clock_in_time_is_recorded_in_attendance_list()
    {
        // 1. ステータスが勤務外のユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        // 2. 出勤の処理を行う
        $clockInTime = now();
        $this->travelTo($clockInTime);

        $clockInResponse = $this->post('/attendance/clock-in');
        $clockInResponse->assertStatus(200);
        $clockInResponse->assertJson(['success' => true]);

        // 3. 勤怠一覧画面から出勤の日時を確認する
        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        // 期待挙動: 勤怠一覧画面に出勤時刻が正確に記録されている
        $response->assertSee($clockInTime->format('H:i'));

        // データベースにも正確に記録されていることを確認
        $attendance = Attendance::where('user_id', $user->id)->first();
        $this->assertNotNull($attendance);
        $this->assertEquals($clockInTime->format('Y-m-d H:i:s'), $attendance->clock_in_time->format('Y-m-d H:i:s'));

        // 時間を元に戻す
        $this->travelBack();
    }
}
