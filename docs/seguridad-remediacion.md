## Registro de clientes: verificación por código

Los registros nuevos reciben un código en su propio correo. Es obligatorio verificar antes de utilizar las rutas de reservas y pagos; también se comprueba en JSON y al entrar por URL directa. Las cuentas existentes con correo ya verificado conservan su acceso; las que no lo tienen deben verificarlo.

Código aleatorio de seis dígitos, HMAC ligado al usuario y correo, vigencia de 10 minutos, máximo cinco intentos persistentes y consumo único bajo bloqueo de fila. Reenvío: uno por minuto y cinco por hora; un envío exitoso reemplaza el código previo. Un error SMTP conserva la cuenta pendiente y permite reintentar. No se guardan códigos en texto plano en la base de datos ni se muestran en JSON. Las rutas antiguas de confirmación por enlace fueron reemplazadas por POST con CSRF. El acceso del personal sigue separado.

Validación: pruebas con correo simulado, sin enviar correos reales a clientes. La entrega real de códigos de registro deberá comprobarse con una cuenta controlada por el propietario.

## Flujo vigente: código del personal al correo del propietario

Sustituye la aprobación mediante bandeja descrita más abajo. Después de una contraseña válida, se envía automáticamente un código de seis dígitos al único superadministrador activo. El propietario entrega el código al empleado si autoriza el acceso. El empleado lo introduce en su sesión original. Caduca en 5 minutos, máximo 5 intentos, un solo uso. El propietario también verifica su propio acceso por correo. Las rutas y vistas de la bandeja anterior se retiraron; sus solicitudes antiguas no permiten entrar.

El envío SMTP real continúa pendiente de cuenta remitente y credenciales privadas; no basta con configurar la dirección destinataria. No se enviaron mensajes reales durante las pruebas. El correo del propietario no cambia su contraseña anterior.

## Cambio vigente: autorización del propietario (27/09/2026)

El flujo TOTP descrito en fases anteriores quedó retirado por decisión del propietario. Se eliminaron su controlador, pantallas, rutas, servicio, comando de reinicio y dependencias exclusivas. La migración histórica y columnas se conservan para no alterar migraciones ya aplicadas; ninguna concede acceso.

- Superadministrador: contraseña y código enviado a su correo, válido 5 minutos, máximo 5 intentos, un solo uso.
- Admin/operador: contraseña, solicitud al correo del único propietario activo y aprobación explícita de esa sesión. Solicitud válida 10 minutos. Abrir el enlace del correo nunca aprueba automáticamente.
- La aprobación no se repite al navegar; cerrar sesión o perder la sesión obliga a verificar de nuevo. Cerrar una pestaña no equivale a cerrar sesión.
- Para entrar tras la aprobación, el empleado pulsa «Consultar autorización y entrar». Sin aprobación o con correo sin configurar, el acceso permanece bloqueado.
- Solo se admite un propietario activo para seleccionar al destinatario sin ambigüedad.
- La bandeja de aprobaciones requiere la sesión verificada del propietario. La interfaz usa el diseño adaptable existente; la entrega y visualización en un móvil real siguen pendientes.

### Activación de correo pendiente
Configurar el proveedor SMTP, remitente y credenciales en `.env` de forma privada (nunca en el chat o Git). `MAIL_MAILER=log` no envía. Usar una URL HTTPS en `APP_URL` accesible desde el móvil para los enlaces. Configurar `SECURITY_ACCESS_MAIL_READY=true` solo tras comprobar SMTP. Mantener `SECURITY_REQUIRE_STAFF_MFA=true`. Ejecutar `php artisan config:clear` tras cambiar configuración y probar envío real con autorización. No se enviaron correos reales durante las pruebas.

El comando `security:assign-owner-email DIRECCION` revisa sin cambiar nada; con `--apply` desactiva al cliente anterior, retira su correo, revoca las sesiones de ambas cuentas y conserva reservas/pagos. No cambia la contraseña del propietario.

---

# Seguridad: primera remediación

Se retiró la contraseña fija del seeder. Crear un propietario nuevo requiere `php artisan app:create-owner`; no ejecutar sobre producción sin preparar el alta. Las cuentas existentes no fueron cambiadas. Rotar manualmente cualquier contraseña inicial que se haya utilizado: sigue presente en el historial Git.

Cerrar cuenta ahora desactiva el acceso y conserva registros. Cambiar contraseña revoca tokens y sesiones de base de datos; el middleware comprueba contraseña de sesión y cuenta activa. El cambio de correo requiere contraseña actual, pero aún falta confirmación del nuevo correo antes de sustituirlo.

Se bloquearon decisiones antiguas de reembolso dentro de transacciones, se restringió la telemetría a personal autenticado, se limitaron registro/reservas/uploads y se neutralizan fórmulas CSV. Las páginas autenticadas usan no-store para reducir exposición en dispositivos compartidos.

Pendiente antes de publicar: MFA administrativo, confirmación del nuevo correo, auditoría persistente de cambios sensibles y alertas, revisión actualizada de dependencias, rotación de credenciales existentes, usuario MySQL de privilegios mínimos, debug desactivado, SMTP real, HTTPS/cookies/proxy, permisos de archivos y restauración de respaldos. No se cambió el entorno real ni se reescribió Git.

Las pruebas usan SQLite en memoria y datos ficticios. El rechazo de una decisión obsoleta de reembolso no sustituye una prueba concurrente en MySQL. Verificar también dos dispositivos, expiración, reintentos móviles y navegación atrás tras logout en staging.


