<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login.form');
Route::post('/login', [LoginController::class, 'login'])->name('login');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/register', [LoginController::class, 'showRegisterForm'])->name('register.form');
Route::post('/register', [LoginController::class, 'register'])->name('register');

Route::get('/admin/login', [AdminController::class, 'showLoginForm'])->name('admin.login.form');
Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login');
Route::post('/admin/logout', [AdminController::class, 'logout'])->name('admin.logout');

Route::middleware(['admin.auth'])->group(function () {
    Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/admin/requests', [AdminController::class, 'requests'])->name('admin.requests');
    Route::get('/admin/attendances', [AdminController::class, 'attendances'])->name('admin.attendances');
    Route::get('/admin/attendance/{id}', [AdminController::class, 'attendanceDetail'])->name('admin.attendance.detail');
    Route::put('/admin/attendance/update/{id}', [AdminController::class, 'attendanceUpdate'])->name('admin.attendance.update');
    Route::post('/admin/attendance/store', [AdminController::class, 'attendanceStore'])->name('admin.attendance.store');
    Route::get('/admin/requests/{id}', [AdminController::class, 'showApprovalPage'])->name('admin.attendance.request.approval');
    Route::get('/admin/break-requests/{id}', [AdminController::class, 'showBreakRequestDetail'])->name('admin.break.request.detail');
    Route::post('/admin/break-requests/{id}/approve', [AdminController::class, 'approveBreakRequest'])->name('admin.break.request.approve');
    Route::post('/admin/requests/{id}', [AdminController::class, 'approveRequest'])->name('admin.attendance.request.approve');
    Route::get('/admin/user/{userId}/attendances', [AdminController::class, 'userAttendanceList'])->name('admin.user.attendance.list');
    Route::get('/admin/user/{userId}/attendances/csv', [AdminController::class, 'userAttendanceCsv'])->name('admin.user.attendance.csv');
});

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [LoginController::class, 'showVerificationNotice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [LoginController::class, 'verify'])
        ->middleware(['signed'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [LoginController::class, 'resendVerificationEmail'])
        ->middleware(['throttle:6,1'])
        ->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [UserController::class, 'attendance'])->name('user.dashboard');
    Route::get('/attendance', [UserController::class, 'attendance'])->name('user.attendance');
    Route::post('/attendance/clock-in', [UserController::class, 'clockIn'])->name('user.clock-in');
    Route::post('/attendance/clock-out', [UserController::class, 'clockOut'])->name('user.clock-out');
    Route::post('/attendance/break-start', [UserController::class, 'breakStart'])->name('user.break-start');
    Route::post('/attendance/break-end', [UserController::class, 'breakEnd'])->name('user.break-end');
    Route::get('/attendance/list', [UserController::class, 'attendanceList'])->name('user.attendance.list');
    Route::get('/attendance/detail/{id}', [UserController::class, 'attendanceDetail'])->name('user.attendance.detail');
    Route::put('/attendance/update/{id}', [UserController::class, 'attendanceUpdate'])->name('user.attendance.update');
    Route::post('/attendance/store', [UserController::class, 'attendanceStore'])->name('user.attendance.store');
    Route::get('/attendance/requests', [UserController::class, 'attendanceRequests'])->name('user.attendance.requests');
    Route::get('/stamp_correction_request/list', [UserController::class, 'stampCorrectionRequests'])->name('user.stamp.correction.requests');
});
