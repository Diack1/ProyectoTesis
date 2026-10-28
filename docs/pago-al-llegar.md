# Reserva con QR y cobro al llegar

Las reservas nuevas usan `pago_al_llegar=true`. Mantienen el estado interno `pendiente_pago` hasta que el encargado registre el cobro y el ingreso. El cliente recibe un código RSV y un QR generado localmente; no se envían datos a servicios externos.

El espacio se retiene 15 minutos desde la creación. El encargado abre el enlace con la cámara de su celular, inicia sesión con su cuenta de personal y completa la verificación de acceso habitual. También puede buscar el código en Operación → Registrar entrada. Un escaneo solo consulta información: no cobra, no confirma ni registra una entrada.

El encargado verifica el efectivo o la transferencia en la cuenta del negocio, registra el método y confirma la recepción. El servidor calcula el importe a partir de la reserva, exige efectivo suficiente o una referencia electrónica única y guarda cobro y entrada en la misma transacción. Si falla la entrada, el cobro no queda aprobado. Los reintentos no duplican entradas.

El contador está en Mis reservas → Ver reserva y tiempo. Consulta el servidor cada 15 segundos y se detiene al registrarse la salida. Los espacios manuales inician al registrar la entrada; los espacios con sensor conservan su inicio mediante sensor. El extra mostrado es estimado; el proceso de salida mantiene la verificación del cobro real.

Auto y camioneta: S/ 5 por hora o fracción, con 5 minutos de tolerancia sobre el tiempo contratado. Para una hora: 1:05:00 no añade importe; 1:05:01 añade S/ 5; 2:05:01 añade S/ 10. Las reservas y estadías existentes mantienen sus condiciones capturadas. Moto conserva su tarifa.

Las reservas anteriores conservan su proceso de pago y revisión de comprobantes. No se modifican Google OAuth, verificación de correo ni MFA del personal.

## Despliegue

Incluir `composer.json`, `composer.lock`, vistas, archivos públicos, controladores, servicios y las cinco migraciones del 26/10/2026. Railway debe instalar dependencias de Composer y compilar los recursos como habitualmente. Antes de atender solicitudes con el nuevo código:

```sh
php artisan migrate --force
php artisan config:cache
php artisan view:cache
```

Mantener `APP_URL=https://parkeo.up.railway.app` y `APP_DEBUG=false`. No requiere nuevas claves ni servicios para QR. No cambiar `APP_KEY`: firma los enlaces. Si cambia el dominio, volver a abrir el pase para generar un QR actualizado. No ejecutar `migrate:fresh` ni seeders en producción. Mantener el scheduler de reservas existente para liberar reservas vencidas.

Validación: pruebas aisladas de autorización, firma alterada, QR sin escrituras, vencimiento, placa incorrecta, efectivo insuficiente, operación repetida, rollback tras fallo de inserción, reintento de entrada y límites exactos de tolerancia. No se realizan cobros reales.
