<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** ======================= Auth Universal ======================= */
use App\Http\Controllers\UniversalAuthController;

/** ======================= Models (untuk binding di closure) ======================= */
use App\Models\Mahasiswa;
use App\Models\MahasiswaReguler;

/** ======================= Mahasiswa RPL ======================= */
use App\Http\Controllers\Mahasiswa\DashboardController as RplDashboardController;
use App\Http\Controllers\Mahasiswa\ProfilController     as RplProfilController;
use App\Http\Controllers\Mahasiswa\AngsuranController   as RplAngsuranController;
use App\Http\Controllers\Mahasiswa\InvoiceController    as RplInvoiceController;

/** ======================= Mahasiswa Reguler ======================= */
use App\Http\Controllers\MahasiswaReguler\DashboardRegulerController;
use App\Http\Controllers\MahasiswaReguler\ProfilRegulerController;
use App\Http\Controllers\MahasiswaReguler\AngsuranRegulerController;
use App\Http\Controllers\MahasiswaReguler\InvoiceRegulerController as RegInvoiceStudentController;

/** ======================= Admin ======================= */
use App\Http\Controllers\Admin\AuthController              as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController         as AdminDashboardController;
use App\Http\Controllers\Admin\InvoiceController           as AdminInvoiceController;
use App\Http\Controllers\Admin\InvoiceRegulerController    as AdminInvoiceRegulerController;
use App\Http\Controllers\Admin\MahasiswaController         as AdminMahasiswaController;
use App\Http\Controllers\Admin\MahasiswaRegulerController  as AdminMahasiswaRegulerController;
use App\Http\Controllers\Admin\KalenderEventController     as AdminKalenderEventController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\RegulerSettingController;
use App\Http\Controllers\Admin\KelulusanController;
use App\Http\Controllers\Admin\PerpanjanganController;
use App\Http\Controllers\Admin\ExportInvoiceController;

/*
|--------------------------------------------------------------------------
| Landing / Auth Mahasiswa (Universal)
|--------------------------------------------------------------------------
*/
Route::view('/',        'landing');
Route::view('/landing', 'landing')->name('landing');

Route::middleware('guest')->group(function () {
    Route::get ('/login', [UniversalAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [UniversalAuthController::class, 'login'])->name('login.submit');
});
Route::post('/logout', [UniversalAuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Lupa/Reset Password (DEV-friendly)
|--------------------------------------------------------------------------
*/

/* === RPL (Mahasiswa) === */
Route::prefix('mahasiswa')->name('mahasiswa.')->middleware('guest:mahasiswa')->group(function () {
    Route::get('password/forgot', fn () => view('auth.mahasiswa.passwords.email'))->name('password.request');

    Route::post('password/email', function (Request $r) {
        $r->validate(['email' => 'required|string']);
        $identity = trim($r->input('email'));
        if (Str::contains($identity, '@')) {
            $email = strtolower($identity);
        } else {
            $m = Mahasiswa::where('nim', $identity)->first();
            if (!$m || empty($m->email)) {
                return back()->withErrors(['email' => 'NIM tidak ditemukan atau email mahasiswa belum diisi.'])->withInput();
            }
            $email = strtolower($m->email);
        }
        $status = Password::broker('mahasiswa')->sendResetLink(['email' => $email]);
        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)])->withInput();
    })->middleware('throttle:5,1')->name('password.email');

    Route::get('password/reset/{token}', fn (Request $r, $token) =>
        view('auth.mahasiswa.passwords.reset', ['token' => $token, 'email' => $r->email])
    )->name('password.reset');

    Route::post('password/reset', function (Request $r) {
        $r->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);
        $status = Password::broker('mahasiswa')->reset(
            $r->only('email','password','password_confirmation','token'),
            function ($user) use ($r) { $user->password = Hash::make($r->password); $user->save(); }
        );
        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    })->name('password.update');
});

