<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\OdontogramController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------- public
Route::get('/', [LandingController::class, 'index'])->name('landing');

// ---------------------------------------------------------------- guests
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:15,1')->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.store');
    Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendReset'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('password.update');
    Route::post('/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1')->name('verification.resend');
});
Route::get('/verify-email/{token}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

// ---------------------------------------------------------------- signed in
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // booking JSON (used by public/js/booking.js)
    Route::get('/booking/slots', [BookingController::class, 'slots'])->name('booking.slots');
    Route::post('/booking/hold', [BookingController::class, 'hold'])->name('booking.hold');

    // ---- patient
    Route::middleware('role:patient')->group(function () {
        Route::get('/book', [BookingController::class, 'create'])->name('booking.create');
        Route::post('/book', [BookingController::class, 'store'])->name('booking.store');
        Route::get('/my-appointments', [AppointmentController::class, 'mine'])->name('appointments.mine');
    });

    // ---- clinic side
    Route::middleware('role:admin,clinic_staff,dentist')->group(function () {
        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm'])->name('appointments.confirm');
        Route::post('/appointments/{appointment}/reject', [AppointmentController::class, 'reject'])->name('appointments.reject');
        Route::post('/appointments/{appointment}/complete', [AppointmentController::class, 'complete'])->name('appointments.complete');
        Route::post('/appointments/{appointment}/no-show', [AppointmentController::class, 'noShow'])->name('appointments.noshow');
        Route::post('/appointments/{appointment}/follow-up', [AppointmentController::class, 'followUp'])->name('appointments.followup');
        Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    });
    Route::middleware('role:admin,clinic_staff')->group(function () {
        Route::get('/appointments/create', [BookingController::class, 'createForPatient'])->name('booking.staff');
        Route::post('/appointments', [BookingController::class, 'storeForPatient'])->name('booking.staff.store');
    });

    // ---- shared by patient (own) and clinic side
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::get('/appointments/{appointment}/reschedule', [AppointmentController::class, 'rescheduleForm'])->name('appointments.reschedule');
    Route::post('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule.store');

    // ---- dentist schedule + time off (dentist: own, admin: any)
    Route::middleware('role:admin,dentist')->group(function () {
        Route::get('/schedule/{dentist?}', [ScheduleController::class, 'show'])->name('schedule');
        Route::post('/schedule/{dentist}/hours', [ScheduleController::class, 'saveHours'])->name('schedule.hours');
        Route::post('/schedule/{dentist}/time-off', [ScheduleController::class, 'storeTimeOff'])->name('schedule.timeoff.store');
        Route::delete('/schedule/time-off/{timeOff}', [ScheduleController::class, 'destroyTimeOff'])->name('schedule.timeoff.destroy');

        // odontogram (dentists write, admin is read-only)
        Route::get('/odontogram', [OdontogramController::class, 'index'])->name('odontogram');
        Route::get('/odontogram/{patient}/findings', [OdontogramController::class, 'findings'])->name('odontogram.findings');
        Route::get('/odontogram/{patient}/history', [OdontogramController::class, 'history'])->name('odontogram.history');
        Route::post('/odontogram/{patient}/findings', [OdontogramController::class, 'store'])->name('odontogram.store');
        Route::put('/odontogram/findings/{finding}', [OdontogramController::class, 'update'])->name('odontogram.update');
        Route::delete('/odontogram/findings/{finding}', [OdontogramController::class, 'destroy'])->name('odontogram.destroy');
    });

    // ---- admin
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/settings', [SettingsController::class, 'show'])->name('admin.settings');
        Route::post('/settings/hours', [SettingsController::class, 'saveHours'])->name('admin.settings.hours');
        Route::post('/settings/rules', [SettingsController::class, 'saveRules'])->name('admin.settings.rules');
        Route::post('/settings/overrides', [SettingsController::class, 'storeOverride'])->name('admin.settings.overrides.store');
        Route::delete('/settings/overrides/{override}', [SettingsController::class, 'destroyOverride'])->name('admin.settings.overrides.destroy');
        Route::post('/settings/dentists', [SettingsController::class, 'saveDentists'])->name('admin.settings.dentists');
        Route::post('/settings/emergency', [SettingsController::class, 'storeEmergency'])->name('admin.settings.emergency.store');
        Route::delete('/settings/emergency/{block}', [SettingsController::class, 'destroyEmergency'])->name('admin.settings.emergency.destroy');
        Route::get('/logs', [LogController::class, 'index'])->name('admin.logs');
    });
});