## Segunda remediación

Cambio de correo: contraseña actual, código enviado al nuevo correo, vencimiento de 10 minutos, máximo 5 intentos persistentes por solicitud y limitación de solicitudes. El correo original se conserva hasta confirmar. Se puede cancelar; solicitar otro cambio invalida el anterior. Cambiar contraseña, recuperar acceso o desactivar cuenta invalida también el cambio pendiente. El código se guarda mediante HMAC y no se incluye en datos serializados ni en old input.

Actividad de seguridad: tabla `security_events`, accesible únicamente por superadministradores en Configuración > Seguridad. Registra identificadores internos, fecha, operación y nombres de campos modificados en usuarios, configuración de cobros, pagos, reembolsos y sensores. No guarda valores de contraseñas, correos, teléfonos, tokens ni capturas. Es una trazabilidad inicial, no un archivo externo inmutable: escrituras SQL directas no pasan por los observadores. Quedan pendientes alertas y retención.

Migración aditiva: `2026_09_26_120000_add_security_records.php`. No elimina tablas ni datos. En otros entornos debe aplicarse antes de servir esta versión. El mailer local log no entrega correo; configurar SMTP antes de uso público. Las pruebas de notificaciones usan Notification::fake y no envían mensajes reales.


## Tercera remediación — 27/09/2026

### Doble factor del personal

El personal activo (super_admin, admin, operador) debe configurar TOTP antes de acceder al panel, perfil o cualquier otra ruta web. No se exige a clientes. El requisito está activo por defecto; `SECURITY_REQUIRE_STAFF_MFA=false` solo sirve para despliegues de prueba sin matricular y hace fallar la comprobación de producción. Una cuenta ya matriculada sigue requiriendo doble factor incluso con esa opción desactivada.

Entrar con contraseña → configurar autenticador → confirmar código → guardar ocho códigos de recuperación. El QR se genera localmente con BaconQrCode. En el mismo teléfono se puede introducir la clave manualmente en la aplicación autenticadora. La clave pendiente dura 10 minutos, se cifra en sesión, la definitiva se cifra en la base de datos y los códigos de recuperación se guardan como hashes HMAC. Cada código de recuperación se muestra una vez y solo permite un acceso. TOTP se verifica mediante OTPHP (SHA1, 6 dígitos, 30 segundos, tolerancia de un período) con bloqueo de fila y protección frente a reutilización.

Los intentos se limitan por cuenta y por IP. El estado de segundo factor se vincula al usuario, contraseña y clave TOTP; cambiar contraseña o recuperar MFA exige verificar de nuevo. No hay una ruta web que desactive o sustituya el autenticador. Seguridad de mi cuenta permite renovar códigos de recuperación con contraseña y segundo factor.

Si se pierde autenticador y códigos: responsable con acceso de consola y tras verificar la identidad ejecuta `php artisan security:reset-mfa ID`. Requiere confirmación y una contraseña nueva de al menos 14 caracteres; revoca sesiones, tokens y códigos, registra el cambio y obliga a configurar otro autenticador. Nunca ejecutar este comando para terceros sin verificar identidad. La clave APP_KEY debe respaldarse de forma segura para recuperar secretos cifrados.

Los antiguos endpoints API de personal con tokens Sanctum no permiten omitir MFA. Los ESP32 deben usar `/api/iot/sensores/{codigo}/lecturas` con su credencial por sensor, que conserva su funcionamiento.

### Alertas

Configuración → Seguridad muestra cambios sensibles pendientes de revisión. Solo superadministradores pueden marcarlos revisados; se registra autor/fecha de revisión. Incluye cambios de personal, contraseña, correo, QR, tokens de sensores y MFA, además de bloqueos del segundo factor (agrupados por cuenta) y del login (agrupados por cuenta/IP durante una hora; no se guarda correo ni IP en el evento). No es un registro inmutable externo ni cubre SQL directo.

Para alertas externas configurar `SECURITY_ALERT_EMAIL` con un destinatario autorizado, un mailer real y una cola persistente. Ejecutar el worker bajo un supervisor (`php artisan queue:work --tries=3`). Se programa el envío después del commit y el mensaje solo contiene un identificador y enlace al panel. Con mailer log/array o destino vacío no se envían alertas externas. Reintentos pueden duplicar el aviso; usan el mismo identificador de evento. No se han enviado correos reales durante las pruebas.

### Publicación y dependencias

`php artisan security:check-production` comprueba configuración local sin imprimir secretos ni modificar datos. No sustituye verificar TLS, permisos SQL, entrega de correo, supervisor, aislamiento de archivos o restauración de copias en el servidor final.

Composer identificó 27 avisos en 7 paquetes. Se actualizaron esos paquetes y una dependencia relacionada dentro de versiones compatibles; Composer terminó sin avisos conocidos. Esto cubre Composer, no el inventario npm/Python ni garantiza ausencia de vulnerabilidades desconocidas. Se declaró explícitamente App\Services → app/services en PSR-4 para que funcione en Linux.

Migración aditiva: `2026_09_27_120000_add_staff_two_factor.php`; aplicar antes de servir esta versión. No se inicializan secretos de cuentas reales automáticamente ni se ejecutan recuperaciones sobre usuarios existentes. La suite general desactiva la exigencia para sus fixtures antiguos; StaffTwoFactorTest la activa y prueba los bloqueos, QR, cifrado, caducidad, TOTP, reutilización, recuperación y API.
