@extends('layouts.admin')
<link rel="stylesheet" href="{{ asset('css/admin/approval.css') }}">

@section('content')
<div class="attendance-detail-container">
    <div class="attendance-detail-header">
        <h1>勤怠詳細</h1>
    </div>

    <div class="attendance-detail-table">
        <table>
            <tbody>
                <tr>
                    <th>名前</th>
                    <td>{{ $request->user->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>日付</th>
                    <td>
                        @if($request->attendance)
                        <div class="date-section">
                            <span class="date-year-section">
                                <span class="date-year">{{ $request->attendance->created_at->format('Y') }}</span><span class="date-unit">年</span>
                            </span>
                            <span class="date-month-day-section">
                                <span class="date-month-day">{{ $request->attendance->created_at->format('n') }}</span><span class="date-unit">月</span>
                                <span class="date-month-day">{{ $request->attendance->created_at->format('j') }}</span><span class="date-unit">日</span>
                            </span>
                        </div>
                        @elseif($request->target_date)
                        <div class="date-section">
                            <span class="date-year-section">
                                <span class="date-year">{{ $request->target_date->format('Y') }}</span>年
                            </span>
                            <span class="date-month-day-section">
                                {{ $request->target_date->format('n') }}月
                                {{ $request->target_date->format('j') }}日
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
                        @if($request->clock_in_time || $request->clock_out_time)
                        <div class="time-display">
                            <span class="time-start">{{ $request->clock_in_time ? \Carbon\Carbon::parse($request->clock_in_time)->format('H:i') : '' }}</span>
                            <span class="time-separator">～</span>
                            <span class="time-end">{{ $request->clock_out_time ? \Carbon\Carbon::parse($request->clock_out_time)->format('H:i') : '' }}</span>
                        </div>
                        @else
                        <span class="no-data">未設定</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>休憩</th>
                    <td>
                        @if($request->break_info && count($request->break_info) > 0)
                        @php $firstBreak = $request->break_info[0]; @endphp
                        <div class="break-item">
                            <div class="break-time">
                                <span class="time-start">{{ $firstBreak['start_time'] ?? '' }}</span>
                                <span class="time-separator">～</span>
                                <span class="time-end">{{ $firstBreak['end_time'] ?? '' }}</span>
                            </div>
                        </div>
                        @elseif($request->attendance && $request->attendance->breaks->count() > 0)
                        @php $firstBreak = $request->attendance->breaks->first(); @endphp
                        <div class="break-item">
                            <div class="break-time">
                                <span class="time-start">{{ $firstBreak->start_time ? \Carbon\Carbon::parse($firstBreak->start_time)->format('H:i') : '' }}</span>
                                <span class="time-separator">～</span>
                                <span class="time-end">{{ $firstBreak->end_time ? \Carbon\Carbon::parse($firstBreak->end_time)->format('H:i') : '' }}</span>
                            </div>

                        </div>
                        @else
                        <span class="no-data"></span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>休憩2</th>
                    <td>
                        @if($request->break_info && count($request->break_info) > 1)
                        @php $secondBreak = $request->break_info[1]; @endphp
                        <div class="break-item">
                            <div class="break-time">
                                <span class="time-start">{{ $secondBreak['start_time'] ?? '' }}</span>
                                <span class="time-separator">～</span>
                                <span class="time-end">{{ $secondBreak['end_time'] ?? '' }}</span>
                            </div>
                        </div>
                        @elseif($request->attendance && $request->attendance->breaks->count() > 1)
                        @php $secondBreak = $request->attendance->breaks->get(1); @endphp
                        <div class="break-item">
                            <div class="break-time">
                                <span class="time-start">{{ $secondBreak->start_time ? \Carbon\Carbon::parse($secondBreak->start_time)->format('H:i') : '' }}</span>
                                <span class="time-separator">～</span>
                                <span class="time-end">{{ $secondBreak->end_time ? \Carbon\Carbon::parse($secondBreak->end_time)->format('H:i') : '' }}</span>
                            </div>

                        </div>
                        @else
                        <span class="no-data"></span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>備考</th>
                    <td>
                        @if($request->notes)
                        <div class="notes-content">{{ $request->notes }}</div>
                        @else
                        <span class="no-data"></span>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="attendance-detail-actions">
        @if($request->status === 'pending')
        <form action="{{ route('admin.attendance.request.approve', ['id' => $request->id]) }}" method="POST" style="display: inline;">
            @csrf
            <button type="submit" class="btn btn-primary">承認</button>
        </form>
        @else
        <button class="btn btn-secondary" disabled>
            承認済み
        </button>
        @endif
    </div>
</div>


@endsection