<?php

use App\Http\Controllers\CashController;
use App\Http\Controllers\CashReportController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ErrorController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesNoteController;
use App\Http\Controllers\SalesCounterController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnpaidController;
use App\Http\Controllers\UserController;
use App\Models\ErrorLog;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Página principal
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => view('welcome'))->name('home');

/*
|--------------------------------------------------------------------------
| Rutas autenticadas
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    /*
    |----------------------------------------------------------------------
    | Usuarios - UserPolicy
    |----------------------------------------------------------------------
    */
    Route::prefix('users')->name('users.')->controller(UserController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:viewAny,'.User::class)->name('index');
        Route::get('create', 'create')->middleware('can:create,'.User::class)->name('create');
        Route::get('{user}', 'show')->withTrashed()->middleware('can:view,user')->name('show');
        // manage permite editar o acceder para restaurar.
        Route::get('{user}/edit', 'edit')->withTrashed()->middleware('can:manage,user')->name('edit');
    });

    /*
    |----------------------------------------------------------------------
    | Roles - RolePolicy
    |----------------------------------------------------------------------
    */
    Route::prefix('roles')->name('roles.')->controller(RoleController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:viewAny,'.Role::class)->name('index');
        Route::get('create', 'create')->middleware('can:create,'.Role::class)->name('create');
        // Las rutas de detalle y edición utilizan permisos existentes.
        Route::get('{role}/edit', 'edit')->middleware('can:roles.edit')->name('edit');
        Route::get('{role}', 'show')->middleware('can:roles.show')->name('show');
    });

    /*
    |----------------------------------------------------------------------
    | Permisos - PermissionPolicy
    |----------------------------------------------------------------------
    */
    Route::prefix('permissions')->name('permissions.')->controller(PermissionController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:viewAny,'.Permission::class)->name('index');
        Route::get('create', 'create')->middleware('can:create,'.Permission::class)->name('create');
        Route::get('{permission}/edit', 'edit')->middleware('can:permissions.edit')->name('edit');
        Route::get('{permission}', 'show')->middleware('can:permissions.show')->name('show');
    });

    /*
    |----------------------------------------------------------------------
    | Clientes
    |----------------------------------------------------------------------
    */
        Route::prefix('customers')->name('customers.')->controller(CustomerController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::get('{customer}', 'show')->name('show');            
        });

    /*
    |----------------------------------------------------------------------
    | Proveedores
    |----------------------------------------------------------------------
    */
        Route::prefix('suppliers')->name('suppliers.')->controller(SupplierController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::get('{supplier}', 'show')->name('show');         
        });
   


    /*
    |----------------------------------------------------------------------
    | Pagos
    |----------------------------------------------------------------------
    */
    Route::prefix('payments')->name('payments.')->controller(PaymentController::class)->group(function () {
        
        Route::get('customers', 'index')->name('customers.index');
        Route::get('suppliers', 'index')->name('suppliers.index');
        Route::get('customer/create', 'create')->name('customer.create');
        Route::get('supplier/create', 'create')->name('supplier.create');

     });

    /*
    |----------------------------------------------------------------------
    | Documentos
    |----------------------------------------------------------------------
    */
        Route::prefix('documents')->name('documents.')->controller(DocumentController::class)->group(function () {

            Route::get('customer', 'index')->name('customer.index');
            Route::get('customer/create', 'create')->name('customer.create');       
            Route::get('{document}', 'show')->name('show');
        });


    /*
    |----------------------------------------------------------------------
    | Documentos Tributarios Electronicos
    |----------------------------------------------------------------------
    */
        Route::prefix('dtes')->name('dtes.')->controller(DocumentController::class)->group(function () {

            Route::get('customer', 'index')->name('customers.index');
            Route::get('supplier', 'index')->name('suppliers.index');
            Route::get('customer/create', 'create')->name('customer.create');
            Route::get('supplier/create', 'create')->name('supplier.create');
            Route::get('customer/{document}', 'show')->name('customer.show');
            Route::get('supplier/{document}', 'show')->name('supplier.show');      
        
        });

    /*
    |----------------------------------------------------------------------
    | Notas de venta
    |----------------------------------------------------------------------
    */
        // Route::prefix('sales-notes')->name('sales-notes.')->controller(SalesNoteController::class)->group(function () {

        //     Route::get('/', 'index')->name('create');      
 
        // });

        /*
    |----------------------------------------------------------------------
    | Meson de Ventas
    |----------------------------------------------------------------------
    */
        Route::prefix('sales-counters')->name('sales-counters.')->controller(SalesCounterController::class)->group(function () {

            Route::get('/', 'show')->name('show');      
             
 
        });

    /*
    |----------------------------------------------------------------------
    | Caja
    |----------------------------------------------------------------------
    */
        Route::prefix('cashs')->name('cashs.')->controller(CashController::class)->group(function () {

            Route::get('/customer', 'index')->name('customer.index');    
        });

    /*
    |----------------------------------------------------------------------
    | Documentos Impagos
    |----------------------------------------------------------------------
    */
        Route::prefix('unpaids')->name('unpaids.')->controller(UnpaidController::class)->group(function () {
        Route::get('/', 'index')->name('index');       
        });

    /*
    |----------------------------------------------------------------------
    | Reportes de caja
    |----------------------------------------------------------------------
    */
        Route::prefix('CashReport')->name('cashReports.')->controller(CashReportController::class)->group(function () {
        Route::get('/', 'show')->name('show');
    });

    /*
    |----------------------------------------------------------------------
    | Documentos PDF
    |----------------------------------------------------------------------
    */
    Route::prefix('pdf')->name('pdf.')->controller(PdfController::class)->group(function () {
        Route::get('{type}/{record}', 'show')->whereNumber('record')->name('show');
        Route::get('{type}/{record}/download', 'download')->whereNumber('record')->name('download');
    });

    /*
    |----------------------------------------------------------------------
    | Registro de errores - ErrorLogPolicy
    |----------------------------------------------------------------------
    */
        Route::prefix('errors')->name('errors.')->controller(ErrorController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:viewAny,'.ErrorLog::class)->name('index');
        Route::get('{id}', 'show')->middleware('can:errors.show')->name('show');
    });

  
    
});

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';