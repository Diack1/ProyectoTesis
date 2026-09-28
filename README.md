# Parke’o · Gestión de cochera

Proyecto de tesis con Laravel 12 / PHP 8.2+, MySQL, sensores ESP32 y reconocimiento de placas YOLO + OCR.

## Funciones

- Plano 2D de referencia E01–E30 y vista en lista. Estados manuales o por sensor; consulta pública sin datos de clientes.
- Reservas web con placa y prepago Yape/Plin mediante QR del negocio. Revisión manual del pago y reembolso.
- Ingreso con ticket, inicio de tiempo manual o mediante sensor y cobro a la salida.
- Cámara USB con consulta de clientes y reservas por matrícula. La detección avisa; el operador registra el ingreso.
- Panel de recepción agrupado en Inicio, Entradas y salidas, Reservas y Clientes.

## Navegación y permisos del personal

- **Recepción (`admin`)**: entradas, tickets, cobros, revisión de pagos, reservas, clientes y lectura de placas. Las cuentas antiguas `operador` mantienen este mismo acceso.
- **Dueño (`super_admin`)**: toda la operación, Reportes y Configuración (espacios, tarifas, formas de pago, personal y sensores). Solo este rol decide los reembolsos. Las restricciones también se comprueban en el servidor.
- **Clientes (`user`)**: conservan su portal público de reservas y pagos.

Inicio ofrece cuatro accesos: registrar entrada, cobrar salida, ver reservas de hoy y buscar vehículo. Reservas separa llegadas de hoy, próximas, pagos por revisar e historial/reembolsos. Registrar llegada requiere un pago aprobado; el reconocimiento de placa sigue siendo una ayuda para recepción y no genera un ingreso automáticamente.

No se requiere una migración de datos para esta reorganización. Las nuevas cuentas de personal se crean como Recepción desde la cuenta del dueño.

## Preparar otra copia

Los estilos se editan en `resources/css/` (shared, public, admin, auth y pages),
y los recursos vectoriales en `resources/images/`. Vite genera los archivos
minificados y versionados de `public/build/`; no se editan a mano ni se guardan
en Git. Después de clonar o cambiar estilos, ejecutar `npm ci` y `npm run build`
antes de servir la aplicación; durante desarrollo puede usarse `npm run dev`.
Usar Node 22.12+ compatible con Vite. La revisión de organización está en
`docs/organizacion-recursos-2026-09-28.md`.

1. Instalar dependencias: `composer install` y `npm ci`.
2. Copiar `.env.example` a `.env`, configurar la base de datos y ejecutar `php artisan key:generate` para una instalación nueva.
3. Ejecutar `php artisan migrate`. En una base nueva, revisar los seeders antes de cargarlos; no ejecutarlos indiscriminadamente sobre un respaldo con datos.
4. Ejecutar `npm run build` y `php artisan serve`. Abrir `http://127.0.0.1:8000`.
5. Configurar QR y tarifas desde el panel. Seguir `vision/README.md` para modelos y entorno de reconocimiento; `docs/camara-entrada.md` para pruebas de cámara.
6. Configurar el planificador de Laravel para procesar los vencimientos (`php artisan schedule:work` en desarrollo).

## Comprobaciones

```sh
php artisan test --compact
node --test tests/plate-tracker.test.mjs
```

Las pruebas PHP utilizan SQLite en memoria. No ejecutar `migrate:fresh` en la base del negocio.

## Respaldo con Git

Git conserva el código y las migraciones, no los datos del negocio. Revisar `git status` y `git diff --check` antes de crear el commit. No publicar `.env`, credenciales de sensores, comprobantes, dumps SQL, imágenes de clientes ni el entorno virtual. Los modelos se preparan siguiendo `vision/README.md`.

Para recuperar una instalación existente, guardar además una copia privada de MySQL, `storage/app/private`, archivos públicos cargados y `.env` con su `APP_KEY` original. Conservar estos respaldos fuera del repositorio y en otra ubicación segura. Restaurar una copia existente no requiere regenerar su clave.

## Plano de referencia

La distribución se basa en la imagen facilitada: nueve espacios al fondo, once a la derecha, siete a la izquierda y tres interiores. Los códigos sin habilitar aparecen grises y no se activan automáticamente. La geometría está en `resources/views/partials/plano.blade.php`; validar numeración y circulación en la cochera antes de instalar señalización.

El panel administrativo muestra el estado al abrirlo y permite actualizarlo. El plano público consulta estados cada diez segundos. Si falla la consulta, deshabilita la reserva hasta recuperar comunicación. El servidor vuelve a validar al guardar.

## Reservas inmediatas

Las nuevas reservas web no permiten elegir una llegada futura. El servidor usa la hora actual para cotizar y registra la modalidad inmediata. Al aprobarse el pago, empieza un plazo fijo de 15 minutos para llegar. Vencido el plazo sin ingreso, el espacio se libera y el pago pasa a revisión de reembolso. Las reservas anteriores a esta modalidad conservan su cálculo de llegada. Aplicar la migración `2026_09_25_220000_add_reserva_inmediata_to_reservas` en otras instalaciones.

Los textos legales permanecen como borradores en `docs/textos-legales-borrador.md` hasta completar responsable, dirección y demás campos pendientes.
