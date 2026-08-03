<?php

use App\Http\Controllers\Api\V1\AbdmController;
use App\Http\Controllers\Api\V1\AdmissionController;
use App\Http\Controllers\Api\V1\Admin\BranchController;
use App\Http\Controllers\Api\V1\Admin\DepartmentController;
use App\Http\Controllers\Api\V1\Admin\DoctorOpsController;
use App\Http\Controllers\Api\V1\Admin\HospitalController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\OtpLoginController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\ConsultationController;
use App\Http\Controllers\Api\V1\ContextController;
use App\Http\Controllers\Api\V1\DiagnosticOrderController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OpdQueueController;
use App\Http\Controllers\Api\V1\PatientClinicalHistoryController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\PatientDocumentController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ServiceCatalogController;
use App\Http\Middleware\ResolveTenantContext;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return ApiResponse::success(
            request: request(),
            data: [
                'service' => 'hims-backend',
                'status' => 'ok',
                'version' => config('app.version', '0.1.0'),
            ],
            message: 'HIMS API is healthy',
        );
    })->name('api.v1.health');

    Route::prefix('auth')->name('api.v1.auth.')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('login');

        Route::post('/otp/request', [OtpLoginController::class, 'requestOtp'])
            ->middleware('throttle:5,1')
            ->name('otp.request');

        Route::post('/otp/verify', [OtpLoginController::class, 'verify'])
            ->middleware('throttle:5,1')
            ->name('otp.verify');

        Route::post('/password/forgot', [PasswordResetController::class, 'forgot'])
            ->middleware('throttle:5,1')
            ->name('password.forgot');

        Route::post('/password/reset', [PasswordResetController::class, 'reset'])
            ->middleware('throttle:5,1')
            ->name('password.reset');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        });
    });

    Route::middleware(['auth:sanctum', ResolveTenantContext::class])->group(function (): void {
        Route::get('/context', [ContextController::class, 'show'])
            ->middleware('can:context.view')
            ->name('api.v1.context.show');

        Route::prefix('admin')->name('api.v1.admin.')->group(function (): void {
            Route::get('/hospitals', [HospitalController::class, 'index'])
                ->middleware('can:admin.hospitals.view')
                ->name('hospitals.index');
            Route::put('/hospitals/{hospital}', [HospitalController::class, 'update'])
                ->middleware('can:admin.hospitals.manage')
                ->name('hospitals.update');

            Route::get('/branches', [BranchController::class, 'index'])
                ->middleware('can:admin.branches.view')
                ->name('branches.index');
            Route::post('/branches', [BranchController::class, 'store'])
                ->middleware('can:admin.branches.manage')
                ->name('branches.store');
            Route::put('/branches/{branch}', [BranchController::class, 'update'])
                ->middleware('can:admin.branches.manage')
                ->name('branches.update');

            Route::get('/departments', [DepartmentController::class, 'index'])
                ->middleware('can:admin.departments.view')
                ->name('departments.index');
            Route::post('/departments', [DepartmentController::class, 'store'])
                ->middleware('can:admin.departments.manage')
                ->name('departments.store');
            Route::put('/departments/{department}', [DepartmentController::class, 'update'])
                ->middleware('can:admin.departments.manage')
                ->name('departments.update');

            Route::get('/users', [UserController::class, 'index'])
                ->middleware('can:admin.users.manage')
                ->name('users.index');
            Route::post('/users', [UserController::class, 'store'])
                ->middleware('can:admin.users.manage')
                ->name('users.store');
            Route::put('/users/{user}', [UserController::class, 'update'])
                ->middleware('can:admin.users.manage')
                ->name('users.update');

            Route::get('/roles', [RoleController::class, 'index'])
                ->middleware('can:admin.roles.view')
                ->name('roles.index');
            Route::post('/roles', [RoleController::class, 'store'])
                ->middleware('can:admin.roles.manage')
                ->name('roles.store');
            Route::put('/roles/{role}', [RoleController::class, 'update'])
                ->middleware('can:admin.roles.manage')
                ->name('roles.update');

            Route::get('/permissions', [RoleController::class, 'permissions'])
                ->middleware('can:admin.roles.view')
                ->name('permissions.index');

            Route::prefix('doctors/{doctor}')->name('doctors.')->middleware('can:admin.users.manage')->group(function (): void {
                Route::get('/schedules', [DoctorOpsController::class, 'schedules'])->name('schedules.index');
                Route::post('/schedules', [DoctorOpsController::class, 'storeSchedule'])->name('schedules.store');
                Route::put('/schedules/{schedule}', [DoctorOpsController::class, 'updateSchedule'])->name('schedules.update');
                Route::get('/leaves', [DoctorOpsController::class, 'leaves'])->name('leaves.index');
                Route::post('/leaves', [DoctorOpsController::class, 'storeLeave'])->name('leaves.store');
                Route::put('/leaves/{leave}', [DoctorOpsController::class, 'updateLeave'])->name('leaves.update');
                Route::get('/fees', [DoctorOpsController::class, 'fees'])->name('fees.index');
                Route::put('/fees', [DoctorOpsController::class, 'upsertFee'])->name('fees.upsert');
            });
        });

        Route::get('/patients/{patient}/clinical-history', [PatientClinicalHistoryController::class, 'index'])
            ->name('api.v1.patients.clinical-history');

        Route::prefix('patients')->name('api.v1.patients.')->middleware('can:patients.manage')->group(function (): void {
            Route::get('/', [PatientController::class, 'index'])->name('index');
            Route::get('/duplicates', [PatientController::class, 'duplicates'])->name('duplicates');
            Route::post('/', [PatientController::class, 'store'])->name('store');
            Route::get('/{patient}', [PatientController::class, 'show'])->name('show');
            Route::put('/{patient}', [PatientController::class, 'update'])->name('update');
            Route::get('/{patient}/documents', [PatientDocumentController::class, 'index'])->name('documents.index');
            Route::post('/{patient}/documents', [PatientDocumentController::class, 'store'])->name('documents.store');
        });

        Route::prefix('appointments')->name('api.v1.appointments.')->middleware('can:appointments.manage')->group(function (): void {
            Route::get('/', [AppointmentController::class, 'index'])->name('index');
            Route::get('/options', [AppointmentController::class, 'options'])->name('options');
            Route::get('/slots', [AppointmentController::class, 'slots'])->name('slots');
            Route::post('/', [AppointmentController::class, 'store'])->name('store');
            Route::get('/{appointment}', [AppointmentController::class, 'show'])->name('show');
            Route::put('/{appointment}', [AppointmentController::class, 'update'])->name('update');
            Route::post('/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('cancel');
            Route::post('/{appointment}/check-in', [AppointmentController::class, 'checkIn'])->name('check-in');
        });

        Route::prefix('opd/queue')->name('api.v1.opd.queue.')->group(function (): void {
            Route::get('/', [OpdQueueController::class, 'index'])->name('index');
            Route::get('/{opdQueue}/vitals', [OpdQueueController::class, 'showVitals'])->name('vitals.show');
            Route::put('/{opdQueue}/vitals', [OpdQueueController::class, 'updateVitals'])->name('vitals.update');
            Route::post('/{opdQueue}/call', [OpdQueueController::class, 'call'])->name('call');
            Route::post('/{opdQueue}/start', [OpdQueueController::class, 'start'])->name('start');
            Route::post('/{opdQueue}/complete', [OpdQueueController::class, 'complete'])->name('complete');
            Route::post('/{opdQueue}/skip', [OpdQueueController::class, 'skip'])->name('skip');
            Route::post('/{opdQueue}/requeue', [OpdQueueController::class, 'requeue'])->name('requeue');
        });

        Route::prefix('opd/consultations')->name('api.v1.opd.consultations.')->middleware('can:opd.consult')->group(function (): void {
            Route::get('/', [ConsultationController::class, 'index'])->name('index');
            Route::post('/', [ConsultationController::class, 'store'])->name('store');
            Route::get('/{encounter}', [ConsultationController::class, 'show'])->name('show');
            Route::put('/{encounter}', [ConsultationController::class, 'update'])->name('update');
            Route::post('/{encounter}/complete', [ConsultationController::class, 'complete'])->name('complete');
        });

        Route::prefix('billing')->name('api.v1.billing.')->middleware('can:billing.manage')->group(function (): void {
            Route::get('/services', [ServiceCatalogController::class, 'index'])->name('services.index');
            Route::post('/services', [ServiceCatalogController::class, 'store'])->name('services.store');
            Route::put('/services/{service}', [ServiceCatalogController::class, 'update'])->name('services.update');

            Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
            Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
            Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
            Route::post('/invoices/{invoice}/finalize', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
            Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'voidInvoice'])->name('invoices.void');

            Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
            Route::get('/invoices/{invoice}/receipt', [PaymentController::class, 'receipt'])->name('invoices.receipt');
        });

        Route::prefix('diagnostics/orders')->name('api.v1.diagnostics.orders.')->group(function (): void {
            Route::get('/', [DiagnosticOrderController::class, 'index'])->name('index');
            Route::get('/catalog', [DiagnosticOrderController::class, 'catalog'])->name('catalog');
            Route::post('/', [DiagnosticOrderController::class, 'store'])->name('store');
            Route::get('/{diagnosticOrder}', [DiagnosticOrderController::class, 'show'])->name('show');
            Route::post('/{diagnosticOrder}/cancel', [DiagnosticOrderController::class, 'cancel'])->name('cancel');
            Route::post('/{diagnosticOrder}/collect', [DiagnosticOrderController::class, 'collect'])->name('collect');
            Route::post('/{diagnosticOrder}/result', [DiagnosticOrderController::class, 'result'])->name('result');
            Route::post('/{diagnosticOrder}/bill', [DiagnosticOrderController::class, 'bill'])->name('bill');
        });

        Route::prefix('ipd')->name('api.v1.ipd.')->middleware('can:ipd.manage')->group(function (): void {
            Route::get('/wards', [AdmissionController::class, 'wards'])->name('wards.index');
            Route::get('/beds/board', [AdmissionController::class, 'board'])->name('beds.board');
            Route::get('/admissions', [AdmissionController::class, 'index'])->name('admissions.index');
            Route::post('/admissions', [AdmissionController::class, 'store'])->name('admissions.store');
            Route::get('/admissions/{admission}', [AdmissionController::class, 'show'])->name('admissions.show');
            Route::post('/admissions/{admission}/transfer', [AdmissionController::class, 'transfer'])->name('admissions.transfer');
            Route::post('/admissions/{admission}/discharge', [AdmissionController::class, 'discharge'])->name('admissions.discharge');
            Route::get('/admissions/{admission}/nursing-notes', [AdmissionController::class, 'nursingNotes'])->name('admissions.nursing-notes.index');
            Route::post('/admissions/{admission}/nursing-notes', [AdmissionController::class, 'storeNursingNote'])->name('admissions.nursing-notes.store');
            Route::get('/admissions/{admission}/charges', [AdmissionController::class, 'charges'])->name('admissions.charges.index');
            Route::post('/admissions/{admission}/charges/daily', [AdmissionController::class, 'postDailyCharges'])->name('admissions.charges.daily');
            Route::get('/admissions/{admission}/clearances', [AdmissionController::class, 'clearances'])->name('admissions.clearances.index');
            Route::post('/admissions/{admission}/clearances', [AdmissionController::class, 'updateClearance'])->name('admissions.clearances.update');
        });

        Route::prefix('abdm')->name('api.v1.abdm.')->middleware('can:abdm.manage')->group(function (): void {
            Route::get('/status', [AbdmController::class, 'status'])->name('status');
            Route::post('/abha/verify/init', [AbdmController::class, 'initiateVerify'])->name('abha.verify.init');
            Route::post('/abha/verify/confirm', [AbdmController::class, 'confirmVerify'])->name('abha.verify.confirm');
            Route::post('/abha/create/init', [AbdmController::class, 'initiateCreate'])->name('abha.create.init');
            Route::post('/abha/create/confirm', [AbdmController::class, 'confirmCreate'])->name('abha.create.confirm');
            Route::post('/scan-share', [AbdmController::class, 'scanShare'])->name('scan-share');
            Route::post('/patients/{patient}/link', [AbdmController::class, 'linkPatient'])->name('patients.link');
        });
        Route::get('/notifications', [NotificationController::class, 'index'])
            ->middleware('can:context.view')
            ->name('api.v1.notifications.index');
    });
});