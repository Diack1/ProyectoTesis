# Acceso y reservas

Google queda desactivado mientras falte cualquiera de estas variables del servidor:
`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`.
El botón no se muestra hasta configurarlas. No guardar claves en Git.

Crear un cliente OAuth de aplicación web en Google y registrar como URI de
redirección el dominio HTTPS del proyecto seguido de `/auth/google/callback`.
Asignar esa misma URL a `GOOGLE_REDIRECT_URI` en Railway. Configurar también
la pantalla de consentimiento y los usuarios de prueba antes de publicar.

Google está disponible para clientes. Si el correo ya existe, se solicita la
contraseña de Parke’o para vincularlo; Google nunca asigna permisos de personal.
Clientes y recepción activan su propio correo una vez. El superadministrador
mantiene la verificación por correo en cada nueva sesión. El envío de correo
debe seguir configurado para activación y recuperación de contraseña.

## Despliegue

Instalar dependencias del lock, compilar assets con `npm ci` y `npm run build`,
ejecutar `php artisan migrate --force` y renovar la caché de configuración
con `php artisan config:cache` después de asignar variables. La migración agrega
una identidad Google opcional y única; no elimina cuentas.

## Comprobaciones

- Sin credenciales, no aparece Google y sus rutas rechazan solicitudes.
- Con OAuth configurado, probar cuenta nueva, cuenta existente con vinculación,
  cancelación de Google y regreso a la reserva seleccionada.
- Recepción sin activar no entra al panel; después de activar, no repite código.
- El dueño sigue necesitando su código por sesión.
- Disponibilidad muestra el plano y calcula la tarifa al elegir vehículo,
  duración y espacio. El servidor vuelve a validar al reservar.
- El dashboard distingue cobros, reembolsos y pagos pendientes. Los pendientes
  son el saldo actual, no ingresos del período.

Las pruebas automatizadas usan OAuth y notificaciones simulados. La entrega
real de correo y el consentimiento real de Google se verifican en el entorno
configurado, sin usar credenciales dentro de los tests.

## Desarrollo local y móvil

Iniciar Laravel desde una terminal normal en la PC:
`C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000`.
El proceso necesita acceso de red saliente para enviar correo. Una autenticación
SMTP exitosa desde otra terminal no demuestra que el proceso web tenga ese acceso.
No iniciar una segunda instancia si el puerto ya está ocupado.

En Puertos de VS Code, reenviar el mismo puerto **8000** y abrir su URL actual
en el móvil. Un túnel al puerto 800 no apunta a esta instancia. Tras cambiar de
instancia, recargar la página para descartar documentos antiguos.

La prueba `php artisan security:check-mail-connection --send-test` envía un único
mensaje al propietario activo y distingue aceptación SMTP de recepción en buzón.
Usarla solo cuando se desea realizar ese envío real; sin el flag no envía correo.

## Si Gmail rechaza los códigos

Después de cambiar o revocar la contraseña de aplicación, actualizar la cuenta
remitente con `php artisan security:configure-gmail` desde una terminal privada
en la PC del sistema. Pide la contraseña sin mostrarla, autentica antes de
guardar y limpia la caché de configuración. No pegar la contraseña en el chat.
`php artisan security:check-mail-connection` comprueba SMTP sin enviar mensajes
y sin imprimir credenciales. La entrega efectiva se confirma al solicitar un
código desde la página. Railway tiene variables independientes del `.env` local.
