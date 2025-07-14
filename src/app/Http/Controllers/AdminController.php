<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\Attendance;
use App\Models\User;
use App\Models\AttendanceRequest as AttendanceRequestModel;
use App\Models\Breaktime;
use App\Models\BreakRequest;
use Carbon\Carbon;
use App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\AttendanceFormRequest;

class AdminController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.login');
    }

    public function login(AdminLoginRequest $request)
    {
        $credentials = $request->validated();

        $admin = \App\Models\Admin::where('email', $credentials['email'])->first();

        if ($admin && Hash::check($credentials['password'], $admin->password)) {
            $request->session()->put('admin_logged_in', true);
            $request->session()->put('admin_email', $admin->email);
            $request->session()->regenerate();

            return redirect()->route('admin.attendances');
        }

        return back()->withErrors([
            'email' => 'ログイン情報が登録されていません',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('admin_logged_in');
        $request->session()->forget('admin_email');

        $request->session()->invalidate();
        $request->session()->regenerate();

        return redirect('/admin/login');
    }

    public function users()
    {
        $users = User::orderBy('name', 'asc')->get();

        return view('admin.users', compact('users'));
    }

    public function requests(Request $request)
    {
        $status = $request->get('status', 'pending');

        $pendingCount = AttendanceRequestModel::where('status', 'pending')->count() +
            BreakRequest::where('status', 'pending')->count();
        $approvedCount = AttendanceRequestModel::where('status', 'approved')->count() +
            BreakRequest::where('status', 'approved')->count();

        $attendanceRequests = AttendanceRequestModel::with('user')
            ->where('status', $status)
            ->get()
            ->map(function ($request) {
                $request->request_type = 'attendance';
                return $request;
            });

        $breakRequests = BreakRequest::with('user')
            ->where('status', $status)
            ->get()
            ->map(function ($request) {
                $request->request_type = 'break';
                return $request;
            });

        $allRequests = $attendanceRequests->concat($breakRequests)
            ->sortByDesc('created_at');

        $allRequests = $allRequests->map(function ($request) {
            $request->displayData = $this->prepareRequestDisplayData($request);
            return $request;
        });

        return view('admin.requests', compact('allRequests', 'status', 'pendingCount', 'approvedCount'));
    }

    public function attendances(Request $request)
    {
        $date = $request->get('date', now()->format('Y-m-d'));
        $selectedDate = Carbon::parse($date);

        $prevDate = $selectedDate->copy()->subDay();
        $nextDate = $selectedDate->copy()->addDay();

        $attendances = Attendance::with(['user', 'breaks'])
            ->where(function ($query) use ($selectedDate) {
                $query->whereDate('created_at', $selectedDate)
                    ->orWhereDate('clock_in_time', $selectedDate)
                    ->orWhereDate('clock_out_time', $selectedDate);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        $allUsers = User::orderBy('name', 'asc')->get();

        $approvedAttendanceRequests = AttendanceRequestModel::where('status', 'approved')
            ->where('target_date', $selectedDate->format('Y-m-d'))
            ->get();

        $allAttendanceData = [];
        foreach ($allUsers as $user) {
            $attendance = $attendances->where('user_id', $user->id)->first();

            $approvedRequest = $approvedAttendanceRequests->where('user_id', $user->id)->first();

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
            } elseif ($approvedRequest && $approvedRequest->break_info) {
                $totalBreakMinutes = 0;
                foreach ($approvedRequest->break_info as $breakInfo) {
                    if (isset($breakInfo['start_time']) && isset($breakInfo['end_time'])) {
                        try {
                            $startTimeStr = $breakInfo['start_time'];
                            $endTimeStr = $breakInfo['end_time'];

                            if (preg_match('/^\d{1,2}:\d{2}$/', $startTimeStr) && preg_match('/^\d{1,2}:\d{2}$/', $endTimeStr)) {
                                $startTime = Carbon::createFromFormat('H:i', $startTimeStr);
                                $endTime = Carbon::createFromFormat('H:i', $endTimeStr);
                            } else {
                                $startTime = Carbon::parse($startTimeStr);
                                $endTime = Carbon::parse($endTimeStr);
                            }

                            $totalBreakMinutes += $startTime->diffInMinutes($endTime);
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }
                $hours = floor($totalBreakMinutes / 60);
                $remainingMinutes = $totalBreakMinutes % 60;
                $breakTime = sprintf('%02d:%02d', $hours, $remainingMinutes);
            }

            $workTime = '';
            if ($attendance && $attendance->clock_in_time && $attendance->clock_out_time) {
                $clockIn = Carbon::parse($attendance->clock_in_time);
                $clockOut = Carbon::parse($attendance->clock_out_time);
                $totalMinutes = $clockOut->diffInMinutes($clockIn);

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
            } elseif ($approvedRequest && $approvedRequest->clock_in_time && $approvedRequest->clock_out_time) {
                $clockIn = Carbon::parse($approvedRequest->clock_in_time);
                $clockOut = Carbon::parse($approvedRequest->clock_out_time);
                $totalMinutes = $clockOut->diffInMinutes($clockIn);

                if ($approvedRequest->break_info) {
                    $totalBreakMinutes = 0;
                    foreach ($approvedRequest->break_info as $breakInfo) {
                        if (isset($breakInfo['start_time']) && isset($breakInfo['end_time'])) {
                            try {
                                $startTimeStr = $breakInfo['start_time'];
                                $endTimeStr = $breakInfo['end_time'];

                                if (preg_match('/^\d{1,2}:\d{2}$/', $startTimeStr) && preg_match('/^\d{1,2}:\d{2}$/', $endTimeStr)) {
                                    $startTime = Carbon::createFromFormat('H:i', $startTimeStr);
                                    $endTime = Carbon::createFromFormat('H:i', $endTimeStr);
                                } else {
                                    $startTime = Carbon::parse($startTimeStr);
                                    $endTime = Carbon::parse($endTimeStr);
                                }

                                $totalBreakMinutes += $startTime->diffInMinutes($endTime);
                            } catch (\Exception $e) {
                                continue;
                            }
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
                'approvedRequest' => $approvedRequest,
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

    public function attendanceDetail($id, Request $request)
    {
        if ($id == 0) {
            $userId = $request->get('user_id');
            $date = $request->get('date', now()->format('Y-m-d'));

            $user = User::find($userId);
            $selectedDate = Carbon::parse($date);

            $attendanceRequest = AttendanceRequestModel::where('user_id', $userId)
                ->where('target_date', $date)
                ->where('status', 'pending')
                ->first();

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

        $user = DB::table('users')->where('id', $attendance->user_id)->first();

        $attendanceRequest = AttendanceRequestModel::where('user_id', $attendance->user_id)
            ->where('target_date', $attendance->created_at->format('Y-m-d'))
            ->where('status', 'pending')
            ->first();

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

    public function attendanceUpdate(AttendanceFormRequest $request, $id)
    {
        $attendance = Attendance::findOrFail($id);

        $validated = $request->validated();

        // デバッグログ
        Log::info('attendanceUpdate called', [
            'attendance_id' => $id,
            'validated_data' => $validated,
            'all_request_data' => $request->all(),
        ]);

        $date = $validated['date'];

        $updateData = [
            'notes' => $validated['notes'] ?? null,
        ];

        if ($validated['clock_in_time'] ?? null) {
            $clockInTime = $validated['clock_in_time'];
            if (preg_match('/^\d{1,2}:\d{2}$/', $clockInTime)) {
                $clockInTime = $date . ' ' . $clockInTime . ':00';
            }
            $updateData['clock_in_time'] = $clockInTime;
        }
        if ($validated['clock_out_time'] ?? null) {
            $clockOutTime = $validated['clock_out_time'];
            if (preg_match('/^\d{1,2}:\d{2}$/', $clockOutTime)) {
                $clockOutTime = $date . ' ' . $clockOutTime . ':00';
            }
            $updateData['clock_out_time'] = $clockOutTime;
        }

        $attendance->update($updateData);

        $this->updateBreakTimesWithDate($attendance, $request, $date);

        $this->approvePendingRequests($attendance->user_id, $date);

        return redirect()->route('admin.attendances');
    }

    public function attendanceStore(AttendanceFormRequest $request)
    {
        $validated = $request->validated();

        $date = $validated['date'];

        $createData = [
            'user_id' => $validated['user_id'],
            'notes' => $validated['notes'] ?? null,
        ];

        if ($validated['clock_in_time'] ?? null) {
            $clockInTime = $validated['clock_in_time'];
            if (preg_match('/^\d{1,2}:\d{2}$/', $clockInTime)) {
                $clockInTime = $date . ' ' . $clockInTime . ':00';
            }
            $createData['clock_in_time'] = $clockInTime;
        }
        if ($validated['clock_out_time'] ?? null) {
            $clockOutTime = $validated['clock_out_time'];
            if (preg_match('/^\d{1,2}:\d{2}$/', $clockOutTime)) {
                $clockOutTime = $date . ' ' . $clockOutTime . ':00';
            }
            $createData['clock_out_time'] = $clockOutTime;
        }

        $attendance = Attendance::create($createData);

        $this->updateBreakTimes($attendance, $request);

        $this->approvePendingRequests($attendance->user_id, $date);

        return redirect()->route('admin.attendances');
    }

    public function showApprovalPage($id)
    {
        // まずAttendanceRequestを探す
        $request = AttendanceRequestModel::with('user')->find($id);

        if ($request) {
            // AttendanceRequestの場合
            if ($request->attendance_id) {
                $attendance = Attendance::with('breaks')->find($request->attendance_id);
            } else {
                $attendance = null;
            }

            // 同じ日付の休憩リクエストも取得
            $breakRequests = BreakRequest::where('user_id', $request->user_id)
                ->where('target_date', $request->target_date)
                ->where('status', 'pending')
                ->with(['break'])
                ->get();

            return view('admin.approval', compact('request', 'attendance', 'breakRequests'));
        } else {
            // BreakRequestの場合
            $request = BreakRequest::with('user')->findOrFail($id);

            // 関連するAttendanceを取得
            $attendance = null;
            if ($request->break_id) {
                $break = Breaktime::with('attendance')->find($request->break_id);
                if ($break) {
                    $attendance = $break->attendance;
                }
            } else {
                // break_idがnullの場合は、target_dateからAttendanceを探す
                $attendance = Attendance::where('user_id', $request->user_id)
                    ->whereDate('created_at', $request->target_date)
                    ->first();
            }

            $breakRequests = collect([$request]);

            return view('admin.approval', compact('request', 'attendance', 'breakRequests'));
        }
    }

    public function showBreakRequestDetail($id)
    {
        $request = BreakRequest::with('user')->findOrFail($id);

        // 関連するAttendanceを取得
        $attendance = null;
        if ($request->break_id) {
            $break = Breaktime::with('attendance')->find($request->break_id);
            if ($break) {
                $attendance = $break->attendance;
            }
        } else {
            // break_idがnullの場合は、target_dateからAttendanceを探す
            $attendance = Attendance::where('user_id', $request->user_id)
                ->whereDate('created_at', $request->target_date)
                ->first();
        }

        return view('admin.break_request_detail', compact('request', 'attendance'));
    }

    public function approveRequest(Request $request, $id)
    {
        // まずAttendanceRequestを探す
        $attendanceRequest = AttendanceRequestModel::find($id);

        if ($attendanceRequest) {
            // AttendanceRequestの場合
            if ($attendanceRequest->status !== 'pending') {
                return back()->withErrors(['general' => 'この申請は既に処理済みです']);
            }

            $attendanceRequest->update([
                'status' => 'approved',
            ]);

            // 同じ日付の休憩リクエストも承認
            $breakRequests = BreakRequest::where('user_id', $attendanceRequest->user_id)
                ->where('target_date', $attendanceRequest->target_date)
                ->where('status', 'pending')
                ->get();

            foreach ($breakRequests as $breakRequest) {
                $breakRequest->update(['status' => 'approved']);
            }

            if ($attendanceRequest->request_type === 'update') {
                $attendance = $attendanceRequest->attendance;
                $updateData = [];

                if ($attendanceRequest->clock_in_time) {
                    $updateData['clock_in_time'] = $attendanceRequest->clock_in_time;
                }
                if ($attendanceRequest->clock_out_time) {
                    $updateData['clock_out_time'] = $attendanceRequest->clock_out_time;
                }

                $attendance->update($updateData);

                // 休憩時間も更新
                $this->updateBreakTimesFromRequests($attendance, $breakRequests);
            } else {
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

                $this->processBreakRequestsAfterApproval($attendance, $attendanceRequest);
            }
        } else {
            // BreakRequestの場合
            $breakRequest = BreakRequest::findOrFail($id);

            if ($breakRequest->status !== 'pending') {
                return back()->withErrors(['general' => 'この申請は既に処理済みです']);
            }

            $breakRequest->update([
                'status' => 'approved',
            ]);

            // 関連するAttendanceを取得して更新
            $attendance = null;
            if ($breakRequest->break_id) {
                $break = Breaktime::find($breakRequest->break_id);
                if ($break) {
                    $attendance = $break->attendance;
                }
            } else {
                $attendance = Attendance::where('user_id', $breakRequest->user_id)
                    ->whereDate('created_at', $breakRequest->target_date)
                    ->first();
            }

            if ($attendance && $breakRequest->request_type === 'update') {
                // 既存の休憩を更新
                if ($breakRequest->break_id) {
                    $break = Breaktime::find($breakRequest->break_id);
                    if ($break) {
                        $break->update([
                            'start_time' => $breakRequest->start_time,
                            'end_time' => $breakRequest->end_time,
                        ]);
                    }
                }
            } elseif ($attendance && $breakRequest->request_type === 'create') {
                // 新しい休憩を作成
                $attendance->breaks()->create([
                    'start_time' => $breakRequest->start_time,
                    'end_time' => $breakRequest->end_time,
                ]);
            }
        }

        return redirect()->route('admin.requests');
    }

    public function userAttendanceList(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);
        $currentMonth = Carbon::create($year, $month, 1);

        $prevMonth = $currentMonth->copy()->subMonth();
        $nextMonth = $currentMonth->copy()->addMonth();

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
                if ($attendance->clock_in_time) {
                    return $attendance->clock_in_time->format('Y-m-d');
                } elseif ($attendance->clock_out_time) {
                    return $attendance->clock_out_time->format('Y-m-d');
                } else {
                    return $attendance->created_at->format('Y-m-d');
                }
            });

        $attendanceRequests = AttendanceRequestModel::where('user_id', $userId)
            ->where('status', 'approved')
            ->whereBetween('target_date', [$currentMonth->format('Y-m-d'), $currentMonth->copy()->endOfMonth()->format('Y-m-d')])
            ->get()
            ->keyBy(function ($request) {
                return $request->target_date->format('Y-m-d');
            });

        $calendar = [];
        $daysInMonth = $currentMonth->daysInMonth;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day);
            $dateKey = $date->format('Y-m-d');
            $attendance = $attendances->get($dateKey);
            $attendanceRequest = $attendanceRequests->get($dateKey);

            $breakTime = '';
            $workTime = '';

            if ($attendanceRequest) {
                if ($attendanceRequest->clock_in_time && $attendanceRequest->clock_out_time) {
                    $clockIn = Carbon::parse($attendanceRequest->clock_in_time);
                    $clockOut = Carbon::parse($attendanceRequest->clock_out_time);
                    $totalMinutes = $clockOut->diffInMinutes($clockIn);

                    if ($attendanceRequest->break_info) {
                        $totalBreakMinutes = 0;
                        foreach ($attendanceRequest->break_info as $breakInfo) {
                            if (isset($breakInfo['start_time']) && isset($breakInfo['end_time'])) {
                                try {
                                    $startTimeStr = $breakInfo['start_time'];
                                    $endTimeStr = $breakInfo['end_time'];

                                    if (preg_match('/^\d{1,2}:\d{2}$/', $startTimeStr) && preg_match('/^\d{1,2}:\d{2}$/', $endTimeStr)) {
                                        $startTime = Carbon::createFromFormat('H:i', $startTimeStr);
                                        $endTime = Carbon::createFromFormat('H:i', $endTimeStr);
                                    } else {
                                        $startTime = Carbon::parse($startTimeStr);
                                        $endTime = Carbon::parse($endTimeStr);
                                    }

                                    $totalBreakMinutes += $startTime->diffInMinutes($endTime);
                                } catch (\Exception $e) {
                                    continue;
                                }
                            }
                        }
                        $totalMinutes -= $totalBreakMinutes;
                        $hours = floor($totalBreakMinutes / 60);
                        $remainingMinutes = $totalBreakMinutes % 60;
                        $breakTime = sprintf('%02d:%02d', $hours, $remainingMinutes);
                    }

                    $hours = floor($totalMinutes / 60);
                    $minutes = $totalMinutes % 60;
                    $workTime = sprintf('%02d:%02d', $hours, $minutes);
                }
            } elseif ($attendance) {
                if ($attendance->breaks->isNotEmpty()) {
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

                if ($attendance->clock_in_time && $attendance->clock_out_time) {
                    $clockIn = Carbon::parse($attendance->clock_in_time);
                    $clockOut = Carbon::parse($attendance->clock_out_time);
                    $totalMinutes = $clockOut->diffInMinutes($clockIn);

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
            }

            $calendar[] = [
                'day' => $day,
                'weekday' => $this->getJapaneseWeekday($date->dayOfWeek),
                'date' => $date->format('Y-m-d'),
                'attendance' => $attendance,
                'attendanceRequest' => $attendanceRequest,
                'attendance_id' => $attendance ? $attendance->id : 0,
                'breakTime' => $breakTime,
                'workTime' => $workTime,
                'isToday' => $date->isToday(),
                'isWeekend' => $date->isWeekend(),
            ];
        }

        return view('admin.list', compact('user', 'calendar', 'currentMonth', 'prevMonth', 'nextMonth'));
    }

    public function userAttendanceCsv(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);
        $currentMonth = Carbon::create($year, $month, 1);

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
                if ($attendance->clock_in_time) {
                    return $attendance->clock_in_time->format('Y-m-d');
                } elseif ($attendance->clock_out_time) {
                    return $attendance->clock_out_time->format('Y-m-d');
                } else {
                    return $attendance->created_at->format('Y-m-d');
                }
            });

        $attendanceRequests = AttendanceRequestModel::where('user_id', $userId)
            ->where('status', 'approved')
            ->whereBetween('target_date', [$currentMonth->format('Y-m-d'), $currentMonth->copy()->endOfMonth()->format('Y-m-d')])
            ->get()
            ->keyBy(function ($request) {
                return $request->target_date->format('Y-m-d');
            });

        $csvData = [];
        $csvData[] = ['日付', '曜日', '出勤時間', '退勤時間', '休憩時間', '勤務時間', '備考'];

        $daysInMonth = $currentMonth->daysInMonth;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day);
            $dateKey = $date->format('Y-m-d');
            $attendance = $attendances->get($dateKey);
            $attendanceRequest = $attendanceRequests->get($dateKey);

            $breakTime = '';
            $workTime = '';

            if ($attendanceRequest) {
                if ($attendanceRequest->clock_in_time && $attendanceRequest->clock_out_time) {
                    $clockIn = Carbon::parse($attendanceRequest->clock_in_time);
                    $clockOut = Carbon::parse($attendanceRequest->clock_out_time);
                    $totalMinutes = $clockOut->diffInMinutes($clockIn);

                    if ($attendanceRequest->break_info) {
                        $totalBreakMinutes = 0;
                        foreach ($attendanceRequest->break_info as $breakInfo) {
                            if (isset($breakInfo['start_time']) && isset($breakInfo['end_time'])) {
                                try {
                                    $startTimeStr = $breakInfo['start_time'];
                                    $endTimeStr = $breakInfo['end_time'];

                                    if (preg_match('/^\d{1,2}:\d{2}$/', $startTimeStr) && preg_match('/^\d{1,2}:\d{2}$/', $endTimeStr)) {
                                        $startTime = Carbon::createFromFormat('H:i', $startTimeStr);
                                        $endTime = Carbon::createFromFormat('H:i', $endTimeStr);
                                    } else {
                                        $startTime = Carbon::parse($startTimeStr);
                                        $endTime = Carbon::parse($endTimeStr);
                                    }

                                    $totalBreakMinutes += $startTime->diffInMinutes($endTime);
                                } catch (\Exception $e) {
                                    continue;
                                }
                            }
                        }
                        $totalMinutes -= $totalBreakMinutes;
                        $hours = floor($totalBreakMinutes / 60);
                        $remainingMinutes = $totalBreakMinutes % 60;
                        $breakTime = sprintf('%02d:%02d', $hours, $remainingMinutes);
                    }

                    $hours = floor($totalMinutes / 60);
                    $minutes = $totalMinutes % 60;
                    $workTime = sprintf('%02d:%02d', $hours, $minutes);
                }
            } elseif ($attendance) {
                if ($attendance->breaks->isNotEmpty()) {
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

                if ($attendance->clock_in_time && $attendance->clock_out_time) {
                    $clockIn = Carbon::parse($attendance->clock_in_time);
                    $clockOut = Carbon::parse($attendance->clock_out_time);
                    $totalMinutes = $clockOut->diffInMinutes($clockIn);

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
            }

            $csvData[] = [
                $date->format('m/d'),
                $this->getJapaneseWeekday($date->dayOfWeek),
                $attendanceRequest ? ($attendanceRequest->clock_in_time ? Carbon::parse($attendanceRequest->clock_in_time)->format('H:i') : '') : ($attendance && $attendance->clock_in_time ? $attendance->clock_in_time->format('H:i') : ''),
                $attendanceRequest ? ($attendanceRequest->clock_out_time ? Carbon::parse($attendanceRequest->clock_out_time)->format('H:i') : '') : ($attendance && $attendance->clock_out_time ? $attendance->clock_out_time->format('H:i') : ''),
                $breakTime,
                $workTime,
                $attendanceRequest ? ($attendanceRequest->notes ?? '') : ($attendance ? ($attendance->notes ?? '') : '')
            ];
        }

        $filename = $user->name . '_' . $currentMonth->format('Y-m') . '_勤怠.csv';

        return response()->streamDownload(function () use ($csvData) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function getJapaneseWeekday($dayOfWeek)
    {
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        return $weekdays[$dayOfWeek];
    }

    private function updateBreakTimes($attendance, $request)
    {
        $date = $attendance->created_at->format('Y-m-d');

        // デバッグログ
        Log::info('updateBreakTimes called', [
            'attendance_id' => $attendance->id,
            'break1_start_time' => $request->break1_start_time,
            'break1_end_time' => $request->break1_end_time,
            'break2_start_time' => $request->break2_start_time,
            'break2_end_time' => $request->break2_end_time,
        ]);

        $attendance->breaks()->delete();

        if ($request->break1_start_time) {
            $startTime = $request->break1_start_time;
            if (preg_match('/^\d{1,2}:\d{2}$/', $startTime)) {
                $startTime .= ':00';
            }

            $breakData = [
                'attendance_id' => $attendance->id,
                'start_time' => $date . ' ' . $startTime,
            ];

            if ($request->break1_end_time) {
                $endTime = $request->break1_end_time;
                if (preg_match('/^\d{1,2}:\d{2}$/', $endTime)) {
                    $endTime .= ':00';
                }
                $breakData['end_time'] = $date . ' ' . $endTime;
            }

            Log::info('Creating break1', $breakData);
            Breaktime::create($breakData);
        }

        if ($request->break2_start_time) {
            $startTime = $request->break2_start_time;
            if (preg_match('/^\d{1,2}:\d{2}$/', $startTime)) {
                $startTime .= ':00';
            }

            $breakData = [
                'attendance_id' => $attendance->id,
                'start_time' => $date . ' ' . $startTime,
            ];

            if ($request->break2_end_time) {
                $endTime = $request->break2_end_time;
                if (preg_match('/^\d{1,2}:\d{2}$/', $endTime)) {
                    $endTime .= ':00';
                }
                $breakData['end_time'] = $date . ' ' . $endTime;
            }

            Log::info('Creating break2', $breakData);
            Breaktime::create($breakData);
        }
    }

    private function updateBreakTimesWithDate($attendance, $request, $date)
    {
        // デバッグログ
        Log::info('updateBreakTimesWithDate called', [
            'attendance_id' => $attendance->id,
            'date' => $date,
            'break1_start_time' => $request->break1_start_time,
            'break1_end_time' => $request->break1_end_time,
            'break2_start_time' => $request->break2_start_time,
            'break2_end_time' => $request->break2_end_time,
        ]);

        $attendance->breaks()->delete();

        if ($request->break1_start_time) {
            $startTime = $request->break1_start_time;
            if (preg_match('/^\d{1,2}:\d{2}$/', $startTime)) {
                $startTime .= ':00';
            }

            $breakData = [
                'attendance_id' => $attendance->id,
                'start_time' => $date . ' ' . $startTime,
            ];

            if ($request->break1_end_time) {
                $endTime = $request->break1_end_time;
                if (preg_match('/^\d{1,2}:\d{2}$/', $endTime)) {
                    $endTime .= ':00';
                }
                $breakData['end_time'] = $date . ' ' . $endTime;
            }

            Log::info('Creating break1 with date', $breakData);
            Breaktime::create($breakData);
        }

        if ($request->break2_start_time) {
            $startTime = $request->break2_start_time;
            if (preg_match('/^\d{1,2}:\d{2}$/', $startTime)) {
                $startTime .= ':00';
            }

            $breakData = [
                'attendance_id' => $attendance->id,
                'start_time' => $date . ' ' . $startTime,
            ];

            if ($request->break2_end_time) {
                $endTime = $request->break2_end_time;
                if (preg_match('/^\d{1,2}:\d{2}$/', $endTime)) {
                    $endTime .= ':00';
                }
                $breakData['end_time'] = $date . ' ' . $endTime;
            }

            Log::info('Creating break2 with date', $breakData);
            Breaktime::create($breakData);
        }
    }

    private function approvePendingRequests($userId, $targetDate)
    {
        AttendanceRequestModel::where('user_id', $userId)
            ->where('target_date', $targetDate)
            ->where('status', 'pending')
            ->update(['status' => 'approved']);

        BreakRequest::where('user_id', $userId)
            ->where('target_date', $targetDate)
            ->where('status', 'pending')
            ->update(['status' => 'approved']);
    }

    private function prepareRequestDisplayData($request)
    {
        $data = [];

        if ($request->request_type === 'attendance') {
            $data['clockInTime'] = $request->clock_in_time ? $request->clock_in_time->format('H:i') : '';
            $data['clockOutTime'] = $request->clock_out_time ? $request->clock_out_time->format('H:i') : '';
            $data['notes'] = $request->notes ?? '';

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
            $data['startTime'] = $request->start_time ? $request->start_time->format('H:i') : '';
            $data['endTime'] = $request->end_time ? $request->end_time->format('H:i') : '';
            $data['notes'] = $request->notes ?? '';
            $data['requestType'] = $request->request_type === 'create' ? '新規作成' : '修正申請';
        }

        return $data;
    }

    private function processBreakRequestsAfterApproval($attendance, $attendanceRequest)
    {
        $breakRequests = BreakRequest::where('user_id', $attendanceRequest->user_id)
            ->where('target_date', $attendanceRequest->target_date)
            ->where('status', 'approved')
            ->get();

        foreach ($breakRequests as $breakRequest) {
            $breakRequest->update([
                'status' => 'approved',
                'attendance_id' => $attendance->id,
            ]);

            if ($breakRequest->break) {
                // 既存の休憩データを更新
                $updateData = [];

                if ($breakRequest->start_time) {
                    $updateData['start_time'] = $breakRequest->target_date . ' ' . $breakRequest->start_time;
                }
                if ($breakRequest->end_time) {
                    $updateData['end_time'] = $breakRequest->target_date . ' ' . $breakRequest->end_time;
                }

                $breakRequest->break->update($updateData);
            } else {
                // 新規作成の場合は新しい休憩データを作成
                $createData = [
                    'attendance_id' => $attendance->id,
                ];

                if ($breakRequest->start_time) {
                    $createData['start_time'] = $breakRequest->target_date . ' ' . $breakRequest->start_time;
                }
                if ($breakRequest->end_time) {
                    $createData['end_time'] = $breakRequest->target_date . ' ' . $breakRequest->end_time;
                }

                $newBreak = Breaktime::create($createData);

                // 作成した休憩データのIDをBreakRequestに設定
                $breakRequest->update(['break_id' => $newBreak->id]);
            }
        }
    }

    private function updateBreakTimesFromRequests($attendance, $breakRequests)
    {
        foreach ($breakRequests as $breakRequest) {
            if ($breakRequest->break) {
                // 既存の休憩データを更新
                $updateData = [];

                if ($breakRequest->start_time) {
                    $updateData['start_time'] = $breakRequest->target_date . ' ' . $breakRequest->start_time->format('H:i:s');
                }
                if ($breakRequest->end_time) {
                    $updateData['end_time'] = $breakRequest->target_date . ' ' . $breakRequest->end_time->format('H:i:s');
                }

                $breakRequest->break->update($updateData);
            } else {
                // 新規作成の場合は新しい休憩データを作成
                $createData = [
                    'attendance_id' => $attendance->id,
                ];

                if ($breakRequest->start_time) {
                    $createData['start_time'] = $breakRequest->target_date . ' ' . $breakRequest->start_time->format('H:i:s');
                }
                if ($breakRequest->end_time) {
                    $createData['end_time'] = $breakRequest->target_date . ' ' . $breakRequest->end_time->format('H:i:s');
                }

                $newBreak = Breaktime::create($createData);

                // 作成した休憩データのIDをBreakRequestに設定
                $breakRequest->update(['break_id' => $newBreak->id]);
            }
        }
    }
}
