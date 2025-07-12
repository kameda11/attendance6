<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Breaktime;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->info('ユーザーが存在しません。先にUserSeederを実行してください。');
            return;
        }

        for ($i = 30; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);

            if ($date->isWeekend()) {
                continue;
            }

            foreach ($users as $user) {
                if (rand(1, 100) <= 80) {
                    $this->createAttendanceRecord($user, $date);
                }
            }
        }
    }

    private function createAttendanceRecord($user, $date)
    {
        $existingAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in_time', $date->toDateString())
            ->first();

        if ($existingAttendance) {
            return;
        }

        $clockInHour = rand(8, 10);
        $clockInMinute = rand(0, 59);
        $clockInTime = $date->copy()->setTime($clockInHour, $clockInMinute);

        $clockOutHour = rand(17, 20);
        $clockOutMinute = rand(0, 59);
        $clockOutTime = $date->copy()->setTime($clockOutHour, $clockOutMinute);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'clock_in_time' => $clockInTime,
            'clock_out_time' => $clockOutTime,
            'status' => 'completed',
            'notes' => $this->getRandomNotes(),
        ]);

        $breakCount = rand(1, 2);
        $this->createBreakTimes($attendance, $clockInTime, $clockOutTime, $breakCount);
    }

    private function createBreakTimes($attendance, $clockInTime, $clockOutTime, $breakCount)
    {
        $workStartHour = $clockInTime->hour;
        $workEndHour = $clockOutTime->hour;

        for ($i = 0; $i < $breakCount; $i++) {
            $breakStartHour = $workStartHour + rand(1, 3);
            $breakStartMinute = rand(0, 59);
            $breakStartTime = $clockInTime->copy()->setTime($breakStartHour, $breakStartMinute);

            $breakDuration = rand(30, 60);
            $breakEndTime = $breakStartTime->copy()->addMinutes($breakDuration);

            if ($breakEndTime->hour > $workEndHour) {
                $breakEndTime = $clockOutTime->copy()->subMinutes(rand(10, 30));
            }

            Breaktime::create([
                'attendance_id' => $attendance->id,
                'start_time' => $breakStartTime,
                'end_time' => $breakEndTime,
            ]);
        }
    }

    private function getRandomNotes()
    {
        $notes = [
            '通常勤務',
            '会議あり',
            '残業あり',
            '体調良好',
            'プロジェクト進行中',
            'クライアント対応',
            '資料作成',
            '研修参加',
            '営業活動',
            'システム開発',
        ];

        return $notes[array_rand($notes)];
    }
}
