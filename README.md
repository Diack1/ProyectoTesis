# Parke’o · Gestión de cochera

Proyecto de tesis con Laravel 12 / PHP 8.2+, MySQL, sensores ESP32 y reconocimiento de placas YOLO + OCR.

## Funciones

- Plano 2D de referencia E01–E30 y vista en lista. Estados manuales o por sensor; consulta pública sin datos de clientes.
- Reservas web con placa y prepago Yape/Plin mediante QR del negocio. Revisión manual del pago y reembolso.
- Ingreso con ticket, inicio de tiempo manual o mediante sensor y cobro a la salida.
- Cámara USB con consulta de clientes y reservas por matrícula. La detección avisa; el operador registra el ingreso.
- Panel agrupado en Inicio, Operación, Reservas y pagos, Reportes y Configuración.

## Preparar otra copia

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
