@extends('layouts.admin')

@section('content')
<link rel="stylesheet" href="{{ asset('css/admin/list.css') }}">

<div class="admin-attendances-container">
    <div class="header-content">
        <h1>{{ $user->name }}の勤怠</h1>
    </div>

    <div class="date-navigation">
        <div class="nav-content">
            <a href="{{ route('admin.user.attendance.list', ['userId' => $user->id, 'year' => $prevMonth->year, 'month' => $prevMonth->month]) }}" class="btn btn-secondary">
                <svg class="arrow-left" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 12H5M12 19L5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                前月
            </a>

            <div class="current-date">
                <div class="date-selector">
                    <input type="month" id="monthSelector" value="{{ $currentMonth->format('Y-m') }}" class="month-input">
                    <label for="monthSelector" class="calendar-button">
                        <img src="{{ asset('storage/calendar.png') }}" alt="カレンダー" class="calendar-icon">
                    </label>
                </div>
                <h2>{{ $currentMonth->format('Y/m') }}</h2>
            </div>

            <a href="{{ route('admin.user.attendance.list', ['userId' => $user->id, 'year' => $nextMonth->year, 'month' => $nextMonth->month]) }}" class="btn btn-secondary">翌月
                <svg class="arrow-right" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 12H19M12 5L19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </a>
        </div>
    </div>

    <main class="attendances-main">
        <div class="attendances-table">
            <table>
                <thead>
                    <tr>
                        <th>日付</th>
                        <th>出勤</th>
                        <th>退勤</th>
                        <th>休憩</th>
                        <th>合計</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($calendar as $date)
                    <tr class="{{ $date['isToday'] ? 'today' : '' }} {{ $date['isWeekend'] ? 'weekend' : '' }}">
                        <td>{{ $currentMonth->format('m') }}/{{ sprintf('%02d', $date['day']) }}({{ $date['weekday'] }})</td>
                        <td>
                            @if($date['attendanceRequest'])
                            {{ $date['attendanceRequest']->clock_in_time ? $date['attendanceRequest']->clock_in_time->format('H:i') : '' }}
                            @elseif($date['attendance'])
                            {{ $date['attendance']->clock_in_time ? $date['attendance']->clock_in_time->format('H:i') : '' }}
                            @endif
                        </td>
                        <td>
                            @if($date['attendanceRequest'])
                            {{ $date['attendanceRequest']->clock_out_time ? $date['attendanceRequest']->clock_out_time->format('H:i') : '' }}
                            @elseif($date['attendance'])
                            {{ $date['attendance']->clock_out_time ? $date['attendance']->clock_out_time->format('H:i') : '' }}
                            @endif
                        </td>
                        <td>{{ $date['breakTime'] }}</td>
                        <td>{{ $date['workTime'] }}</td>
                        <td>
                            @if($date['attendance_id'] > 0)
                            <a href="{{ route('admin.attendance.detail', ['id' => $date['attendance_id']]) }}" class="action-button detail">詳細</a>
                            @else
                            <a href="{{ route('admin.attendance.detail', ['id' => 0, 'user_id' => $user->id, 'date' => $date['date']]) }}" class="action-button detail">詳細</a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </main>
    <div class="header-actions">
        <a href="{{ route('admin.user.attendance.csv', ['userId' => $user->id, 'year' => $currentMonth->year, 'month' => $currentMonth->month]) }}" class="csv-button">
            CSV出力
        </a>
    </div>
</div>
@endsection

@section('script')
<script>
    document.getElementById('monthSelector').addEventListener('change', function() {
        const selectedMonth = this.value;
        const [year, month] = selectedMonth.split('-');
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('year', year);
        currentUrl.searchParams.set('month', month);
        window.location.href = currentUrl.toString();
    });

    // カレンダーアイコンをクリックしたときにmonth pickerを開く
    document.querySelector('.calendar-button').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('monthSelector').showPicker();
    });
</script>
@endsection