<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Attendance;
use App\Models\User;
use App\Models\Admin;
use App\Models\AttendanceRequest as AttendanceRequestModel;
use App\Models\Breaktime;
use App\Models\BreakRequest;
use Carbon\Carbon;
use App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\AttendanceFormRequest;

class AdminController extends Controller
{
    /**
     * 管理者ログインフォームを表示
     */
    public function showLoginForm()
    {
        return view('admin.login');
    }

    /**
     * 管理者ログイン処理
     */
    public function login(AdminLoginRequest $request)
    {
        $credentials = $request->validated();

        // データベースから管理者を検索
        $admin = \App\Models\Admin::where('email', $credentials['email'])->first();

        if ($admin && Hash::check($credentials['password'], $admin->password)) {
            // 管理者セッションを作成
            $request->session()->put('admin_logged_in', true);
            $request->session()->put('admin_email', $admin->email);
            $request->session()->regenerate();

            return redirect()->route('admin.attendances');
        }

        return back()->withErrors([
            'email' => 'メールアドレスまたはパスワードが正しくありません。',
        ])->onlyInput('email');
    }

    /**
     * 管理者ログアウト処理
     */
    public function logout(Request $request)
    {
        $request->session()->forget('admin_logged_in');
        $request->session()->forget('admin_email');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login.form');
    }

    /**
     * スタッフ一覧表示
     */
    public function users()
    {
        $users = User::orderBy('name', 'asc')->get();

        return view('admin.users', compact('users'));
    }

    /**
     * 申請一覧表示
     */
    public function requests(Request $request)
    {
        $status = $request->get('status', 'pending');

        // 承認待ちと承認済みの件数を取得（勤怠申請 + 休憩申請）
        $pendingCount = AttendanceRequestModel::where('status', 'pending')->count() +
            BreakRequest::where('status', 'pending')->count();
        $approvedCount = AttendanceRequestModel::where('status', 'approved')->count() +
            BreakRequest::where('status', 'approved')->count();

        // 勤怠申請を取得
        $attendanceRequests = AttendanceRequestModel::with('user')
            ->where('status', $status)
            ->get()
            ->map(function ($request) {
                $request->request_type = 'attendance';
                return $request;
            });

        // 休憩申請を取得
        $breakRequests = BreakRequest::with('user')
            ->where('status', $status)
            ->get()
            ->map(function ($request) {
                $request->request_type = 'break';
                return $request;
            });

        // 両方の申請を結合して日時順にソート
        $allRequests = $attendanceRequests->concat($breakRequests)
            ->sortByDesc('created_at');

        // 各申請の表示データを準備
        $allRequests = $allRequests->map(function ($request) {
            $request->displayData = $this->prepareRequestDisplayData($request);
            return $request;
        });

        return view('admin.requests', compact('allRequests', 'status', 'pendingCount', 'approvedCount'));
    }