/* === Reguler === */
Route::prefix('reguler')->name('reguler.')->middleware('guest:mahasiswa_reguler')->group(function () {
    Route::get('password/forgot', fn () => view('auth.reguler.passwords.email'))->name('password.request');

    Route::post('password/email', function (Request $r) {
        $r->validate(['email' => 'required|string']);
        $identity = trim($r->input('email'));
        if (Str::contains($identity, '@')) {
            $email = strtolower($identity);
        } else {
            $m = MahasiswaReguler::where('nim', $identity)->first();
            if (!$m || empty($m->email)) {
                return back()->withErrors(['email' => 'NIM tidak ditemukan atau email mahasiswa belum diisi.'])->withInput();
            }
            $email = strtolower($m->email);
        }
        $status = Password::broker('mahasiswa_reguler')->sendResetLink(['email' => $email]);
        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)])->withInput();
    })->middleware('throttle:5,1')->name('password.email');

    Route::get('password/reset/{token}', fn (Request $r, $token) =>
        view('auth.reguler.passwords.reset', ['token' => $token, 'email' => $r->email])
    )->name('password.reset');

    Route::post('password/reset', function (Request $r) {
        $r->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);
        $status = Password::broker('mahasiswa_reguler')->reset(
            $r->only('email','password','password_confirmation','token'),
            function ($user) use ($r) { $user->password = Hash::make($r->password); $user->save(); }
        );
        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    })->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Mahasiswa RPL (canonical)
|--------------------------------------------------------------------------
*/
Route::prefix('mahasiswa')->name('mahasiswa.')->middleware('auth:mahasiswa')->group(function () {
    Route::get ('/dashboard', [RplDashboardController::class, 'index'])->name('dashboard');

    Route::get ('/profil', [RplProfilController::class, 'edit'])->name('profil.edit');
    Route::post('/profil', [RplProfilController::class, 'update'])->name('profil.update');

    Route::get ('/angsuran', [RplAngsuranController::class, 'create'])->name('angsuran.create');
    Route::post('/angsuran', [RplAngsuranController::class, 'store'])->name('angsuran.store');

    Route::get ('/invoices',                  [RplInvoiceController::class, 'index'])->name('invoices.index');
    Route::get ('/invoices/{invoice}',        [RplInvoiceController::class, 'show'])->whereNumber('invoice')->name('invoices.show');
    Route::post('/invoices/{invoice}/upload', [RplInvoiceController::class, 'upload'])->whereNumber('invoice')->name('invoices.upload');
    Route::post('/invoices/{invoice}/reset',  [RplInvoiceController::class, 'reset'])->whereNumber('invoice')->name('invoices.reset');

    Route::get ('/invoices/{invoice}/kwitansi', [RplInvoiceController::class, 'kwitansi'])
        ->whereNumber('invoice')->name('invoices.kwitansi');

    Route::get('/invoices/kwitansi/bulk', [RplInvoiceController::class, 'kwitansiBulk'])
        ->name('invoices.kwitansi.bulk');
});

/*
|--------------------------------------------------------------------------
| Mahasiswa RPL – ALIAS (kompat Blade lama)
|--------------------------------------------------------------------------
*/
Route::prefix('mahasiswa')->name('mahasiswa.')->middleware('auth:mahasiswa')->group(function () {
    Route::get('/invoice', fn () => redirect()->route('mahasiswa.invoices.index'))->name('invoice.index');
    Route::get('/invoice/setup', [RplAngsuranController::class, 'create'])->name('invoice.setup');
    Route::get('/invoice/{invoice}', [RplInvoiceController::class, 'show'])->whereNumber('invoice')->name('invoice.detail');

    Route::get ('/angsuran/form',  [RplAngsuranController::class, 'create'])->name('angsuran.form');
    Route::post('/angsuran/simpan',[RplAngsuranController::class, 'store'])->name('angsuran.simpan');

    Route::get('/invoice/kwitansi/bulk', fn () => redirect()->route('mahasiswa.invoices.kwitansi.bulk'))
        ->name('invoice.kwitansi.bulk');
});

