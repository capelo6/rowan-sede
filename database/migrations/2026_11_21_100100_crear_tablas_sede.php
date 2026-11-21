<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Personas interesadas (ciudadanía y entidades representadas).
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_persona', 10)->default('fisica'); // fisica | juridica
            $table->string('nif', 20)->unique();
            $table->string('nombre')->nullable();
            $table->string('apellido1')->nullable();
            $table->string('apellido2')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('direccion')->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('municipio')->nullable();
            $table->string('provincia')->nullable();
            $table->timestamp('privacidad_aceptada_at')->nullable();
            $table->timestamp('consentimiento_notificaciones_at')->nullable();
            $table->timestamp('ultimo_acceso_at')->nullable();
            $table->json('ultimo_certificado')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('procedimientos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre');
            $table->string('resumen', 500)->nullable();
            $table->text('informacion')->nullable();
            $table->json('definicion'); // campos y documentos requeridos
            $table->boolean('permite_representacion')->default(true);
            $table->boolean('requiere_tasa')->default(false);
            $table->unsignedSmallInteger('plazo_resolucion_dias')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('representaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('representante_id')->constrained('personas');
            $table->foreignId('representado_id')->constrained('personas');
            $table->string('ambito', 20)->default('todos'); // todos | procedimientos
            $table->json('procedimientos')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente | validada | rechazada | revocada
            $table->foreignId('validada_por')->nullable()->constrained('users');
            $table->timestamp('validada_at')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->index(['representante_id', 'estado']);
        });

        Schema::create('expedientes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->nullable()->unique();
            $table->foreignId('procedimiento_id')->constrained('procedimientos');
            $table->foreignId('titular_id')->constrained('personas');
            $table->foreignId('presentador_id')->constrained('personas');
            $table->foreignId('representacion_id')->nullable()->constrained('representaciones');
            $table->string('estado', 30)->default('borrador')->index();
            $table->string('resultado', 20)->nullable(); // aprobada | denegada | retirada | anulada
            $table->json('datos')->nullable();
            $table->foreignId('asignado_a')->nullable()->constrained('users');
            $table->timestamp('presentado_at')->nullable();
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamp('estado_cambiado_at')->nullable();
            $table->timestamp('desistimiento_propuesto_at')->nullable();
            $table->timestamps();
            $table->index(['titular_id', 'estado']);
        });

        Schema::create('expediente_estados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30);
            $table->text('motivo')->nullable();
            $table->string('actor_tipo', 20); // persona | usuario | sistema
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->nullable()->constrained('expedientes');
            $table->string('categoria', 30); // aportado | solicitud | firma | justificante_registro | requerimiento | resolucion | notificacion | justificante_pago | representacion | autorizacion | evidencia | otro
            $table->string('clave_requisito', 60)->nullable();
            $table->string('nombre');
            $table->string('nombre_original')->nullable();
            $table->string('disco', 30)->default('local');
            $table->string('ruta');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('tamano')->default(0);
            $table->char('sha256', 64);
            $table->string('csv', 40)->nullable()->unique();
            $table->string('origen', 20)->default('interesado'); // interesado | organismo
            $table->foreignId('persona_id')->nullable()->constrained('personas');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->boolean('firmado')->default(false);
            $table->json('metadatos')->nullable();
            $table->timestamps();
            $table->index(['expediente_id', 'categoria']);
        });

        Schema::create('registros', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 10); // entrada | salida
            $table->unsignedSmallInteger('ejercicio');
            $table->unsignedInteger('numero');
            $table->timestamp('registrado_at');
            $table->foreignId('expediente_id')->nullable()->constrained('expedientes');
            $table->foreignId('persona_id')->nullable()->constrained('personas');
            $table->string('asunto');
            $table->text('resumen')->nullable();
            $table->foreignId('justificante_id')->nullable()->constrained('documentos');
            $table->timestamps();
            $table->unique(['tipo', 'ejercicio', 'numero']);
        });

        Schema::create('requerimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->text('motivo')->nullable();
            $table->json('items'); // [{clave, tipo: documento|dato, descripcion}]
            $table->unsignedSmallInteger('plazo_dias')->default(10);
            $table->boolean('plazo_habiles')->default(true);
            $table->timestamp('notificado_at')->nullable();
            $table->date('vence_at')->nullable();
            $table->timestamp('atendido_at')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente | atendido | vencido | anulado
            $table->timestamps();
        });

        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->nullable()->constrained('expedientes');
            $table->foreignId('persona_id')->constrained('personas');
            $table->string('asunto');
            $table->text('cuerpo')->nullable();
            $table->foreignId('documento_id')->nullable()->constrained('documentos');
            $table->timestamp('puesta_disposicion_at');
            $table->timestamp('accedida_at')->nullable();
            $table->timestamp('rechazada_at')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente | accedida | rechazada
            $table->foreignId('justificante_id')->nullable()->constrained('documentos');
            $table->timestamps();
            $table->index(['persona_id', 'estado']);
        });

        Schema::create('tasas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40);
            $table->unsignedSmallInteger('ejercicio');
            $table->string('concepto');
            $table->decimal('importe', 10, 2);
            $table->decimal('iva_porcentaje', 5, 2)->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->unique(['codigo', 'ejercicio']);
        });

        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes');
            $table->string('referencia', 30)->unique();
            $table->string('estado', 20)->default('pendiente'); // pendiente | pagada | anulada
            $table->decimal('base', 10, 2)->default(0);
            $table->decimal('iva', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->date('vence_at')->nullable();
            $table->foreignId('emitida_por')->nullable()->constrained('users');
            $table->timestamp('pagada_at')->nullable();
            $table->timestamps();
        });

        Schema::create('liquidacion_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();
            $table->foreignId('tasa_id')->nullable()->constrained('tasas');
            $table->string('concepto');
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->decimal('importe_unitario', 10, 2);
            $table->decimal('iva_porcentaje', 5, 2)->default(0);
            $table->string('motivo')->nullable();
            $table->decimal('base', 10, 2);
            $table->decimal('iva', 10, 2);
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });

        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones');
            $table->string('canal', 20); // tarjeta | bizum | ventanilla | transferencia
            $table->string('pedido', 12)->nullable()->unique();
            $table->decimal('importe', 10, 2);
            $table->string('estado', 20)->default('iniciado'); // iniciado | autorizado | denegado | anulado
            $table->string('codigo_respuesta', 10)->nullable();
            $table->string('autorizacion', 20)->nullable();
            $table->json('datos_pasarela')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users');
            $table->timestamp('pagado_at')->nullable();
            $table->foreignId('justificante_id')->nullable()->constrained('documentos');
            $table->timestamps();
        });

        Schema::create('festivos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('descripcion');
            $table->string('ambito', 20)->default('nacional'); // nacional | autonomico | local
            $table->timestamps();
            $table->unique(['fecha', 'ambito']);
        });

        Schema::create('plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nombre');
            $table->string('tipo', 30); // resolucion | denegacion | requerimiento | notificacion | otro
            $table->string('asunto')->nullable();
            $table->longText('cuerpo');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 120)->unique(); // evita duplicados: p. ej. req:12:mitad
            $table->foreignId('persona_id')->constrained('personas');
            $table->foreignId('expediente_id')->nullable()->constrained('expedientes');
            $table->string('tipo', 40);
            $table->string('asunto');
            $table->timestamp('enviado_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('texto');
            $table->timestamps();
        });

        Schema::create('actuaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes');
            $table->string('descripcion');
            $table->timestamp('programada_para')->nullable();
            $table->timestamp('realizada_at')->nullable();
            $table->foreignId('realizada_por')->nullable()->constrained('users');
            $table->foreignId('evidencia_id')->nullable()->constrained('documentos');
            $table->unsignedSmallInteger('periodicidad_meses')->nullable();
            $table->date('proxima_renovacion')->nullable();
            $table->timestamps();
        });

        Schema::create('ajustes', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80)->unique();
            $table->json('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['ajustes', 'actuaciones', 'notas', 'avisos', 'plantillas', 'festivos', 'pagos', 'liquidacion_lineas',
            'liquidaciones', 'tasas', 'notificaciones', 'requerimientos', 'registros', 'documentos',
            'expediente_estados', 'expedientes', 'representaciones', 'procedimientos', 'personas'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
