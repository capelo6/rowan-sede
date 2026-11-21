# ROWAN Sede

Núcleo de **sede electrónica** y **panel de tramitación** para entidades del sector público y organismos que tramitan con la ciudadanía. Es software libre ([EUPL-1.2](LICENSE)) y está hecho con Laravel. Cada organismo lo adapta configurando su catálogo de trámites, sus tasas, sus plantillas y sus integraciones.

> Titular: **ROWAN S. Coop. Galega**. La licencia permite usar, estudiar, modificar y redistribuir el código. Quien lo use no depende de ROWAN para mantenerlo ni ampliarlo.

## Qué incluye

**Sede electrónica (ciudadanía)**
- **Acceso con certificado electrónico** (FNMT, DNI electrónico y demás cualificados) firmando con **AutoFirma** un reto de un solo uso. Se verifican la firma CAdES, la vigencia, el prestador de confianza y la revocación por **OCSP**. Funciona aunque haya un WAF o proxy que termine el TLS delante del servidor: no depende del certificado de cliente TLS.
- Los **certificados de representante de persona jurídica** acreditan la representación automáticamente. La arquitectura está preparada para añadir **Cl@ve**.
- **Primer acceso**: datos del certificado, correo de aviso, política de privacidad y consentimiento de notificaciones electrónicas.
- **Catálogo de trámites** con información previa y documentación necesaria. Los formularios se configuran desde el panel, sin programar.
- **Solicitudes**:
  - borrador con guardado automático;
  - documentos con formatos y tamaños configurables;
  - pantalla de resumen;
  - **firma con AutoFirma** (CAdES sobre el PDF de la solicitud);
  - **registro de entrada** numerado con **justificante** (fecha y hora, huellas SHA-256 y CSV).
- **Carpeta electrónica**:
  - expedientes con línea temporal en lenguaje claro;
  - buzón de notificaciones, documentos y representaciones;
  - avisos con los **días hábiles restantes** y tres niveles (urgente, pendiente, todo en orden).
- **Subsanación**: la persona ve solo lo que se le requiere. También puede hacer **desistimiento voluntario**.
- **Notificaciones por comparecencia** (art. 43 de la Ley 39/2015): puesta a disposición, aviso por correo, acceso y rechazo a los 10 días naturales, con justificante en cada caso.
- **Pago de tasas** con el **TPV virtual (Redsys)** y **Bizum**, notificación firmada HMAC-SHA256, conciliación y justificante.
- **Verificación de documentos** por código seguro de verificación (CSV).
- Accesibilidad **WCAG 2.1 AA**: navegación por teclado, foco visible, etiquetas y errores asociados, y adaptación a 320 px.
- **Declaración de accesibilidad** según el RD 1112/2018, aviso legal, privacidad y cookies (solo técnicas).
- **Fecha y hora oficial** de la sede en el pie.

**Panel de gestión (personal)** en `/gestion`
- Acceso con **usuario, contraseña y segundo factor** (TOTP con códigos de recuperación) y roles: administración, tramitación y consulta.
- **Inicio** con las solicitudes nuevas, en revisión, documentación recibida, pagos y plazos próximos.
- **Bandeja** en lista y en **tablero (kanban)**, con filtros y búsqueda por nombre, NIF o número.
- **Ficha del expediente**:
  - revisión asignada y lista de comprobaciones por bloques;
  - **requerimiento de subsanación**;
  - **asignación de tasas** (catálogo por ejercicio, con o sin IVA, y líneas manuales motivadas);
  - pago en ventanilla;
  - servicios programados con fotografía y renovaciones;
  - **resolución con plantillas** y **sello electrónico PAdES**;
  - desistimiento propuesto por el sistema y confirmado por el personal;
  - **descarga del expediente en ZIP con índice** y notas internas.
- **Registro de entrada y salida**, pagos y notificaciones, con exportación a Excel.
- **Auditoría** de accesos, consultas, descargas, cambios de estado y exportaciones.
- Configuración de procedimientos, tasas, festivos, plantillas, personal y ajustes de la sede.