    /**
     * 勤怠一覧表示
     */
    public function attendances(Request $request)
    {
        // 日付パラメータを取得（デフォルトは今日）
        $date = $request->get('date', now()->format('Y-m-d'));
        $selectedDate = Carbon::parse($date);

        // 前日・翌日の日付を計算
        $prevDate = $selectedDate->copy()->subDay();
        $nextDate = $selectedDate->copy()->addDay();

        // その日の全ユーザーの勤怠データを取得
        $attendances = Attendance::with(['user', 'breaks'])
            ->where(function ($query) use ($selectedDate) {
                $query->whereDate('created_at', $selectedDate)
                    ->orWhereDate('clock_in_time', $selectedDate)
                    ->orWhereDate('clock_out_time', $selectedDate);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        // 全ユーザーを取得（勤怠データがないユーザーも含める）
        $allUsers = User::orderBy('name', 'asc')->get();

        // 勤怠データがないユーザーも含めて配列を作成
        $allAttendanceData = [];
        foreach ($allUsers as $user) {
            $attendance = $attendances->where('user_id', $user->id)->first();

            // 休憩時間を計算
            $breakTime = '';
            if ($attendance && $attendance->breaks->isNotEmpty()) {
                $totalBreakMinutes = 0;
                foreach ($attendance->breaks as $break) {
                    if ($break->start_time && $break->end_time) {
                        $totalBreakMinutes += $break->start_time->diffInMinutes($break->end_time);
                    }
                }
                $hours = floor($totalBreakMinutes / 60);
                $remainingMinutes = $totalBreakMinutes % 60;
                $breakTime = sprintf('%02d:%02d', $hours, $remainingMinutes);
            }

            // 勤務時間を計算
            $workTime = '';
            if ($attendance && $attendance->clock_in_time && $attendance->clock_out_time) {
                $clockIn = Carbon::parse($attendance->clock_in_time);
                $clockOut = Carbon::parse($attendance->clock_out_time);
                $totalMinutes = $clockOut->diffInMinutes($clockIn);

                // 休憩時間を差し引く
                if ($attendance->breaks->isNotEmpty()) {
                    $totalBreakMinutes = 0;
                    foreach ($attendance->breaks as $break) {
                        if ($break->start_time && $break->end_time) {
                            $totalBreakMinutes += $break->start_time->diffInMinutes($break->end_time);
                        }
                    }
                    $totalMinutes -= $totalBreakMinutes;
                }

                $hours = floor($totalMinutes / 60);
                $minutes = $totalMinutes % 60;
                $workTime = sprintf('%02d:%02d', $hours, $minutes);
            }

            $allAttendanceData[] = [
                'user' => $user,
                'attendance' => $attendance,
                'breakTime' => $breakTime,
                'workTime' => $workTime
            ];
        }

        return view('admin.attendances', compact(
            'allAttendanceData',
            'selectedDate',
            'prevDate',
            'nextDate'
        ));
    }

    /**
     * 勤務時間を計算
     */
    private function calculateWorkTime($clockIn, $clockOut, $breakTimes = [])
    {
        $totalMinutes = $clockOut->diffInMinutes($clockIn);

        // 休憩時間を差し引く
        foreach ($breakTimes as $break) {
            if ($break->start_time && $break->end_time) {
                $breakMinutes = $break->start_time->diffInMinutes($break->end_time);
                $totalMinutes -= $breakMinutes;
            }
        }

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * 休憩時間を計算
     */
    private function calculateBreakTime($breakTimes)
    {
        $totalMinutes = 0;
        foreach ($breakTimes as $break) {
            if ($break->start_time && $break->end_time) {
                $totalMinutes += $break->start_time->diffInMinutes($break->end_time);
            }
        }

        $hours = floor($totalMinutes / 60);
        $remainingMinutes = $totalMinutes % 60;

        return sprintf('%02d:%02d', $hours, $remainingMinutes);
    }

    /**
     * 勤怠詳細表示
     */
    public function attendanceDetail($id, Request $request)
    {
        // IDが0の場合は新規作成ページとして扱う
        if ($id == 0) {
            // リクエストからユーザーIDと日付を取得
            $userId = $request->get('user_id');
            $date = $request->get('date', now()->format('Y-m-d'));

            $user = User::find($userId);
            $selectedDate = Carbon::parse($date);

            // その日付の申請情報を取得
            $attendanceRequest = AttendanceRequestModel::where('user_id', $userId)
                ->where('target_date', $date)
                ->where('status', 'pending')
                ->first();

            // その日付の休憩申請情報を取得
            $breakRequests = BreakRequest::where('user_id', $userId)
                ->where('target_date', $date)
                ->where('status', 'pending')
                ->get();

            return view('admin.detail', [
                'attendance' => null,
                'user' => $user,
                'selectedDate' => $selectedDate,
                'attendanceRequest' => $attendanceRequest,
                'breakRequests' => $breakRequests
            ]);
        }

        $attendance = Attendance::with(['breaks'])->find($id);

        if (!$attendance) {
            abort(404, '勤怠データが見つかりません');
        }

        // usersテーブルから直接ユーザー情報を取得
        $user = DB::table('users')->where('id', $attendance->user_id)->first();

        // その日付の申請情報を取得
        $attendanceRequest = AttendanceRequestModel::where('user_id', $attendance->user_id)
            ->where('target_date', $attendance->created_at->format('Y-m-d'))
            ->where('status', 'pending')
            ->first();

        // その日付の休憩申請情報を取得
        $breakRequests = BreakRequest::where('user_id', $attendance->user_id)
            ->where('target_date', $attendance->created_at->format('Y-m-d'))
            ->where('status', 'pending')
            ->get();

        return view('admin.detail', [
            'attendance' => $attendance,
            'user' => $user,
            'selectedDate' => $attendance->created_at,
            'attendanceRequest' => $attendanceRequest,
            'breakRequests' => $breakRequests
        ]);
    }

    /**
     * 勤怠更新処理
     */
    public function attendanceUpdate(AttendanceFormRequest $request, $id)
    {
        $attendance = Attendance::findOrFail($id);

        $validated = $request->validated();

        // 送信された日付を使用
        $date = $validated['date'];

        $updateData = [
            'notes' => $validated['notes'] ?? null,
        ];

        // 時間データの処理
        if ($validated['clock_in_time'] ?? null) {
            // 時刻のみの場合は日付と結合、完全な日時文字列の場合はそのまま使用
            $clockInTime = $validated['clock_in_time'];
            if (preg_match('/^\d{1,2}:\d{2}$/', $clockInTime)) {
                $clockInTime = $date . ' ' . $clockInTime . ':00';
            }
            $updateData['clock_in_time'] = $clockInTime;
        }
        if ($validated['clock_out_time'] ?? null) {
            // 時刻のみの場合は日付と結合、完全な日時文字列の場合はそのまま使用
            $clockOutTime = $validated['clock_out_time'];
            if (preg_match('/^\d{1,2}:\d{2}$/', $clockOutTime)) {
                $clockOutTime = $date . ' ' . $clockOutTime . ':00';
            }
            $updateData['clock_out_time'] = $clockOutTime;
        }

        $attendance->update($updateData);

        // 休憩時間の処理（日付を指定して更新）
        $this->updateBreakTimesWithDate($attendance, $request, $date);

        // 申請情報を承認状態に更新
        $this->approvePendingRequests($attendance->user_id, $date);

        return redirect()->route('admin.attendances')->with('success', '勤怠情報を更新しました。');
    }

    /**
     * 勤怠新規作成処理
     */
    public function attendanceStore(AttendanceFormRequest $request)
    {
        $validated = $request->validated();

        // 指定された日付を使用
        $date = $validated['date'];

        $createData = [
            'user_id' => $validated['user_id'],
            'notes' => $validated['notes'] ?? null,
        ];

        // 時間データの処理
        if ($validated['clock_in_time'] ?? null) {
            // 時刻のみの場合は日付と結合、完全な日時文字列の場合はそのまま使用
            $clockInTime = $validated['clock_in_time'];
            if (preg_match('/^\d{1,2}:\d{2}$/', $clockInTime)) {
                $clockInTime = $date . ' ' . $clockInTime . ':00';
            }
            $createData['clock_in_time'] = $clockInTime;
        }
        if ($validated['clock_out_time'] ?? null) {
            // 時刻のみの場合は日付と結合、完全な日時文字列の場合はそのまま使用
            $clockOutTime = $validated['clock_out_time'];
            if (preg_match('/^\d{1,2}:\d{2}$/', $clockOutTime)) {
                $clockOutTime = $date . ' ' . $clockOutTime . ':00';
            }
            $createData['clock_out_time'] = $clockOutTime;
        }

        $attendance = Attendance::create($createData);

        // 休憩時間の処理
        $this->updateBreakTimes($attendance, $request);

        // 申請情報を承認状態に更新
        $this->approvePendingRequests($attendance->user_id, $date);

        return redirect()->route('admin.attendances')->with('success', '勤怠情報を作成しました。');
    }

    /**
     * 申請一覧を表示
     */
    public function attendanceRequests(Request $request)
    {
        $status = $request->get('status', 'pending');

        // 承認待ちと承認済みの件数を取得（勤怠申請のみ）
        $pendingCount = AttendanceRequestModel::where('status', 'pending')->count();
        $approvedCount = AttendanceRequestModel::where('status', 'approved')->count();

        // 勤怠申請を取得
        $attendanceRequests = AttendanceRequestModel::with('user')
            ->where('status', $status)
            ->get()
            ->map(function ($request) {
                $request->request_type = 'attendance';
                return $request;
            });

        // 各申請の表示データを準備
        $allRequests = $attendanceRequests->map(function ($request) {
            $request->displayData = $this->prepareRequestDisplayData($request);
            return $request;
        });

        return view('admin.requests', compact('allRequests', 'status', 'pendingCount', 'approvedCount'));
    }

    /**
     * 申請詳細を表示
     */
    public function attendanceRequestDetail($id)
    {
        $request = AttendanceRequestModel::with(['user', 'attendance'])
            ->findOrFail($id);

        return view('admin.attendance-request-detail', compact('request'));
    }



    /**
     * 修正申請承認ページを表示
     */
    public function showApprovalPage($id)
    {
        $request = AttendanceRequestModel::with(['user', 'attendance.breakTimes'])
            ->findOrFail($id);

        return view('admin.approval', compact('request'));
    }

    /**
     * 申請を承認
     */
    public function approveRequest(Request $request, $id)
    {
        $attendanceRequest = AttendanceRequestModel::findOrFail($id);

        if ($attendanceRequest->status !== 'pending') {
            return back()->withErrors(['general' => 'この申請は既に処理済みです。']);
        }

        // 承認処理
        $attendanceRequest->update([
            'status' => 'approved',
        ]);

        // 勤怠データを更新または作成
        if ($attendanceRequest->request_type === 'update') {
            // 修正申請の場合
            $attendance = $attendanceRequest->attendance;
            $updateData = [];

            if ($attendanceRequest->clock_in_time) {
                $updateData['clock_in_time'] = $attendanceRequest->clock_in_time;
            }
            if ($attendanceRequest->clock_out_time) {
                $updateData['clock_out_time'] = $attendanceRequest->clock_out_time;
            }

            $attendance->update($updateData);
        } else {
            // 新規作成申請の場合
            $createData = [
                'user_id' => $attendanceRequest->user_id,
            ];

            if ($attendanceRequest->clock_in_time) {
                $createData['clock_in_time'] = $attendanceRequest->clock_in_time;
            }
            if ($attendanceRequest->clock_out_time) {
                $createData['clock_out_time'] = $attendanceRequest->clock_out_time;
            }

            $attendance = Attendance::create($createData);

            // 新規作成申請が承認された場合、休憩申請も処理する
            $this->processBreakRequestsAfterApproval($attendance, $attendanceRequest);
        }

        return redirect()->route('admin.attendance.requests')
            ->with('success', '申請を承認しました。');
    }
    /**
     * スタッフ別勤怠一覧を表示
     */
    public function userAttendanceList(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        // 年月パラメータを取得（デフォルトは現在の年月）
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);
        $currentMonth = Carbon::create($year, $month, 1);

        // 前月・翌月を計算
        $prevMonth = $currentMonth->copy()->subMonth();
        $nextMonth = $currentMonth->copy()->addMonth();

        // その月の勤怠データを取得
        $attendances = Attendance::with(['breaks'])
            ->where('user_id', $userId)
            ->where(function ($query) use ($year, $month) {
                $query->where(function ($q) use ($year, $month) {
                    $q->whereYear('created_at', $year)
                        ->whereMonth('created_at', $month);
                })->orWhere(function ($q) use ($year, $month) {
                    $q->whereYear('clock_in_time', $year)
                        ->whereMonth('clock_in_time', $month);
                })->orWhere(function ($q) use ($year, $month) {
                    $q->whereYear('clock_out_time', $year)
                        ->whereMonth('clock_out_time', $month);
                });
            })
            ->get()
            ->keyBy(function ($attendance) {
                // 実際の勤怠日を優先してキーとして使用
                if ($attendance->clock_in_time) {
                    return $attendance->clock_in_time->format('Y-m-d');
                } elseif ($attendance->clock_out_time) {
                    return $attendance->clock_out_time->format('Y-m-d');
                } else {
                    return $attendance->created_at->format('Y-m-d');
                }
            });

        // カレンダー配列を作成
        $calendar = [];
        $daysInMonth = $currentMonth->daysInMonth;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day);
            $dateKey = $date->format('Y-m-d');
            $attendance = $attendances->get($dateKey);

            // 休憩時間を計算
            $breakTime = '';
            if ($attendance && $attendance->breaks->isNotEmpty()) {
                $totalBreakMinutes = 0;
                foreach ($attendance->breaks as $break) {
                    if ($break->start_time && $break->end_time) {
                        $totalBreakMinutes += $break->start_time->diffInMinutes($break->end_time);
                    }
                }
                $hours = floor($totalBreakMinutes / 60);
                $remainingMinutes = $totalBreakMinutes % 60;
                $breakTime = sprintf('%02d:%02d', $hours, $remainingMinutes);
            }

            // 勤務時間を計算
            $workTime = '';
            if ($attendance && $attendance->clock_in_time && $attendance->clock_out_time) {
                $clockIn = Carbon::parse($attendance->clock_in_time);
                $clockOut = Carbon::parse($attendance->clock_out_time);
                $totalMinutes = $clockOut->diffInMinutes($clockIn);

                // 休憩時間を差し引く
                if ($attendance->breaks->isNotEmpty()) {
                    $totalBreakMinutes = 0;
                    foreach ($attendance->breaks as $break) {
                        if ($break->start_time && $break->end_time) {
                            $totalBreakMinutes += $break->start_time->diffInMinutes($break->end_time);
                        }
                    }
                    $totalMinutes -= $totalBreakMinutes;
                }

                $hours = floor($totalMinutes / 60);
                $minutes = $totalMinutes % 60;
                $workTime = sprintf('%02d:%02d', $hours, $minutes);
            }

            $calendar[] = [
                'day' => $day,
                'weekday' => $this->getJapaneseWeekday($date->dayOfWeek),
                'date' => $date->format('Y-m-d'),
                'attendance' => $attendance,
                'attendance_id' => $attendance ? $attendance->id : 0,
                'breakTime' => $breakTime,
                'workTime' => $workTime,
                'isToday' => $date->isToday(),
                'isWeekend' => $date->isWeekend(),
            ];
        }

        return view('admin.list', compact('user', 'calendar', 'currentMonth', 'prevMonth', 'nextMonth'));
    }

