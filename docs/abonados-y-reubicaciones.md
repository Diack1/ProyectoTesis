# Abonados y reubicaciones

## Uso

1. Personal → Abonados → registrar cliente, placas autorizadas (máximo 5), espacio e inicio. T01–T26 son techo (S/ 270); T27–T41 son patio (S/ 240). La mensualidad solo se registra después de verificar su recepción; efectivo suficiente o referencia electrónica única. No hay cobros automáticos ni conexión a una pasarela.
2. Cada periodo dura un mes desde las 00:00 del inicio hasta las 00:00 del mismo día del mes siguiente, ajustado al último día cuando no existe. El final es exclusivo y se muestra en pantalla. Renovar crea otro periodo con su pago, conservando el historial. No se permiten periodos superpuestos en el mismo espacio.
3. El QR en el detalle del abonado abre su ficha para personal autenticado; requiere firma válida y mantiene las comprobaciones de acceso/MFA existentes. El QR no registra entrada ni acredita un pago por sí mismo.
4. Al llegar el abonado, elegir su abono en Registrar entrada, su espacio y una placa autorizada. Si está ocupado temporalmente, abrir el ticket del ocupante, elegir un destino libre sin otro abono vigente, moverlo físicamente y confirmar la reubicación. Después registrar al abonado. No hay movimientos automáticos.
5. Para ocupar temporalmente un espacio asignado, el ingreso requiere autorización, recepción de llave y ubicación de la llave. La salida prevista es opcional. El uso temporal lo decide el personal; la web no ofrece espacios con abono vigente y el servidor rechaza reservas que se extiendan hasta un abono futuro.
6. Sin destino libre se muestra un aviso y se mantiene la ubicación. No se han creado lugares ficticios, estacionamiento detrás de otro ni posiciones en la entrada.

## Cobro y auditoría

La reubicación cambia únicamente la ubicación actual del ticket y los estados de origen/destino. Conserva reserva original, inicio, contador, tarifa capturada y pagos. Registra origen, destino, encargado, fecha y motivo. La reserva original no vuelve a bloquear su antiguo espacio después de haber ingresado.

La estancia del abonado está cubierta hasta el vencimiento. Desde ese instante se cobra con la tarifa regular capturada al ingresar, incluyendo su mínimo y tolerancia. Una renovación pagada contigua amplía la cobertura; una renovación con días sin cubrir no elimina el cargo de ese intervalo. La salida exige verificar cualquier saldo real. El abono no se renueva automáticamente.

Se bloquean espacio y registros durante los cambios y se comprueban los datos otra vez al confirmar. Un formulario antiguo no puede trasladar un ticket desde una ubicación que ya cambió. Los destinos ocupados, incompatibles, con reserva pendiente o asignados a otro abonado se rechazan.

## Despliegue

Subir los archivos y ejecutar las migraciones pendientes antes de servir el nuevo código:

```sh
php artisan migrate --force
php artisan view:clear
```

La migración `2026_10_27_000001_abonados_y_reubicaciones` crea abonados, periodos y movimientos, y agrega referencias en pagos y estadías. No genera abonados ni pagos ficticios. No requiere variables de entorno nuevas. No usar `migrate:fresh` ni volver a sembrar la base de producción.

Pruebas: precios por zona, comprobación de pago, duplicados, superposición, renovación, vencimiento, autorización y firmas QR, privacidad de clientes, llaves, destinos ocupados, conservación de tickets y cierre posterior a reubicación.
