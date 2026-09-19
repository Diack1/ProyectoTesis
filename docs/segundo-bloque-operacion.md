# Segundo bloque: operación de Parke’o

## Actualización: diseño anterior restaurado

Por solicitud del usuario, se recuperaron la portada, navegación, panel, autenticación y tablas del diseño anterior, con su paleta azul oscuro y naranja. Se conserva el nombre Parke’o y todas las funciones de tickets, cobros, sensores y revisión manual. Los formularios nuevos usan los estilos originales y el complemento `public/css/operaciones.css`; `parkeo.css` ya no se carga. Se verificaron 52 pruebas (250 comprobaciones) después de la restauración.

La copia previa al cambio visual está en `storage/app/private/backups/diseno-antes-restaurar-20260913-110147`. No se modificó la base de datos durante esta restauración.


## Lo implementado

- Ingresos presenciales con placa, espacio, tipo de vehículo y ticket único.
- Llegada de una reserva pagada vinculada al ticket, conservando el adelanto.
- Inicio del tiempo al emitir el ticket en espacios manuales; en espacios con sensor, al recibir la primera detección de ocupado posterior al ingreso.
- Contingencia manual para un sensor que no responde: hora observada, motivo y responsable registrados. No permite reiniciar un tiempo ya establecido.
- Consulta por placa/ticket, historial, impresión de ingreso y constancia al salir.
- Cobro presencial en efectivo (recibido y vuelto), Yape o Plin (operación verificada). El operador confirma recepción del dinero.
- Protección contra tickets simultáneos para una placa o espacio, doble salida y reutilización de una operación de pago.
- Tolerancia de llegada predeterminada de 15 minutos, configurable entre 10 y 15 para nuevas reservas. Después de la tolerancia, si no hay ingreso, se libera el espacio y el pago queda aprobado con una solicitud de revisión/reembolso pendiente. No se transfiere dinero automáticamente.
- Diseño original restaurado en la web y el panel; accesos añadidos para ingreso, caja y revisión de pagos.

## Cómo probar

1. Entrar al panel con una cuenta de personal. En **Registrar ingreso**, elegir placa, espacio y vehículo. Para una reserva web, seleccionarla primero en la lista de reservas pagadas.
2. Registrar e imprimir el ticket. En un espacio manual comienza el tiempo inmediatamente. En uno con sensor se muestra «Esperando sensor» hasta recibir una lectura.
3. Abrir **Tickets y salidas**, buscar la placa y consultar el saldo. En efectivo, registrar lo recibido. En Yape/Plin, contrastar el abono en el celular del negocio y escribir la operación.
4. Confirmar recepción del pago y salida. Imprimir la constancia. Una reserva pagada cubre su duración contratada; solo se cobra el exceso que corresponda.
5. En **Cobros y tolerancias**, revisar QR fijo del negocio y plazo de llegada. En **Reservas**, revisar inasistencias y reembolsos. «Registrar devolución realizada» registra una devolución que el administrador ya efectuó fuera de la web.

## Reglas de cálculo

Se conserva una copia de la tarifa al ingresar, de modo que editar precios no altere una estadía abierta. En reservas se conservan el adelanto, duración, tolerancia y penalidad de la contratación.

Para ingreso presencial se redondea el tiempo a minutos completos hacia arriba, se resta la tolerancia de la tarifa y se respeta su mínimo. Se aplica la modalidad seleccionada al ingresar: hora/fracción, bloques de 24 horas para tarifa diaria o bloques con la duración de la tarifa nocturna. La selección mantiene el criterio del sistema existente: nocturna dentro de su horario; de lo contrario, por hora o fracción. No se cambia automáticamente a una tarifa diaria al cumplir 24 horas.

En una reserva, el exceso se calcula después de la duración contratada más la tolerancia; cada fracción iniciada usa la penalidad configurada. Salir antes no devuelve automáticamente parte del adelanto.

La cotización de salida dura 5 minutos y queda vinculada al ticket y su inicio. Se registra tanto la hora de corte del cálculo como la hora efectiva de salida.

Una lectura de espacio libre no finaliza ni cobra un ticket. El personal debe cerrar la estadía. En espacios con sensor, después del cierre también debe existir una lectura de libre para volver a ofrecer el espacio.

## Operación automática

Los comandos `reservas:expirar` y `reservas:finalizar` están programados cada minuto. El segundo procesa inasistencias; las estadías terminan exclusivamente en caja. Para desarrollo local, ejecutar `php artisan schedule:work` en una terminal. En el servidor publicado, configurar la ejecución de `php artisan schedule:run` cada minuto. Las páginas de disponibilidad, reservas y operación también actualizan los vencimientos al consultarse.

## Validación

- 52 pruebas automatizadas, 250 comprobaciones, incluidas reservas/pagos existentes y nuevos casos de caja, adelantos, inasistencia, sensor, contingencia y cambio de tarifa/cruce de medianoche.
- Prueba de ingreso, cobro, vuelto y liberación sobre MySQL dentro de una transacción revertida: no quedaron registros ficticios.
- Revisión visual de portada, ingreso, panel, disponibilidad y menú en celular, y ticket de 80 mm. La impresión física requiere probar la impresora del negocio.
- Migración aplicada sobre la base local después de generar `storage/app/private/backups/pre-parkeo-20260913-102527.sql`. Se conservaron los datos existentes; no se reinició la base.

## Integración física pendiente

El código recibe ocupación mediante la API autenticada existente. Los ensayos automatizados no sustituyen la conexión real: quedan calibración, cableado, firmware y pruebas con los dos A02 RS485, el JSN-SR04T y los ESP32. Los 30 espacios siguen pudiendo operar manualmente y pasar a sensores gradualmente. La cámara y el reconocimiento de placas pertenecen a un bloque posterior.
