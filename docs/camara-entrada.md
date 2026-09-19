# Prueba de entrada con cámara USB

## Cómo empezar

1. Conectar la TEROS a la PC por USB. Cerrar otras aplicaciones que estén usando la cámara.
2. Abrir **Reconocer placas** en el navegador de esa misma PC, usando `http://localhost:8000/admin/placas` o `http://127.0.0.1:8000/admin/placas` si el servidor de desarrollo está en el puerto 8000. En una web publicada usar HTTPS. Un dominio local HTTP distinto de localhost puede no permitir la cámara.
3. Pulsar **Encender cámara** y permitir el acceso. No se solicita micrófono. Si hay varias cámaras, seleccionar la TEROS en la lista; Windows puede identificarla con un nombre genérico.
4. Apuntar a una placa o a una fotografía de prueba en una pantalla/papel. Pulsar **Capturar y analizar foto**. El sistema analiza ese fotograma y muestra los recortes y posibles clientes asociados.
5. Para la entrada real, encuadrar el acceso y pulsar **Iniciar detección automática**. Mantener esta página visible. **Pausar detección** detiene el análisis; **Apagar cámara** también libera el dispositivo. Al ocultar o abandonar la página, la cámara se apaga y debe encenderse de nuevo.

El rótulo «4K» de la cámara no confirma la resolución efectiva de la captura. La página indica la resolución obtenida; solicita hasta 1920 × 1080 como preferencia y reduce los fotogramas a un lado máximo de 1920 para esta prueba.

## Qué ocurre cuando aparece una placa

La página captura un fotograma, lo envía al servidor Laravel del proyecto y este ejecuta YOLOv9 + OCR localmente. Espera a terminar antes de tomar el siguiente, dejando dos segundos entre análisis. El intervalo real también incluye procesamiento y red: no equivale a vídeo analizado a 30 imágenes por segundo.

Para el aviso automático, la misma matrícula debe aparecer en dos análisis consecutivos separados por no más de diez segundos, con puntuación de detección de al menos 0,80 y OCR de al menos 0,85. Son valores iniciales para evaluar, no una garantía de exactitud. Lecturas insuficientes no generan un aviso estable; se puede probar la captura manual y corregir la matrícula.

Una placa que sigue visible no vuelve a avisar continuamente. Se necesita un período de quince segundos sin una lectura válida de ella y al menos sesenta segundos desde su aviso previo para volver a notificar. Al iniciar una nueva vigilancia se reinicia ese seguimiento. Los avisos son temporales en la página, no entradas definitivas ni un historial persistente de cámara.

**La cámara detecta una matrícula visible, no comprueba por sí sola que el vehículo haya cruzado la entrada.** Un auto estacionado frente a ella también puede aparecer. La prueba actual requiere revisión del operador. Una automatización desatendida de ingresos necesitaría zona/línea de cruce, seguimiento de dirección, evaluación en el acceso real y un servicio persistente independiente de la pestaña.

## Cómo se reconoce al cliente

En **Clientes y vehículos**, registrar la matrícula y el nombre comprobados con el cliente. El teléfono es opcional. Si tiene cuenta de cliente en la web, se puede vincular mediante su correo; entonces se utiliza el nombre de esa cuenta. Las reservas se buscan por la placa exacta registrada al reservar, independientemente de esta ficha.

La asociación es explícita: ni YOLO ni el historial de visitas adivinan quién es el propietario o conductor. Cada matrícula tiene una sola ficha; se puede editar o desactivar. Si cambia la persona asociada, revisar la ficha. Las visitas contadas corresponden al vehículo y podrían incluir conductores anteriores.

Al encontrar coincidencia exacta se muestra nombre, contacto registrado, placa, número de visitas finalizadas y, cuando corresponda, ticket activo o reservas de esa matrícula. **Vehículo frecuente** significa dos o más visitas finalizadas. Una ficha recién creada no inventa visitas anteriores. Sin ficha, se indica que no hay cliente asociado.

Si el OCR se equivoca, abrir **Consultar una matrícula corregida**, escribir la placa correcta y buscar. La consulta pausa la detección para evitar que otro fotograma reemplace el resultado. Los datos del cliente solo están disponibles para personal autenticado.

## Del reconocimiento al ticket

El enlace **Revisar y registrar ingreso** abre el formulario con la placa sugerida. El operador compara la placa, selecciona espacio y tipo de vehículo y emite el ticket. Las nuevas reservas web exigen placa, que se muestra al confirmar, pagar y consultar la reserva. Se normaliza a mayúsculas sin espacios ni guiones. La cámara avisa «Este vehículo tiene una reserva confirmada» y muestra código, persona que reservó, espacio y llegada prevista. Busca la matrícula exacta, sin exigir una ficha de cliente frecuente; nunca muestra las reservas de otro vehículo por compartir cuenta. Solo considera reservas con pago aprobado, sin ingreso registrado, sin inasistencia y dentro de la tolerancia: las del día y las de ayer cuya tolerancia cruza medianoche. Las de mañana no generan este aviso hoy. El aviso no crea tickets, pagos ni una asociación permanente con el conductor. Las reservas anteriores sin placa conservan sus datos y se pueden buscar manualmente por código; no generan coincidencias de cámara.

Si la matrícula ya tiene un ticket activo se ofrece ese ticket. Las reglas existentes evitan dos estadías activas del mismo vehículo. Detectar una placa no confirma pagos ni inicia el cobro: el comienzo sigue dependiendo del ticket manual o del sensor según el espacio.

## Datos, instalación y validación

Los originales de los fotogramas se eliminan del almacenamiento temporal al finalizar cada análisis. El resultado de cámara permanece en la página, no se almacena en la sesión ni se incorpora a un dataset. No se graba vídeo. El servidor recibe imágenes únicamente mientras el operador solicita captura o vigilancia. Si en el futuro se aloja fuera de esta PC, los fotogramas viajarán a ese servidor mediante HTTPS.

La tabla `cliente_vehiculos` se agrega con la migración del 19/09/2026. Se realizó una copia de la base antes de aplicarla. No se vincularon automáticamente las cuentas existentes a ninguna placa.

Pruebas de código: `php artisan test --compact` y `node --test tests/plate-tracker.test.mjs`. Las pruebas de cámara requieren además verificar permiso, imagen, enfoque, iluminación, placas conocidas/desconocidas, desconexión USB, pausa y coincidencias reales. Una prueba con una fotografía frente a la webcam no demuestra rendimiento con vehículos en movimiento ni de noche.

Referencia de compatibilidad: [getUserMedia — MDN](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia), [enumeración de cámaras — MDN](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/enumerateDevices).
