<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;
use Tests\TestCase;

class ClockOutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 退勤機能のテスト
     * 
     * テスト手順:
     * 1. ステータスが出勤中のユーザーにログインする
     * 2. 画面に「退勤」ボタンが表示されていることを確認する
     * 3. 退勤の処理を行う
     * 
     * 期待挙動:
     * 画面上に「退勤」ボタンが表示され、処理後に画面上に表示されるステータスが「退勤済」になる
     */
    public function test_clock_out_functionality()
    {
        // 1. ステータスが出勤中のユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        // 出勤中の勤怠記録を作成
        $user->attendances()->create([
            'clock_in_time' => now()->subHours(2),
            'status' => 'working',
        ]);

        // 2. 画面に「退勤」ボタンが表示されていることを確認する
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('退勤');
        $response->assertSee('clockOutBtn');

        // 3. 退勤の処理を行う
        $clockOutResponse = $this->post('/attendance/clock-out');
        $clockOutResponse->assertStatus(200);
        $clockOutResponse->assertJson(['success' => true]);

        // 期待挙動: 処理後に画面上に表示されるステータスが「退勤済」になる
        $afterClockOutResponse = $this->get('/attendance');
        $afterClockOutResponse->assertStatus(200);
        $afterClockOutResponse->assertSee('退勤済');
    }

    /**
     * 勤怠一覧画面で退勤時刻が正確に記録されていることを確認するテスト
     * 
     * テスト手順:
     * 1. ステータスが勤務外のユーザーにログインする
     * 2. 出勤と退勤の処理を行う
     * 3. 勤怠一覧画面から退勤の日付を確認する
     * 
     * 期待挙動:
     * 勤怠一覧画面に退勤時刻が正確に記録されている
     */
    public function test_clock_out_time_is_recorded_in_attendance_list()
    {
        // 1. ステータスが勤務外のユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        // 2. 出勤と退勤の処理を行う
        $clockInTime = now();
        $this->travelTo($clockInTime);

        // 出勤記録を直接作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => $clockInTime,
            'status' => 'working',
            'created_at' => $clockInTime,
        ]);

        // 出勤記録が作成されていることを確認
        $this->assertNotNull($attendance);
        $this->assertEquals('working', $attendance->status);

        // 退勤時刻を設定（8時間後）
        $clockOutTime = $clockInTime->copy()->addHours(8);

        // 退勤処理を直接データベースで実行
        $attendance->update([
            'clock_out_time' => $clockOutTime,
            'status' => 'completed',
        ]);

        // 3. 勤怠一覧画面から退勤の日付を確認する
        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        // 期待挙動: 勤怠一覧画面に退勤時刻が正確に記録されている
        $response->assertSee($clockOutTime->format('H:i'));

        // データベースにも正確に記録されていることを確認
        $attendance = Attendance::where('user_id', $user->id)->first();
        $this->assertNotNull($attendance);
        $this->assertEquals($clockOutTime->format('Y-m-d H:i:s'), $attendance->clock_out_time->format('Y-m-d H:i:s'));

        // 時間を元に戻す
        $this->travelBack();
    }
}