**Documentos de salida**: PDF con sello electrónico del organismo en **PAdES-BASELINE-B**, con [`tc-lib-pdf`](https://github.com/tecnickcom/tc-lib-pdf) (LGPL), y CSV al pie.

**Plazos**: cómputo en días hábiles con el calendario de festivos (art. 30 de la Ley 39/2015). Hay avisos por correo al presentar, en cada cambio de estado, a mitad de plazo y antes del vencimiento.

## Requisitos

- PHP 8.4 con las extensiones `openssl`, `pdo_mysql` (o `pdo_sqlite`), `mbstring`, `intl`, `gd`, `zip`, `fileinfo` y `curl`.
- MariaDB 10.6+ o MySQL 8 (SQLite para desarrollo).
- Composer 2.
- Un servidor web con HTTPS (Apache o Nginx; vale un alojamiento con **Plesk**).
- Tarea programada (cron) cada minuto para `php artisan schedule:run`.

No necesita Node.js en el servidor ni licencias de pago.

## Instalación

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# configure .env: base de datos, correo (MAIL_*), datos del organismo (SEDE_*), Redsys
php artisan migrate --force
php artisan sede:roles
php artisan filament:assets
php artisan make:filament-user   # primer usuario; después asígnele el rol «administracion»
```

**Tarea programada:** `* * * * * cd /ruta && php artisan schedule:run >> /dev/null 2>&1`. Se encarga de las notificaciones rechazadas por plazo, las propuestas de desistimiento y los recordatorios.

### Certificados de confianza

Copie en `storage/app/confianza/` (o en la carpeta que indique `SEDE_CONFIANZA`) los certificados raíz e intermedios de los prestadores que acepte la sede: la FNMT (AC Usuarios, AC Representación), la DGP (DNIe) y otros de la lista de confianza (TSL). Valen en PEM o DER. Sin esos certificados, la sede rechaza el acceso (`SEDE_EXIGIR_CONFIANZA=true`).

### Sello electrónico

Indique el PKCS#12 del sello del organismo en `SEDE_SELLO_P12` y su contraseña en `SEDE_SELLO_CLAVE`. Si no hay sello configurado, los documentos se generan sin firma.

### AutoFirma

La página de acceso carga el cliente oficial `public/vendor/autofirma/autoscript.js` ([clienteafirma](https://github.com/ctt-gob-es/clienteafirma), GPL-2.0+/EUPL-1.1). La ciudadanía necesita tener [AutoFirma](https://firmaelectronica.gob.es/Home/Descargas.html) instalado.

### Redsys y Bizum

Por defecto se usa el **entorno de pruebas** de Redsys con el comercio público de pruebas. Para producción:
- `REDSYS_ENTORNO=real`;
- `REDSYS_FUC`, `REDSYS_TERMINAL` y `REDSYS_CLAVE` del comercio;
- URL de notificación: `https://<sede>/pagos/notificacion`.

## Demostración

```bash
php artisan migrate:fresh
php artisan sede:demo            # organismo ficticio «Mancomunidad de Valdeloria»
SEDE_ACCESO_SIMULADO=true SEDE_GESTION_2FA=false php artisan serve
```

- Sede: `http://localhost:8000`. Hay acceso de demostración sin certificado, que no funciona si `APP_ENV=production`.
- Panel: `http://localhost:8000/gestion`, con los usuarios `admin@`, `tramitacion@` y `consulta@valdeloria.example` y la clave `demo-sede-2026`.

## Pruebas

```bash
php artisan test
```

Las pruebas generan una autoridad de certificación propia y certificados con los perfiles españoles. Firman en CAdES como AutoFirma y comprueban:
- el acceso;
- el flujo completo de solicitud, requerimiento, subsanación, tasa, pago con Redsys o Bizum, resolución sellada y verificación por CSV;
- los plazos y el desistimiento;
- la confidencialidad entre personas;
- el panel con sus permisos.

## Estructura

| Carpeta | Contenido |
|---|---|
| `app/Sede/Identidad` | Acceso con certificado: reto, verificación CAdES, perfiles de certificado, confianza y OCSP |
| `app/Sede/Expedientes` | Reglas de tramitación (`Tramitador`) y exportación del expediente |
| `app/Sede/Registro` | Registro de entrada y salida |
| `app/Sede/Documentos` | Almacén con huella y CSV, PDF y sello PAdES |
| `app/Sede/Notificaciones` | Comparecencia, rechazo y justificantes |
| `app/Sede/Pagos` | Liquidaciones y Redsys/Bizum |
| `app/Sede/Plazos` | Calendario de días hábiles y semáforo |
| `app/Sede/Avisos` | Correos y recordatorios sin duplicados |
| `app/Http/Controllers/Sede` | Sede pública |
| `app/Filament` | Panel de gestión |

## Licencia

[EUPL-1.2](LICENSE). Copyright © 2026 ROWAN S. Coop. Galega. Los componentes de terceros conservan sus licencias:
- Laravel, Filament y spatie: MIT.
- tc-lib-pdf: LGPL-3.0.
- autoscript.js: GPL-2.0+/EUPL-1.1.
- Fuentes DejaVu: licencia libre de Bitstream Vera.