/*
|--------------------------------------------------------------------------
| Mahasiswa Reguler (canonical)
|--------------------------------------------------------------------------
*/
Route::prefix('reguler')->name('reguler.')->middleware('auth:mahasiswa_reguler')->group(function () {
    Route::get ('/dashboard', [DashboardRegulerController::class, 'index'])->name('dashboard');

    Route::get ('/profil', [ProfilRegulerController::class, 'edit'])->name('profil.edit');
    Route::post('/profil', [ProfilRegulerController::class, 'update'])->name('profil.update');

    Route::get ('/angsuran', [AngsuranRegulerController::class, 'create'])->name('angsuran.create');
    Route::post('/angsuran', [AngsuranRegulerController::class, 'store'])->name('angsuran.store');
    Route::post('/angsuran/simpan', [AngsuranRegulerController::class, 'store'])->name('angsuran.simpan');

    Route::get('/invoice/setup', [RegInvoiceStudentController::class, 'setupAngsuran'])->name('invoice.setup');

    /** Index (standarisasi reguler.invoice.index) */
    Route::get('/invoices', [RegInvoiceStudentController::class, 'index'])->name('invoice.index');
    Route::get('/invoices/index', fn () => redirect()->route('reguler.invoice.index'))->name('invoices.index');
    Route::get('/invoice',       fn () => redirect()->route('reguler.invoice.index'))->name('invoice.index.alias');

    /** Detail & aksi */
    Route::get ('/invoices/{invoice}',        [RegInvoiceStudentController::class, 'detail'])->whereNumber('invoice')->name('invoices.show');
    Route::post('/invoices/{invoice}/upload', [RegInvoiceStudentController::class, 'upload'])->whereNumber('invoice')->name('invoices.upload');
    Route::post('/invoices/{invoice}/reset',  [RegInvoiceStudentController::class, 'reset'])->whereNumber('invoice')->name('invoices.reset');

    /** Kwitansi */
    Route::get ('/invoices/{invoice}/kwitansi',          [RegInvoiceStudentController::class, 'kwitansiForm'])->whereNumber('invoice')->name('invoice.kwitansi.form');
    Route::post('/invoices/{invoice}/kwitansi',          [RegInvoiceStudentController::class, 'kwitansiDownload'])->whereNumber('invoice')->name('invoice.kwitansi.download');
    Route::get ('/invoices/{invoice}/kwitansi/download', [RegInvoiceStudentController::class, 'kwitansiDownload'])->whereNumber('invoice')->name('invoice.kwitansi.direct');

    Route::get('/invoices/kwitansi/bulk', [RegInvoiceStudentController::class, 'kwitansiBulk'])->name('invoices.kwitansi.bulk');

    Route::view('/tutorial', 'mahasiswa_reguler.tutorial')->name('tutorial');
});