    /**
     * スタッフ別勤怠CSV出力
     */
    public function userAttendanceCsv(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        // 年月パラメータを取得（デフォルトは現在の年月）
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);
        $currentMonth = Carbon::create($year, $month, 1);

        // その月の勤怠データを取得
        $attendances = Attendance::with(['breaks'])
            ->where('user_id', $userId)
            ->where(function ($query) use ($year, $month) {
                $query->where(function ($q) use ($year, $month) {
                    $q->whereYear('created_at', $year)
                        ->whereMonth('created_at', $month);
                })->orWhere(function ($q) use ($year, $month) {
                    $q->whereYear('clock_in_time', $year)
                        ->whereMonth('clock_in_time', $month);
                })->orWhere(function ($q) use ($year, $month) {
                    $q->whereYear('clock_out_time', $year)
                        ->whereMonth('clock_out_time', $month);
                });
            })
            ->get()
            ->keyBy(function ($attendance) {
                // 実際の勤怠日を優先してキーとして使用
                if ($attendance->clock_in_time) {
                    return $attendance->clock_in_time->format('Y-m-d');
                } elseif ($attendance->clock_out_time) {
                    return $attendance->clock_out_time->format('Y-m-d');
                } else {
                    return $attendance->created_at->format('Y-m-d');
                }
            });

