<?php

use App\Http\Controllers\Sede\AccesoController;
use App\Http\Controllers\Sede\CarpetaController;
use App\Http\Controllers\Sede\DocumentoController;
use App\Http\Controllers\Sede\ExpedienteController;
use App\Http\Controllers\Sede\NotificacionController;
use App\Http\Controllers\Sede\PagoController;
use App\Http\Controllers\Sede\PaginaController;
use App\Http\Controllers\Sede\RepresentacionController;
use App\Http\Controllers\Sede\SolicitudController;
use App\Http\Controllers\Sede\VerificacionController;
use Illuminate\Support\Facades\Route;

Route::name('sede.')->group(function () {
    // Páginas públicas
    Route::get('/', [PaginaController::class, 'inicio'])->name('inicio');
    Route::get('/tramites/{procedimiento:codigo}', [PaginaController::class, 'tramite'])->name('tramite');
    Route::get('/accesibilidad', [PaginaController::class, 'accesibilidad'])->name('accesibilidad');
    Route::get('/aviso-legal', [PaginaController::class, 'avisoLegal'])->name('aviso-legal');
    Route::get('/privacidad', [PaginaController::class, 'privacidad'])->name('privacidad');
    Route::get('/cookies', [PaginaController::class, 'cookies'])->name('cookies');
    Route::get('/verificar', [VerificacionController::class, 'formulario'])->name('verificar');
    Route::post('/verificar', [VerificacionController::class, 'comprobar'])->middleware('throttle:20,1')->name('verificar.comprobar');
    Route::get('/verificar/{csv}/descargar', [VerificacionController::class, 'descargar'])->middleware('throttle:20,1')->name('verificar.descargar');

    // Acceso con certificado electrónico
    Route::get('/acceso', [AccesoController::class, 'mostrar'])->name('acceso');
    Route::post('/acceso/reto', [AccesoController::class, 'reto'])->middleware('throttle:30,1')->name('acceso.reto');
    Route::post('/acceso', [AccesoController::class, 'acceder'])->middleware('throttle:15,1')->name('acceso.acceder');
    Route::post('/acceso/simulado', [AccesoController::class, 'simulado'])->name('acceso.simulado');
    Route::post('/salir', [AccesoController::class, 'salir'])->name('salir');

    // Pasarela de pago (servidor a servidor, sin sesión)
    Route::post('/pagos/notificacion', [PagoController::class, 'notificacion'])->name('pagos.notificacion');

    Route::middleware('auth:sede')->group(function () {
        Route::get('/primer-acceso', [CarpetaController::class, 'perfil'])->name('perfil');
        Route::post('/primer-acceso', [CarpetaController::class, 'guardarPerfil'])->name('perfil.guardar');

        Route::middleware('sede.perfil')->group(function () {
            Route::get('/carpeta', [CarpetaController::class, 'inicio'])->name('carpeta');
            Route::get('/carpeta/expedientes', [CarpetaController::class, 'expedientes'])->name('expedientes');
            Route::get('/carpeta/documentos', [CarpetaController::class, 'documentos'])->name('documentos');
            Route::get('/carpeta/datos', [CarpetaController::class, 'perfil'])->name('datos');

            Route::get('/carpeta/expedientes/{expediente}', [ExpedienteController::class, 'ver'])->name('expedientes.ver');
            Route::get('/carpeta/expedientes/{expediente}/subsanar', [ExpedienteController::class, 'subsanar'])->name('expedientes.subsanar');
            Route::post('/carpeta/expedientes/{expediente}/subsanar', [ExpedienteController::class, 'enviarSubsanacion'])->name('expedientes.subsanar.enviar');
            Route::get('/carpeta/expedientes/{expediente}/retirar', [ExpedienteController::class, 'confirmarRetirada'])->name('expedientes.retirar');
            Route::post('/carpeta/expedientes/{expediente}/retirar', [ExpedienteController::class, 'retirar'])->name('expedientes.retirar.enviar');

            Route::post('/tramites/{procedimiento:codigo}/solicitar', [SolicitudController::class, 'crear'])->name('solicitudes.crear');
            Route::get('/solicitudes/{expediente}', [SolicitudController::class, 'editar'])->name('solicitudes.editar');
            Route::post('/solicitudes/{expediente}/borrador', [SolicitudController::class, 'guardar'])->name('solicitudes.guardar');
            Route::post('/solicitudes/{expediente}/documentos', [SolicitudController::class, 'subirDocumento'])->name('solicitudes.documentos');
            Route::delete('/solicitudes/{expediente}/documentos/{documento}', [SolicitudController::class, 'quitarDocumento'])->name('solicitudes.documentos.quitar');
            Route::get('/solicitudes/{expediente}/resumen', [SolicitudController::class, 'resumen'])->name('solicitudes.resumen');
            Route::post('/solicitudes/{expediente}/firmar', [SolicitudController::class, 'prepararFirma'])->name('solicitudes.preparar');
            Route::post('/solicitudes/{expediente}/presentar', [SolicitudController::class, 'presentar'])->name('solicitudes.presentar');
            Route::delete('/solicitudes/{expediente}', [SolicitudController::class, 'descartar'])->name('solicitudes.descartar');

            Route::get('/carpeta/notificaciones', [NotificacionController::class, 'lista'])->name('notificaciones');
            Route::get('/carpeta/notificaciones/{notificacion}', [NotificacionController::class, 'ver'])->name('notificaciones.ver');
            Route::post('/carpeta/notificaciones/{notificacion}/acceder', [NotificacionController::class, 'acceder'])->name('notificaciones.acceder');

            Route::get('/carpeta/autorizaciones', [RepresentacionController::class, 'lista'])->name('autorizaciones');
            Route::post('/carpeta/autorizaciones', [RepresentacionController::class, 'solicitar'])->name('autorizaciones.solicitar');

            Route::get('/documentos/{documento}', [DocumentoController::class, 'descargar'])->name('documentos.descargar');

            Route::post('/carpeta/expedientes/{expediente}/pagar', [PagoController::class, 'iniciar'])->name('pagos.iniciar');
            Route::get('/pagos/{pago}/vuelta', [PagoController::class, 'vuelta'])->name('pagos.vuelta');
        });
    });
});
