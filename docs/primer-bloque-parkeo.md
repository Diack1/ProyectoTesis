# Primer bloque de Parke’o

La plataforma conserva reservas, tarifas y reembolsos. El pago simulado fue sustituido por presentación de evidencia y revisión manual de Yape o Plin. No hay conexión automática con las billeteras ni verificación bancaria automática.

## Configuración inicial

1. Iniciar sesión como administrador o superadministrador.
2. Abrir **Configuración de cobros**. Registrar titular, celular, QR fijo de Yape y/o Plin y plazo de envío entre 5 y 60 minutos. Solo se ofrece al cliente un medio que tenga QR cargado.
3. Abrir **Personal** para crear operadores. El administrador crea y desactiva operadores; el superadministrador también administra cuentas de administrador. Los clientes se registran desde la página pública.
4. Revisar los tipos de vehículo permitidos en **Espacios**. Se completaron E01–E30 sin eliminar registros existentes. E01–E15 son la selección inicial para la tesis, editable desde el formulario de cada espacio.

## Pago y revisión

- El cliente crea la reserva y envía un número de operación, una captura o ambos. El importe se obtiene de la reserva, nunca del formulario del cliente.
- El plazo inicial es 10 minutos, configurable para nuevas reservas. El plazo cambia también al rechazar un pago.
- Después del envío, la reserva queda pendiente de revisión y conserva el bloqueo sin vencimiento automático. El personal debe revisar la bandeja, incluso si el horario solicitado ya pasó, y resolver el caso con el cliente. No se permite cancelar desde la cuenta del cliente mientras la evidencia está en revisión.
- El panel consulta cada 15 segundos la cantidad de pagos pendientes. El enlace abre **Revisar pagos**. No se envían correos, SMS ni avisos a aplicaciones externas.
- El personal comprueba manualmente el abono, importe y operación en el celular del negocio. Aprobar confirma la reserva; rechazar exige motivo y abre un nuevo plazo para corregir la evidencia. No se solicita repetir un abono ya efectuado.
- Cada intento conserva su evidencia, fecha, resultado y responsable. Una operación rechazada puede volver a presentarse corregida; una pendiente o aprobada no puede reutilizarse con el mismo medio de pago.
- Los comprobantes se guardan en almacenamiento privado y solo pueden consultarlos su propietario y el personal activo autorizado. Las capturas también requieren comparación humana para detectar reutilización.

## Roles

| Rol | Acceso |
| --- | --- |
| Cliente | Sus reservas, pagos y solicitudes de reembolso |
| Operador | Panel, monitoreo manual, consulta de reservas y reportes, revisión de pagos |
| Administrador | Funciones del operador, espacios, tarifas, cobros, reembolsos y cuentas de operador |
| Superadministrador | Funciones administrativas y gestión de administradores |

## Espacios y sensores

Los 30 espacios comienzan en control manual. Los sensores simulados anteriores se conservan como registros históricos y no equivalen a equipos instalados. Se puede seleccionar **Sensor instalado**, registrar código y modelo al incorporar cada equipo. No se programó todavía el firmware de los ESP32 ni la cámara.

La ocupación física se guarda independientemente del bloqueo de una reserva. La disponibilidad pública combina ambas condiciones. Se conserva por ahora la restricción anterior de una reserva activa por espacio; las reservas por intervalos simultáneos no forman parte de este bloque. Los estados `reservado` antiguos permanecen hasta su liberación o verificación manual; no se inventaron mediciones físicas para reemplazarlos.

La API de escritura de sensores requiere autenticación Sanctum de personal activo y un espacio en modo sensor. Las futuras credenciales de dispositivos se configurarán con la integración del hardware.

Desactivar un espacio conserva sus reservas, pagos e historial.

## Instalación y comprobación

En la base local ya se ejecutaron la migración y `ParkeoSeeder`. Para otra copia del proyecto:

```sh
php artisan migrate
php artisan db:seed --class=ParkeoSeeder
php artisan test
```

El sembrador conserva los espacios y asociaciones existentes, agrega los faltantes y marca E01–E15. Repetirlo restablece esa selección inicial del estudio; no usarlo para cambiar una selección ya personalizada.

Se guardó un respaldo SQL previo a los cambios en `storage/app/private/backups/pre-parkeo-20260913-003705.sql`. Contiene datos privados y debe mantenerse fuera del directorio público. No se ejecutó una migración destructiva ni se eliminaron las cuentas de prueba.