        // CSVデータを準備
        $csvData = [];
        $csvData[] = ['日付', '曜日', '出勤時間', '退勤時間', '休憩時間', '勤務時間', '備考'];

        $daysInMonth = $currentMonth->daysInMonth;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day);
            $dateKey = $date->format('Y-m-d');
            $attendance = $attendances->get($dateKey);

            // 休憩時間を計算
            $breakTime = '';
            if ($attendance && $attendance->breaks->isNotEmpty()) {
                $totalBreakMinutes = 0;
                foreach ($attendance->breaks as $break) {
                    if ($break->start_time && $break->end_time) {
                        $totalBreakMinutes += $break->start_time->diffInMinutes($break->end_time);
                    }
                }
                $hours = floor($totalBreakMinutes / 60);
                $remainingMinutes = $totalBreakMinutes % 60;
                $breakTime = sprintf('%02d:%02d', $hours, $remainingMinutes);
            }

            // 勤務時間を計算
            $workTime = '';
            if ($attendance && $attendance->clock_in_time && $attendance->clock_out_time) {
                $clockIn = Carbon::parse($attendance->clock_in_time);
                $clockOut = Carbon::parse($attendance->clock_out_time);
                $totalMinutes = $clockOut->diffInMinutes($clockIn);

                // 休憩時間を差し引く
                if ($attendance->breaks->isNotEmpty()) {
                    $totalBreakMinutes = 0;
                    foreach ($attendance->breaks as $break) {
                        if ($break->start_time && $break->end_time) {
                            $totalBreakMinutes += $break->start_time->diffInMinutes($break->end_time);
                        }
                    }
                    $totalMinutes -= $totalBreakMinutes;
                }

                $hours = floor($totalMinutes / 60);
                $minutes = $totalMinutes % 60;
                $workTime = sprintf('%02d:%02d', $hours, $minutes);
            }

            $csvData[] = [
                $date->format('m/d'),
                $this->getJapaneseWeekday($date->dayOfWeek),
                $attendance && $attendance->clock_in_time ? $attendance->clock_in_time->format('H:i') : '',
                $attendance && $attendance->clock_out_time ? $attendance->clock_out_time->format('H:i') : '',
                $breakTime,
                $workTime,
                '' // 備考欄（必要に応じて追加）
            ];
        }

        // CSVファイル名を設定
        $filename = $user->name . '_' . $currentMonth->format('Y-m') . '_勤怠.csv';

        // CSVレスポンスを返す
        return response()->streamDownload(function () use ($csvData) {
            $output = fopen('php://output', 'w');
            // BOMを追加してExcelで文字化けしないようにする
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * 日本語の曜日を取得
     */
    private function getJapaneseWeekday($dayOfWeek)
    {
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        return $weekdays[$dayOfWeek];
    }

    /**
     * 休憩時間を更新
     */
    private function updateBreakTimes($attendance, $request)
    {
        $date = $attendance->created_at->format('Y-m-d');

        // 既存の休憩時間を削除
        $attendance->breaks()->delete();

        // 休憩1の処理
        if ($request->break1_start_time) {
            $breakData = [
                'attendance_id' => $attendance->id,
                'start_time' => $date . ' ' . $request->break1_start_time . ':00',
            ];
            if ($request->break1_end_time) {
                $breakData['end_time'] = $date . ' ' . $request->break1_end_time . ':00';
            }
            Breaktime::create($breakData);
        }

        // 休憩2の処理
        if ($request->break2_start_time) {
            $breakData = [
                'attendance_id' => $attendance->id,
                'start_time' => $date . ' ' . $request->break2_start_time . ':00',
            ];
            if ($request->break2_end_time) {
                $breakData['end_time'] = $date . ' ' . $request->break2_end_time . ':00';
            }
            Breaktime::create($breakData);
        }
    }

    /**
     * 指定した日付で休憩時間を更新
     */
    private function updateBreakTimesWithDate($attendance, $request, $date)
    {
        // 既存の休憩時間を削除
        $attendance->breaks()->delete();

        // 休憩1の処理
        if ($request->break1_start_time) {
            $breakData = [
                'attendance_id' => $attendance->id,
                'start_time' => $date . ' ' . $request->break1_start_time . ':00',
            ];
            if ($request->break1_end_time) {
                $breakData['end_time'] = $date . ' ' . $request->break1_end_time . ':00';
            }
            Breaktime::create($breakData);
        }

        // 休憩2の処理
        if ($request->break2_start_time) {
            $breakData = [
                'attendance_id' => $attendance->id,
                'start_time' => $date . ' ' . $request->break2_start_time . ':00',
            ];
            if ($request->break2_end_time) {
                $breakData['end_time'] = $date . ' ' . $request->break2_end_time . ':00';
            }
            Breaktime::create($breakData);
        }
    }

    /**
     * 保留中の申請を承認状態に更新
     */
    private function approvePendingRequests($userId, $targetDate)
    {
        // 勤怠申請を承認
        AttendanceRequestModel::where('user_id', $userId)
            ->where('target_date', $targetDate)
            ->where('status', 'pending')
            ->update(['status' => 'approved']);

        // 休憩申請を承認
        BreakRequest::where('user_id', $userId)
            ->where('target_date', $targetDate)
            ->where('status', 'pending')
            ->update(['status' => 'approved']);
    }

    /**
     * 申請の表示データを準備
     */
    private function prepareRequestDisplayData($request)
    {
        $data = [];

        if ($request->request_type === 'attendance') {
            // 勤怠申請の場合
            $data['clockInTime'] = $request->clock_in_time ? $request->clock_in_time->format('H:i') : '';
            $data['clockOutTime'] = $request->clock_out_time ? $request->clock_out_time->format('H:i') : '';
            $data['notes'] = $request->notes ?? '';

            // 休憩情報（新規作成申請の場合）
            if ($request->break_info) {
                $breakInfo = $request->break_info;
                $data['break1StartTime'] = $breakInfo[0]['start_time'] ?? '';
                $data['break1EndTime'] = $breakInfo[0]['end_time'] ?? '';
                $data['break2StartTime'] = $breakInfo[1]['start_time'] ?? '';
                $data['break2EndTime'] = $breakInfo[1]['end_time'] ?? '';
            } else {
                $data['break1StartTime'] = '';
                $data['break1EndTime'] = '';
                $data['break2StartTime'] = '';
                $data['break2EndTime'] = '';
            }
        } elseif ($request->request_type === 'break') {
            // 休憩申請の場合
            $data['startTime'] = $request->start_time ? $request->start_time->format('H:i') : '';
            $data['endTime'] = $request->end_time ? $request->end_time->format('H:i') : '';
            $data['notes'] = $request->notes ?? '';
            $data['requestType'] = $request->request_type === 'create' ? '新規作成' : '修正申請';
        }

        return $data;
    }

    /**
     * 新規作成申請承認後の休憩申請処理
     */
    private function processBreakRequestsAfterApproval($attendance, $attendanceRequest)
    {
        // 勤怠申請に含まれる休憩情報を処理
        if ($attendanceRequest->break_info) {
            foreach ($attendanceRequest->break_info as $breakInfo) {
                $breakData = [
                    'attendance_id' => $attendance->id,
                ];

                if (isset($breakInfo['start_time'])) {
                    $breakData['start_time'] = $attendanceRequest->target_date . ' ' . $breakInfo['start_time'] . ':00';
                }
                if (isset($breakInfo['end_time'])) {
                    $breakData['end_time'] = $attendanceRequest->target_date . ' ' . $breakInfo['end_time'] . ':00';
                }

                Breaktime::create($breakData);
            }
        }

        // 既存の休憩申請も処理（修正申請の場合）
        $breakRequests = BreakRequest::where('user_id', $attendance->user_id)
            ->where('target_date', $attendanceRequest->target_date)
            ->where('status', 'pending')
            ->where('request_type', 'update')
            ->get();

        foreach ($breakRequests as $breakRequest) {
            // 休憩申請を承認状態に更新
            $breakRequest->update([
                'status' => 'approved',
                'attendance_id' => $attendance->id,
            ]);

            // 休憩データを更新
            if ($breakRequest->break) {
                $updateData = [];

                if ($breakRequest->start_time) {
                    $updateData['start_time'] = $breakRequest->target_date . ' ' . $breakRequest->start_time;
                }
                if ($breakRequest->end_time) {
                    $updateData['end_time'] = $breakRequest->target_date . ' ' . $breakRequest->end_time;
                }


                $breakRequest->break->update($updateData);
            }
        }
    }
}
