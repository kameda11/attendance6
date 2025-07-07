@extends('layouts.admin')
<link rel="stylesheet" href="{{ asset('css/admin/detail.css') }}">

@section('content')
<div class="attendance-detail-container">
    <div class="attendance-detail-header">
        <h1>勤怠詳細</h1>
    </div>

    <form action="{{ $attendance ? route('admin.attendance.update', ['id' => $attendance->id]) : route('admin.attendance.store') }}" method="POST">
        @csrf
        @if($attendance)
        @method('PUT')
        <input type="hidden" name="date" value="{{ $selectedDate ? $selectedDate->format('Y-m-d') : $attendance->created_at->format('Y-m-d') }}">
        @else
        <input type="hidden" name="user_id" value="{{ $user ? $user->id : '' }}">
        <input type="hidden" name="date" value="{{ $selectedDate ? $selectedDate->format('Y-m-d') : '' }}">
        @endif
        <div class="attendance-detail-table">
            <table>
                <tbody>
                    <tr>
                        <th>名前</th>
                        <td>{{ $user ? $user->name : '未設定' }}</td>
                    </tr>
                    <tr>
                        <th>日付</th>
                        <td>
                            @if($selectedDate)
                            <div class="date-section">
                                <span class="date-year-section">
                                    <span class="date-year">{{ $selectedDate->format('Y') }}</span><span class="date-unit">年</span>
                                </span>
                                <span class="date-month-day-section">
                                    <span class="date-month-day">{{ $selectedDate->format('n') }}</span><span class="date-unit">月</span>
                                    <span class="date-month-day">{{ $selectedDate->format('j') }}</span><span class="date-unit">日</span>
                                </span>
                            </div>
                            @elseif($attendance)
                            <div class="date-section">
                                <span class="date-year-section">
                                    <span class="date-year">{{ $attendance->created_at->format('Y') }}</span><span class="date-unit">年</span>
                                </span>
                                <span class="date-month-day-section">
                                    <span class="date-month-day">{{ $attendance->created_at->format('n') }}</span><span class="date-unit">月</span>
                                    <span class="date-month-day">{{ $attendance->created_at->format('j') }}</span><span class="date-unit">日</span>
                                </span>
                            </div>
                            @else
                            未設定
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>出勤・退勤</th>
                        <td>
                            <div class="time-inputs">
                                <div class="time-input">
                                    <input type="text" name="clock_in_time" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5" value="{{ old('clock_in_time', $attendanceRequest && $attendanceRequest->clock_in_time ? $attendanceRequest->clock_in_time->format('H:i') : ($attendance && $attendance->clock_in_time ? $attendance->clock_in_time->format('H:i') : '')) }}" inputmode="numeric" autocomplete="off">
                                </div>
                                <label>～</label>
                                <div class="time-input">
                                    <input type="text" name="clock_out_time" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5" value="{{ old('clock_out_time', $attendanceRequest && $attendanceRequest->clock_out_time ? $attendanceRequest->clock_out_time->format('H:i') : ($attendance && $attendance->clock_out_time ? $attendance->clock_out_time->format('H:i') : '')) }}" inputmode="numeric" autocomplete="off">
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>休憩</th>
                        <td>
                            @if($breakRequests && $breakRequests->count() > 0)
                            @php $firstBreakRequest = $breakRequests->first(); @endphp
                            <div class="break-item">
                                <span class="break-time">
                                    {{ $firstBreakRequest->start_time ? $firstBreakRequest->start_time->format('H:i') : '' }} ~
                                    {{ $firstBreakRequest->end_time ? $firstBreakRequest->end_time->format('H:i') : '' }}
                                </span>

                            </div>
                            @elseif($attendance && $attendance->breaks->count() > 0)
                            @php $firstBreak = $attendance->breaks->first(); @endphp
                            <div class="break-item">
                                <span class="break-time">
                                    {{ $firstBreak->start_time ? $firstBreak->start_time->format('H:i') : '' }} ~
                                    {{ $firstBreak->end_time ? $firstBreak->end_time->format('H:i') : '' }}
                                </span>

                            </div>
                            @else
                            <div class="time-inputs">
                                <div class="time-input">
                                    <input type="text" name="break1_start_time" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5" value="{{ old('break1_start_time') }}" inputmode="numeric" autocomplete="off">
                                </div>
                                <label>～</label>
                                <div class="time-input">
                                    <input type="text" name="break1_end_time" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5" value="{{ old('break1_end_time') }}" inputmode="numeric" autocomplete="off">
                                </div>
                            </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>休憩2</th>
                        <td>
                            @if($breakRequests && $breakRequests->count() > 1)
                            @php $secondBreakRequest = $breakRequests->get(1); @endphp
                            <div class="break-item">
                                <span class="break-time">
                                    {{ $secondBreakRequest->start_time ? $secondBreakRequest->start_time->format('H:i') : '' }} ~
                                    {{ $secondBreakRequest->end_time ? $secondBreakRequest->end_time->format('H:i') : '' }}
                                </span>

                            </div>
                            @elseif($attendance && $attendance->breaks->count() > 1)
                            @php $secondBreak = $attendance->breaks->get(1); @endphp
                            <div class="break-item">
                                <span class="break-time">
                                    {{ $secondBreak->start_time ? $secondBreak->start_time->format('H:i') : '' }} ~
                                    {{ $secondBreak->end_time ? $secondBreak->end_time->format('H:i') : '' }}
                                </span>

                            </div>
                            @else
                            <div class="time-inputs">
                                <div class="time-input">
                                    <input type="text" name="break2_start_time" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5" value="{{ old('break2_start_time') }}" inputmode="numeric" autocomplete="off">
                                </div>
                                <label>～</label>
                                <div class="time-input">
                                    <input type="text" name="break2_end_time" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5" value="{{ old('break2_end_time') }}" inputmode="numeric" autocomplete="off">
                                </div>
                            </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>備考</th>
                        <td>
                            <textarea name="notes" class="notes-textbox" rows="4" cols="50" required>{{ old('notes', $attendanceRequest && $attendanceRequest->notes ? $attendanceRequest->notes : ($attendance ? ($attendance->notes ?? '') : '')) }}</textarea>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="attendance-detail-actions">
            <button type="submit" class="btn btn-primary">修正</button>
        </div>
    </form>
</div>
@endsection