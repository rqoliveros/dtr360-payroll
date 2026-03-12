<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FirebaseController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\AttendanceController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', fn() => redirect('/login'));
Route::get('/login', [AuthController::class, 'loginPage'])->name('login');
Route::post('/firebase-login', [AuthController::class, 'firebaseLogin']);
Route::get('/logout', function() {
    Session::forget('firebase_user');
    return redirect('/login');
});
Route::get('/dashboard', function () {
    if (!Session::has('firebase_user')) {
        return redirect('/login');
    }
    return view('home.dashboard');
});
Route::middleware(['firebase.auth'])->group(function () {
    Route::get('/firebase-users', [FirebaseController::class, 'getEmployees']);
    Route::get('/firebase-attendance', [FirebaseController::class, 'getAttendance']);
    Route::get('/firebase-holidays/{start}/{end}', [FirebaseController::class, 'getHolidays']);
    Route::get('/firebase-docs', [FirebaseController::class, 'getFiledDocuments']);
    Route::get('/attendance/{dept}/{start}/{end}', [FirebaseController::class, 'getAttendanceByDateRange']);
    Route::get('/documents/{dept}/{start}/{end}', [FirebaseController::class, 'getFiledDocumentsByDateRange']);
});

Route::get('/firebase-test', function(\Kreait\Firebase\Contract\Auth $auth){

    $users = $auth->listUsers();

    return 'Firebase connected';

});

// Payroll route group
Route::prefix('payroll')
    ->middleware(['firebase.auth'])  // protects payroll routes
    ->name('payroll.')              // route names will start with payroll.
    ->group(function () {

        // Payroll Dashboard
        Route::get('/dashboard', [PayrollController::class, 'dashboard'])
            ->name('dashboard');

        // Payroll Approval
        Route::get('/approval', [PayrollController::class, 'approval'])
            ->name('approval');

        // Approval Fetch
        Route::get('/approval/{department}/{startDate}/{endDate}', 
            [FirebaseController::class, 'getDocumentsByDepartment'])
            ->name('approval.documents');

        // Approve Documents
        Route::post('/approve-documents', 
            [PayrollController::class, 'approveDocuments'])
            ->name('approval.documents');

        // Approve Documents
        Route::post('/reject-document', 
            [PayrollController::class, 'rejectDocument'])
            ->name('reject.document');

        // Approve Documents
        Route::post('/approve-document', 
            [PayrollController::class, 'approveDocument'])
            ->name('approval.document');

        // Attendance pages
        Route::get('/attendance/{department}/{startDate}/{endDate}', 
            [FirebaseController::class, 'getAttendanceByDateRange'])
            ->name('attendance');

        // Payroll reports
        Route::get('/reports', [PayrollController::class, 'reports'])
            ->name('reports');

        // Payroll settings
        Route::get('/settings', [PayrollController::class, 'settings'])
            ->name('settings');
});