/*
|--------------------------------------------------------------------------
| Mahasiswa Reguler – ALIAS (kompat)
|--------------------------------------------------------------------------
*/
Route::prefix('mahasiswa_reguler')->name('mahasiswa_reguler.')->middleware('auth:mahasiswa_reguler')->group(function () {
    Route::get ('/dashboard', fn () => redirect()->route('reguler.dashboard'))->name('dashboard');
    Route::get ('/profil', [ProfilRegulerController::class, 'edit'])->name('edit.profil');
    Route::post('/profil', [ProfilRegulerController::class, 'update'])->name('update.profil');

    Route::get ('/angsuran', [AngsuranRegulerController::class, 'create'])->name('angsuran.form');
    Route::post('/angsuran', [AngsuranRegulerController::class, 'store'])->name('angsuran.simpan');

    Route::get ('/invoice', fn () => redirect()->route('reguler.invoice.index'))->name('invoice.index');
    Route::get ('/invoice/setup', [RegInvoiceStudentController::class, 'setupAngsuran'])->name('invoice.setup');

    Route::get ('/invoices/{invoice}',        [RegInvoiceStudentController::class, 'detail'])->whereNumber('invoice')->name('invoices.show');
    Route::post('/invoices/{invoice}/upload', [RegInvoiceStudentController::class, 'upload'])->whereNumber('invoice')->name('invoices.upload');
    Route::post('/invoices/{invoice}/reset',  [RegInvoiceStudentController::class, 'reset'])->whereNumber('invoice')->name('invoices.reset');

    Route::get ('/invoice/{invoice}', [RegInvoiceStudentController::class, 'detail'])->whereNumber('invoice')->name('invoice.detail');

    Route::get ('/invoices/{invoice}/kwitansi',          [RegInvoiceStudentController::class, 'kwitansiForm'])->whereNumber('invoice')->name('invoice.kwitansi.form');
    Route::post('/invoices/{invoice}/kwitansi',          [RegInvoiceStudentController::class, 'kwitansiDownload'])->whereNumber('invoice')->name('invoice.kwitansi.download');
    Route::get ('/invoices/{invoice}/kwitansi/download', [RegInvoiceStudentController::class, 'kwitansiDownload'])->whereNumber('invoice')->name('invoice.kwitansi.direct');

    Route::get('/invoice/kwitansi/bulk', fn () => redirect()->route('reguler.invoices.kwitansi.bulk'))->name('invoice.kwitansi.bulk');

    Route::get('/tutorial', fn () => redirect()->route('reguler.tutorial'))->name('tutorial');
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get ('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login.submit');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Settings
        Route::get ('/settings/total-tagihan', [SettingController::class, 'editTotalTagihan'])->name('settings.total-tagihan');
        Route::get ('/reguler-settings/edit',  [RegulerSettingController::class, 'edit'])->name('settings.reguler-settings.edit');
        Route::match(['POST','PATCH'], '/settings/total-tagihan', [SettingController::class, 'updateTotalTagihan'])->name('settings.tagihan.update');
        Route::match(['POST','PATCH'], '/reguler-settings/edit',  [RegulerSettingController::class, 'update'])->name('settings.reguler-settings.update');

        // Verifikasi RPL
        Route::get ('/invoices',                   [AdminInvoiceController::class, 'index'])->name('invoices.index');
        Route::post('/invoices',                   [AdminInvoiceController::class, 'store'])->name('invoices.store');
        Route::get ('/invoices/{invoice}/detail',  [AdminInvoiceController::class, 'show'])->whereNumber('invoice')->name('invoices.show');
        Route::get ('/invoices/{invoice}/bukti',   [AdminInvoiceController::class, 'bukti'])->whereNumber('invoice')->name('invoices.bukti');
        Route::post('/invoices/{invoice}/verify',  [AdminInvoiceController::class, 'verify'])->whereNumber('invoice')->name('invoices.verify');
        Route::post('/invoices/{invoice}/reject',  [AdminInvoiceController::class, 'reject'])->whereNumber('invoice')->name('invoices.reject');
        Route::post('/invoices/{invoice}/reset',   [AdminInvoiceController::class, 'reset'])->whereNumber('invoice')->name('invoices.reset');

        // Kwitansi RPL (Admin)
        Route::get ('/invoices/{invoice}/kwitansi', [AdminInvoiceController::class, 'kwitansiDownload'])->whereNumber('invoice')->name('invoices.kwitansi');

        // Verifikasi Reguler
        Route::get ('/invoices-reguler',                   [AdminInvoiceRegulerController::class, 'index'])->name('invoices-reguler.index');
        Route::post('/invoices-reguler',                   [AdminInvoiceRegulerController::class, 'store'])->name('invoices-reguler.store');
        Route::get ('/invoices-reguler/{invoice}/detail',  [AdminInvoiceRegulerController::class, 'show'])->whereNumber('invoice')->name('invoices-reguler.show');
        Route::get ('/invoices-reguler/{invoice}/bukti',   [AdminInvoiceRegulerController::class, 'bukti'])->whereNumber('invoice')->name('invoices-reguler.bukti');
        Route::post('/invoices-reguler/{invoice}/verify',  [AdminInvoiceRegulerController::class, 'verify'])->whereNumber('invoice')->name('invoices-reguler.verify');
        Route::post('/invoices-reguler/{invoice}/reject',  [AdminInvoiceRegulerController::class, 'reject'])->whereNumber('invoice')->name('invoices-reguler.reject');
        Route::post('/invoices-reguler/{invoice}/reset',   [AdminInvoiceRegulerController::class, 'reset'])->whereNumber('invoice')->name('invoices-reguler.reset');

        // Kwitansi Reguler (Admin)
        Route::get ('/invoices-reguler/{invoice}/kwitansi', [AdminInvoiceRegulerController::class, 'kwitansiDownload'])->whereNumber('invoice')->name('invoices-reguler.kwitansi');

        // Aliases camelCase
        Route::get ('/invoicesReguler',                   [AdminInvoiceRegulerController::class, 'index'])->name('invoicesReguler.index');
        Route::post('/invoicesReguler',                   [AdminInvoiceRegulerController::class, 'store'])->name('invoicesReguler.store');
        Route::get ('/invoicesReguler/{invoice}/detail',  [AdminInvoiceRegulerController::class, 'show'])->whereNumber('invoice')->name('invoicesReguler.show');
        Route::get ('/invoicesReguler/{invoice}/bukti',   [AdminInvoiceRegulerController::class, 'bukti'])->whereNumber('invoice')->name('invoicesReguler.bukti');
        Route::post('/invoicesReguler/{invoice}/verify',  [AdminInvoiceRegulerController::class, 'verify'])->whereNumber('invoice')->name('invoicesReguler.verify');
        Route::post('/invoicesReguler/{invoice}/reject',  [AdminInvoiceRegulerController::class, 'reject'])->whereNumber('invoice')->name('invoicesReguler.reject');
        Route::post('/invoicesReguler/{invoice}/reset',   [AdminInvoiceRegulerController::class, 'reset'])->whereNumber('invoice')->name('invoicesReguler.reset');

        // Mass update
        Route::patch('/mahasiswa/update-total-all',         [AdminMahasiswaController::class, 'updateTotalAll'])->name('mahasiswa.update-total-all');
        Route::patch('/mahasiswa-reguler/update-total-all', [AdminMahasiswaRegulerController::class, 'updateTotalAll'])->name('mahasiswa-reguler.update-total-all');

        // Master Mahasiswa RPL
        Route::match(['POST','DELETE'], '/mahasiswa/bulk-delete', [AdminMahasiswaController::class, 'bulkDelete'])->name('mahasiswa.bulkDelete');
        Route::get ('/mahasiswa',              [AdminMahasiswaController::class, 'index'])->name('mahasiswa.index');
        Route::get ('/mahasiswa/{mahasiswa}',  [AdminMahasiswaController::class, 'show'])->name('mahasiswa.show');
        Route::post('/mahasiswa/{mahasiswa}/reset-angsuran', [AdminMahasiswaController::class, 'resetAngsuran'])->name('mahasiswa.reset-angsuran');
        Route::get('/mahasiswa/{mahasiswa}/tagihan', function (Mahasiswa $mahasiswa) {
            return redirect()->route('admin.invoices.index', ['search' => $mahasiswa->nim, 'status' => 'semua']);
        })->name('mahasiswa.tagihan');
        Route::get('/mahasiswaRPL/{mahasiswa}/tagihan', function (Mahasiswa $mahasiswa) {
            return redirect()->route('admin.invoices.index', ['search' => $mahasiswa->nim, 'status' => 'semua']);
        })->name('mahasiswaRPL.tagihan');

        // Master Mahasiswa Reguler
        Route::match(['POST','DELETE'], '/mahasiswa-reguler/bulk-delete', [AdminMahasiswaRegulerController::class, 'bulkDelete'])->name('mahasiswa-reguler.bulkDelete');
        Route::match(['POST','DELETE'], '/mahasiswaReguler/bulk-delete',  [AdminMahasiswaRegulerController::class, 'bulkDelete'])->name('mahasiswaReguler.bulkDelete');
        Route::get ('/mahasiswa-reguler',             [AdminMahasiswaRegulerController::class, 'index'])->name('mahasiswa-reguler.index');
        Route::get ('/mahasiswa-reguler/{mahasiswa}', [AdminMahasiswaRegulerController::class, 'show'])->name('mahasiswa-reguler.show');
        Route::post('/mahasiswa-reguler/{mahasiswa}/reset-angsuran', [AdminMahasiswaRegulerController::class, 'resetAngsuran'])->name('mahasiswa-reguler.reset-angsuran');
        Route::get('/mahasiswa-reguler/{mahasiswa}/tagihan', function (MahasiswaReguler $mahasiswa) {
            return redirect()->route('admin.invoices-reguler.index', ['search' => $mahasiswa->nim, 'status' => 'semua']);
        })->name('mahasiswa-reguler.tagihan');
        Route::get('/mahasiswaReguler/{mahasiswa}/tagihan', function (MahasiswaReguler $mahasiswa) {
            return redirect()->route('admin.invoices-reguler.index', ['search' => $mahasiswa->nim, 'status' => 'semua']);
        })->name('mahasiswaReguler.tagihan');

        // Kalender
        Route::get   ('/kalender',            [AdminKalenderEventController::class, 'index'])->name('kalender.index');
        Route::post  ('/kalender',            [AdminKalenderEventController::class, 'store'])->name('kalender.store');
        Route::delete('/kalender/{kalender}', [AdminKalenderEventController::class, 'destroy'])->whereNumber('kalender')->name('kalender.destroy');
        Route::get('/kalender/create', fn () => redirect()->route('admin.kalender.index', ['create' => 1]))->name('kalender.create');
        Route::get('/kalender/{kalender}/edit', fn ($kalender) => redirect()->route('admin.kalender.index', ['edit' => $kalender]))->whereNumber('kalender')->name('kalender.edit');

        // Import CSV
        Route::post('/mahasiswa/import',         [AdminMahasiswaController::class, 'import'])->name('mahasiswa.import');
        Route::post('/mahasiswa-reguler/import', [AdminMahasiswaRegulerController::class, 'import'])->name('mahasiswa-reguler.import');
        Route::post('/mahasiswaReguler/import',  [AdminMahasiswaRegulerController::class, 'import'])->name('mahasiswaReguler.import');

        // Ubah total per ID
        Route::patch('/mahasiswa/{id}/update-total',         [AdminMahasiswaController::class, 'updateTotalTagihan'])->whereNumber('id')->name('mahasiswa.update-total-tagihan');
        Route::patch('/mahasiswa-reguler/{id}/update-total', [AdminMahasiswaRegulerController::class, 'updateTotalTagihan'])->whereNumber('id')->name('mahasiswa-reguler.update-total-tagihan');

        // Alias diminta
        Route::post('/invoices-reguler/{invoice}/tolak', [AdminInvoiceRegulerController::class, 'reject'])->whereNumber('invoice')->name('tolak');

        // Kelulusan
        Route::post('/kelulusan/rpl/{m}/lulus',     [KelulusanController::class, 'rplLulus'])->whereNumber('m')->name('kelulusan.rpl.lulus');
        Route::post('/kelulusan/rpl/{m}/tolak',     [KelulusanController::class, 'rplTolak'])->whereNumber('m')->name('kelulusan.rpl.tolak');
        Route::post('/kelulusan/reguler/{m}/lulus', [KelulusanController::class, 'regulerLulus'])->whereNumber('m')->name('kelulusan.reguler.lulus');
        Route::post('/kelulusan/reguler/{m}/tolak', [KelulusanController::class, 'regulerTolak'])->whereNumber('m')->name('kelulusan.reguler.tolak');

        // Perpanjangan
        Route::get ('/perpanjangan/reguler/{m}', [PerpanjanganController::class, 'regulerForm'])->whereNumber('m')->name('perpanjangan.reguler.form');
        Route::post('/perpanjangan/reguler/{m}', [PerpanjanganController::class, 'regulerAppend'])->whereNumber('m')->name('perpanjangan.reguler.append');
        Route::get ('/perpanjangan/rpl/{m}',     [PerpanjanganController::class, 'rplForm'])->whereNumber('m')->name('perpanjangan.rpl.form');
        Route::post('/perpanjangan/rpl/{m}',     [PerpanjanganController::class, 'rplAppend'])->whereNumber('m')->name('perpanjangan.rpl.append');

        // Verifikasi alias
        Route::get('/verifikasi',         fn () => redirect()->route('admin.invoices.index'))->name('verifikasi.rpl');
        Route::get('/verifikasi-reguler', fn () => redirect()->route('admin.invoices-reguler.index'))->name('verifikasi.reguler');

        // Export
        Route::get('/exports/invoices/rpl',     [ExportInvoiceController::class, 'exportRpl'])->name('exports.invoices.rpl');
        Route::get('/exports/invoices/reguler', [ExportInvoiceController::class, 'exportReguler'])->name('exports.invoices.reguler');
    });
});

/*
|--------------------------------------------------------------------------
| Web healthcheck
|--------------------------------------------------------------------------
*/
Route::get('/healthz', fn () => response()->json(['ok' => true]))->name('healthz');

/* Fallback */
Route::fallback(fn () => abort(404));
