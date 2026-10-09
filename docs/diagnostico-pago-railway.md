# Error al continuar al pago en Railway

## Hallazgo y alcance

El botón «Confirmar y continuar al pago» hace POST a `/reservas/guardar/{espacio}`:
`ReservaController::store` valida placa, vehículo, disponibilidad y tarifa; bloquea el
espacio y crea una reserva `pendiente_pago` y un pago `pendiente` en la misma transacción.
Después redirige a `PagoController::show`, que muestra el QR. No realiza un cobro.

Este flujo, el envío de evidencia y la aprobación manual no envían notificaciones al
cliente. El observer de Pago escribe auditoría, sin activar alertas por correo. No se
ha cambiado Google OAuth ni la verificación de correo. Resend no explica el fallo de
esta ruta en la versión revisada.

La migración original de reservas usa enum. Laravel lo representa en PostgreSQL como
varchar con CHECK `reservas_estado_check`, que solo permite pendiente, confirmada,
cancelada y finalizada. La migración del flujo de pagos cambia el tipo a string, pero
PostgreSQL conserva ese CHECK. El insert de `pendiente_pago` puede fallar con SQLSTATE
23514. La nueva migración sustituye ese CHECK por los estados admitidos actualmente,
conservando validación, registros y transacciones. Es una reparación hacia adelante:
su down no restaura una restricción incompatible con pagos existentes.

Este defecto se comprobó en las migraciones y en el SQL generado por Laravel. **No se
ha confirmado que sea el error concreto del despliegue**, pues no hay acceso a su
esquema o logs. Los logs locales no contienen el incidente reciente. Las pruebas
funcionales usan SQLite; la prueba PostgreSQL verifica SQL en modo pretend, no un
servidor PostgreSQL real.

## Diagnóstico en producción (solo lectura)

En la consola/shell del servicio desplegado, después de desplegar este código:

```sh
php artisan payments:check
php artisan migrate:status
```

`payments:check` no muestra filas, contraseñas, claves ni correos y no envía mensajes.
Comprueba columnas necesarias y restricciones CHECK. No sustituye un chequeo completo
de permisos, índices, almacenamiento persistente o conectividad con Resend.

Para logs: Railway → ProyectoTesis → Deployments → despliegue activo → View logs.
Revisar los logs de ejecución alrededor de la hora del 500, no solo Build Logs.
Si aparece `23514` con `reservas_estado_check`, confirma la causa descrita.
Si aparece otra excepción, no asumir que esta migración la corrige. Compartir tipo,
mensaje saneado y primeras líneas de app/, sin SQL con valores ni datos personales.

## Variables

```dotenv
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=stderr
LOG_LEVEL=error
```

Conservar APP_KEY, APP_URL, OAuth y credenciales existentes. La conexión PostgreSQL
usa `DB_CONNECTION=pgsql` y `DB_URL` o los campos DB_HOST/DB_PORT/DB_DATABASE/
DB_USERNAME/DB_PASSWORD. En este proyecto se lee **DB_URL**, no DATABASE_URL directamente.
Si la conexión actual funciona, no cambiarla a ciegas.

Para los correos existentes conservar MAIL_MAILER=resend, RESEND_KEY,
MAIL_FROM_ADDRESS y MAIL_FROM_NAME. No se requiere ninguna variable nueva de correo
para la reparación. El remitente de pruebas de Resend no habilita envíos a todos los
usuarios: hace falta verificar un dominio para eso; es independiente del CHECK.

## Despliegue

1. Subir el código revisado a la rama conectada a Railway.
2. En Pre-deploy Command del servicio (integrándolo con cualquier comando existente):

```sh
php artisan migrate --force
```

3. Desplegar esa revisión. No ejecutar migrate:fresh, reset ni seed en producción.
4. Ejecutar `php artisan payments:check` y `php artisan migrate:status` en el servicio.
5. Si cambiaste variables y existe caché antigua, en el contenedor de la aplicación:

```sh
php artisan config:clear
php artisan config:cache
```

No sustituir el comando de inicio del servicio. Si la migración falla por estados no
contemplados, detenerse y revisar; no modificar ni borrar esos registros automáticamente.

## Validación local sin cobros

```sh
php artisan test --filter="PostgresPaymentSchemaTest|ParkeoPaymentTest|ReservaPlacaTest"
```

Se comprueba reserva/pago pendientes sin correo, aprobación manual, rechazo, reenvío,
referencias duplicadas, comprobantes privados, permisos y rollback ante error al crear
el pago. Reenvíos secuenciales están cubiertos; no se afirma haber ensayado concurrencia
real en PostgreSQL. Se conservan bloqueos y el índice único de operación existentes.

Referencias: https://docs.railway.com/guides/laravel y https://docs.railway.com/observability/logs
