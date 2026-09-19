# Integración IoT: recepción y calibración

## Estado de entrega

Se añadió Sensores IoT al panel conservando el diseño anterior. Los registros existentes y modos de los espacios no se cambiaron. La nueva integración requiere generación explícita de una credencial por sensor y calibración; los sensores anteriores no fueron inscritos automáticamente.

La migración se aplicó a MySQL después de la copia `storage/app/private/backups/pre-parkeo-20260913-152049.sql`. Validación: 62 pruebas, 294 comprobaciones, y recepción de tres muestras sobre MySQL dentro de una transacción revertida. No se dejaron sensores de prueba ni se generaron credenciales para dispositivos reales.

## Configuración gradual

1. Desde Espacios, asignar un código y modelo al sensor manteniendo el espacio manual.
2. Desde Sensores IoT, ajustar distancias mínima/máxima válidas y los umbrales según mediciones reales. El patrón actual es objeto cercano = ocupado: ocupado hasta el primer umbral, libre desde el segundo. Entre ambos no se confirma un cambio.
3. Definir cuántas lecturas consecutivas confirman el estado (2 a 10, inicialmente 3) y cuánto tiempo sin una confirmación reciente se tolera (15 a 300 segundos, inicialmente 60).
4. Activar la recepción y generar la credencial. Solo administrador/superadministrador puede configurar o renovar; el operador consulta diagnóstico.
5. Comprobar lecturas en modo manual; no cambian la ocupación ni el inicio del ticket.
6. Activar control por sensor desde Espacios solo cuando exista una lectura estable reciente. Se sincroniza el estado físico con esa lectura.

La integración no presume que los siete sensores simulados históricos son dispositivos conectados. Sus modelos y estados se conservan y la pantalla indica «Integración pendiente».

## Contrato para el ESP32

`POST /api/iot/sensores/{codigo_sensor}/lecturas`

Encabezados:

```text
Authorization: Bearer CREDENCIAL_DEL_SENSOR
Content-Type: application/json
Accept: application/json
```

Cuerpo:

```json
{"evento_id":"d05c86b1-b4ac-4b6c-8a7d-12a6a26b4b52","distancia_cm":83.25}
```

Cada nueva muestra lleva un UUID nuevo. Si se reintenta la misma muestra se conserva su UUID y distancia. Para falta de eco se envía `distancia_cm: null`; nunca cero como sustituto de libre.

La respuesta HTTP 200 indica recepción, no necesariamente un cambio de ocupación. `resultado` puede ser `sin_calibrar`, `lectura_invalida`, `zona_intermedia`, `confirmando`, `libre` u `ocupado`. `duplicada: true` indica que el paquete ya fue procesado: no vuelve a sumarse ni renueva la vigencia de la lectura.

Errores: 401 credencial inválida, 409 sensor/espacio inactivo o UUID reutilizado con otro contenido, 422 datos inválidos, 429 límite de frecuencia. El límite es 120 solicitudes por minuto, sensor e IP; usar aproximadamente una muestra por segundo en la prueba inicial.

El servidor utiliza su propia hora al confirmar ocupación y comenzar el ticket; el dispositivo no fija la hora de cobro. El sketch descarta muestras pendientes antiguas y no reenvía una cola acumulada después de desconectarse.

## Reglas de operación

- Una confirmación de ocupado inicia una sola vez el ticket asignado que esté esperando sensor.
- Una confirmación de libre no cobra ni cierra el ticket. Un ticket activo sigue bloqueando el espacio.
- Una lectura inválida no libera el espacio y reinicia la secuencia candidata.
- Si un sensor inscrito deja de confirmar lecturas recientes, un espacio físicamente libre deja de ofrecerse como disponible (`sin_senal`). El último estado físico no se borra.
- Cambiar calibración o renovar credencial exige no tener ticket activo y requiere nuevas confirmaciones.
- La credencial se guarda como hash y solo se muestra al generarla. La API antigua de estados no puede modificar un sensor inscrito: debe utilizar el contrato de distancias.
- Se conservan las muestras recibidas en `lecturas_sensores` para diagnóstico y tesis; los cambios confirmados se registran además en `registros_ocupacion`. No se configuró eliminación automática de ese histórico.

## Pendientes físicos

[Guía de Arduino IDE](../firmware/README.md): identificación de placa, verificación eléctrica, pines, compilación y carga. No se ha compilado/cargado el sketch ni probado sensores físicos. El ejemplo JSN requiere verificar su modo TRIG/ECHO. El driver de A02 RS485 está pendiente de identificar protocolo y adaptador. La cámara y YOLO siguen la [propuesta de placas](placas-yolo.md).
