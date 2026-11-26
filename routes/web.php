<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\InstallmentReviewController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\OdontogramController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\RevenueDashboardController;
use App\Http\Controllers\DemographicsDashboardController;
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
        Route::get('/my-installments', [InstallmentController::class, 'mine'])->name('installments.mine');
        Route::post('/my-installment-payments/{row}/proof', [InstallmentController::class, 'pay'])->middleware('throttle:5,1')->name('installments.pay');
        Route::get('/my-installment-payments/{row}/proof', [InstallmentController::class, 'proof'])->name('installments.proof');
        Route::get('/my-profile', [PatientController::class, 'own'])->name('patients.own');
        Route::get('/my-profile/edit', [PatientController::class, 'editOwn'])->name('patients.own.edit');
        Route::put('/my-profile', [PatientController::class, 'updateOwn'])->name('patients.own.update');
        Route::get('/my-profile/attachments/{attachment}', [PatientController::class, 'download'])->name('patients.attachments.download');
        Route::get('/book', [BookingController::class, 'create'])->name('booking.create');
        Route::post('/book', [BookingController::class, 'store'])->name('booking.store');
        Route::get('/my-appointments', [AppointmentController::class, 'mine'])->name('appointments.mine');
    });

    Route::middleware('role:admin,clinic_staff,dentist')->get('/installments', [InstallmentReviewController::class, 'index'])->name('installments.index');
    Route::middleware('role:admin,clinic_staff,dentist')->prefix('installments')->name('installments.')->group(function () {
        Route::get('/new-for-patient', [InstallmentReviewController::class, 'createForPatient'])->name('create-for-patient');
        Route::post('/new-for-patient', [InstallmentReviewController::class, 'storeForPatient'])->name('store-for-patient');
    });
    Route::middleware('role:admin,clinic_staff')->get('/installment-proofs/{row}/file', [InstallmentReviewController::class, 'proof'])->name('installments.proof.view');
    Route::middleware('role:admin,clinic_staff')->group(function () {
        Route::post('/installment-proofs/{row}/verify', [InstallmentReviewController::class, 'verify'])->name('installments.verify');
        Route::post('/installment-proofs/{row}/reject', [InstallmentReviewController::class, 'rejectProof'])->name('installments.proof.reject');
    });

    // ---- clinic side
    Route::middleware('role:admin,clinic_staff')->group(function () {
        Route::get('/patients/create', [PatientController::class, 'create'])->name('patients.create');
        Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
    });
    Route::middleware('role:admin,clinic_staff,dentist')->group(function () {
        Route::get('/patients/{patient}/edit', [PatientController::class, 'edit'])->name('patients.edit');
        Route::put('/patients/{patient}', [PatientController::class, 'update'])->name('patients.update');
    });
    Route::middleware('role:admin,clinic_staff,dentist')->group(function () {
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
        Route::get('/patients/{patient}/attachments/{attachment}', [PatientController::class, 'downloadClinic'])->name('patients.attachments.download.clinic');
        Route::get('/patients/{patient}/attachments/{attachment}/view', [PatientController::class, 'viewAttachment'])->name('patients.attachments.view');
    });
    Route::middleware('role:admin,clinic_staff,dentist')->group(function () {
        Route::post('/patients/{patient}/attachments', [PatientController::class, 'upload'])->name('patients.attachments.store');
    });
    Route::middleware('role:clinic_staff,dentist')->prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::get('/create', [PaymentController::class, 'create'])->name('create');
        Route::post('/', [PaymentController::class, 'store'])->name('store');
        Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
        Route::post('/{payment}/settle', [PaymentController::class, 'settle'])->name('settle');
        Route::post('/{payment}/verify', [PaymentController::class, 'verify'])->name('verify');
        Route::post('/{payment}/reject', [PaymentController::class, 'reject'])->name('reject');
        Route::get('/{payment}/receipt', [PaymentController::class, 'receipt'])->name('receipt');
    });
    Route::middleware('role:admin,clinic_staff,dentist')->group(function () {
        Route::put('/patients/{patient}/health', [PatientController::class, 'health'])->name('patients.health');
    });
    Route::middleware('role:clinic_staff')->group(function () {
        Route::post('/patients/{patient}/consents', [PatientController::class, 'storeConsent'])->name('patients.consents.store');
    });
    Route::middleware('role:dentist')->group(function () {
        Route::post('/patients/{patient}/visits', [PatientController::class, 'visit'])->name('patients.visits.store');
        Route::put('/patients/{patient}/visits/{visitRecord}', [PatientController::class, 'updateVisit'])->name('patients.visits.update');
    });

    Route::middleware('role:admin,clinic_staff,dentist')->group(function () {
        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm'])->name('appointments.confirm');
        Route::post('/appointments/{appointment}/reject', [AppointmentController::class, 'reject'])->name('appointments.reject');
        Route::post('/appointments/{appointment}/complete', [AppointmentController::class, 'complete'])->name('appointments.complete');
        Route::post('/appointments/{appointment}/no-show', [AppointmentController::class, 'noShow'])->name('appointments.noshow');
        Route::post('/appointments/{appointment}/follow-up', [AppointmentController::class, 'followUp'])->name('appointments.followup');
        Route::get('/appointments/{appointment}/suggested-slots', [AppointmentController::class, 'suggestedSlots'])->name('appointments.suggested_slots');
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

    // ---- admin-managed dentist schedules and days off
    Route::middleware('role:admin')->group(function () {
        Route::get('/schedule/{dentist?}', [ScheduleController::class, 'show'])->name('schedule');
        Route::post('/schedule/{dentist}/hours', [ScheduleController::class, 'saveHours'])->name('schedule.hours');
        Route::post('/schedule/{dentist}/time-off', [ScheduleController::class, 'storeTimeOff'])->name('schedule.timeoff.store');
        Route::delete('/schedule/time-off/{timeOff}', [ScheduleController::class, 'destroyTimeOff'])->name('schedule.timeoff.destroy');
    });

    Route::middleware('role:admin,dentist')->group(function () {
        // odontogram (dentists and admins can record findings)
        Route::get('/odontogram', [OdontogramController::class, 'index'])->name('odontogram');
        Route::get('/odontogram/{patient}/findings', [OdontogramController::class, 'findings'])->name('odontogram.findings');
        Route::get('/odontogram/{patient}/history', [OdontogramController::class, 'history'])->name('odontogram.history');
        Route::post('/odontogram/{patient}/findings', [OdontogramController::class, 'store'])->name('odontogram.store');
        Route::put('/odontogram/findings/{finding}', [OdontogramController::class, 'update'])->name('odontogram.update');
        Route::delete('/odontogram/findings/{finding}', [OdontogramController::class, 'destroy'])->name('odontogram.destroy');
    });

    // ---- admin
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/revenue', [RevenueDashboardController::class, 'index'])->name('admin.revenue');
        Route::get('/revenue/data', [RevenueDashboardController::class, 'data'])->name('admin.revenue.data');
        Route::get('/revenue/export', [RevenueDashboardController::class, 'export'])->name('admin.revenue.export');
        Route::get('/demographics', [DemographicsDashboardController::class, 'index'])->name('admin.demographics');
        Route::get('/demographics/data', [DemographicsDashboardController::class, 'data'])->name('admin.demographics.data');
        Route::get('/users', [UserManagementController::class, 'index'])->name('admin.users');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('admin.users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('admin.users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('admin.users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('admin.users.update');
        Route::post('/users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('admin.users.deactivate');
        Route::post('/users/{user}/reactivate', [UserManagementController::class, 'reactivate'])->name('admin.users.reactivate');
        Route::post('/users/{user}/unlock', [UserManagementController::class, 'unlock'])->name('admin.users.unlock');
        Route::post('/users/{user}/reset', [UserManagementController::class, 'sendReset'])->name('admin.users.reset');
        Route::post('/users/{user}/verification', [UserManagementController::class, 'resendVerification'])->name('admin.users.verification');
        Route::get('/settings', [SettingsController::class, 'show'])->name('admin.settings');
        Route::post('/settings/hours', [SettingsController::class, 'saveHours'])->name('admin.settings.hours');
        Route::post('/settings/rules', [SettingsController::class, 'saveRules'])->name('admin.settings.rules');
        Route::post('/settings/gcash-qr', [SettingsController::class, 'saveGcashQr'])->name('admin.settings.gcash');
        Route::post('/settings/overrides/preview', [SettingsController::class, 'previewOverride'])->name('admin.settings.overrides.preview');
        Route::get('/settings/overrides/{override}/call-list', [SettingsController::class, 'callList'])->name('admin.settings.overrides.calllist');
        Route::post('/settings/overrides', [SettingsController::class, 'storeOverride'])->name('admin.settings.overrides.store');
        Route::delete('/settings/overrides/{override}', [SettingsController::class, 'destroyOverride'])->name('admin.settings.overrides.destroy');
        Route::post('/settings/dentists', [SettingsController::class, 'saveDentists'])->name('admin.settings.dentists');
        Route::post('/settings/emergency', [SettingsController::class, 'storeEmergency'])->name('admin.settings.emergency.store');
        Route::delete('/settings/emergency/{block}', [SettingsController::class, 'destroyEmergency'])->name('admin.settings.emergency.destroy');
        Route::get('/logs', [LogController::class, 'index'])->name('admin.logs');
    });
});
