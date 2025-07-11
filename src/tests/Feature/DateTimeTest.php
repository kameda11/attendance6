<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DateTimeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 勤怠打刻画面の日時表示が現在の日時と一致することをテスト
     */
    public function test_attendance_page_displays_current_datetime()
    {
        // テスト用のユーザーを作成してログイン
        /** @var \App\Models\User $user */
        $user = \App\Models\User::factory()->create();
        $user = \App\Models\User::find($user->id);
        $this->actingAs($user);

        // 現在時刻を取得
        $currentDateTime = Carbon::now();
        $currentDate = $currentDateTime->format('Y年m月d日'); // 月と日を2桁で表示
        $currentTime = $currentDateTime->format('H:i');
        $currentWeekday = $currentDateTime->format('D'); // 英語の短縮形（Fri）

        // 勤怠打刻画面にアクセス
        $response = $this->get('/attendance');

        // レスポンスが正常であることを確認
        $response->assertStatus(200);
        $response->assertViewIs('attendance');

        // 画面に表示される日時情報を確認
        $response->assertSee($currentDate);
        $response->assertSee($currentTime);
        $response->assertSee($currentWeekday);

        // 日付と曜日が組み合わされた形式も確認
        $fullDateDisplay = $currentDate . '(' . $currentWeekday . ')';
        $response->assertSee($fullDateDisplay);
    }
}
