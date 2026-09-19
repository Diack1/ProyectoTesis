# ESP32 y sensores — Arduino IDE

## Estado actual

Las fotos del 13/09/2026 identifican un **ESP32 DEVKIT V1 TYPE-C** de 30 pines y un **JSN-SR04T-V3.0**, con pines impresos `5V`, `Trig`, `Echo` y `GND`. El sketch `parkeo_esp32/parkeo_esp32.ino` prepara lectura TRIG/ECHO y envío autenticado. La configuración propone D18 para TRIG y D19 para ECHO adaptado. Mantiene el hardware y la red desactivados hasta revisar el cableado. No se ha compilado ni cargado en la placa; no se encontró Arduino CLI ni el paquete ESP32 en las ubicaciones habituales.

Los A02 RS485 requieren identificar modelo completo, protocolo, alimentación y adaptador RS485 compatible con los niveles del ESP32. No se han supuesto direcciones Modbus, velocidades ni registros. Wi-Fi conecta el ESP32 con el servidor; no reemplaza la interfaz eléctrica RS485.

## Antes de conectar

Seguir [la guía de primera prueba](../docs/conexion-jsn-v3.md). Las fotos ya permiten ubicar los pines del ESP32 y JSN. Queda por verificar el nivel de ECHO, alimentación y modo efectivo del sensor. Para los A02 aún necesitamos el modelo completo o enlace de compra y saber qué adaptador RS485 hay disponible; los colores de los cables no identifican su función de forma fiable.

## Preparación del programa

1. Instalar/seleccionar el soporte oficial ESP32 en Arduino IDE y el modelo exacto de placa.
2. Abrir la carpeta `parkeo_esp32`. Copiar `config.example.h` a `config.h`.
3. Después de confirmar cableado y modo, definir TRIG/ECHO y poner `HARDWARE_CONFIRMED = true`. Mantener `SEND_TO_SERVER = false` al probar distancias en el monitor serie a 115200 baudios.
4. Medir el espacio vacío y con vehículo. Una falta de eco no se interpreta como libre.
5. En Espacios asignar el código del sensor manteniendo control manual. En **Sensores IoT**, definir los límites y umbrales medidos, activar recepción y generar la credencial. Copiarla a `config.h`; no usar la contraseña de un administrador.
6. Definir SSID, contraseña, URL y token. Desde el ESP32, `localhost` apunta al propio ESP32: usar la dirección LAN de la PC o la dirección HTTPS del servidor. No se ha expuesto ni cambiado el firewall de tu PC.
7. Para HTTPS cargar la CA correspondiente; el sketch sincroniza la hora antes de enviar. Para pruebas en una LAN privada se puede habilitar explícitamente `ALLOW_PRIVATE_LAN_HTTP`; acepta únicamente direcciones IP privadas. Mantener HTTPS en la web publicada.
8. Activar `SEND_TO_SERVER`, compilar y cargar. Revisar la respuesta HTTP y el diagnóstico del panel. Finalmente pasar el espacio a sensor desde Espacios cuando haya una lectura estable reciente.

Un ESP32 puede necesitar un programa con varios lectores, pero este primer sketch atiende **un JSN por dispositivo**. La distribución de los dos A02 entre tus dos ESP32 se decidirá al verificar el bus y adaptadores. No reutilizar una credencial entre sensores.

El servidor confirma estados tras varias muestras consecutivas y utiliza umbrales separados para ocupado y libre. Un UUID identifica cada muestra: reintentar el mismo paquete no cuenta como una lectura nueva. El sketch descarta una muestra pendiente después de 5 segundos y no acumula lecturas estando desconectado.

## Fuentes

- Soporte oficial Arduino ESP32: https://docs.espressif.com/projects/arduino-esp32/en/latest/
- Límites y variantes ESP32: https://documentation.espressif.com/esp32_datasheet_en.html
- Certificados HTTPS: https://docs.espressif.com/projects/esp-idf/en/release-v5.5/esp32/api-reference/protocols/esp_http_client.html

La serigrafía identifica la versión del JSN, pero no demuestra su modo eléctrico ni sustituye la ficha del proveedor. No cambiar puentes de modo sin verificar esa documentación.
