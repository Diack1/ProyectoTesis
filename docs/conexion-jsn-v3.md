# Primera prueba: JSN-SR04T V3.0 con ESP32

## Qué identificamos en tus fotos

- ESP32 DEVKIT V1 TYPE-C, 30 pines. Mirando el frente con USB abajo, D19 y D18 están en la hilera derecha.
- JSN-SR04T-V3.0: seguir las etiquetas `5V`, `Trig`, `Echo`, `GND` de la placa, no una posición recordada de otra foto. Al dar vuelta la placa se invierte la orientación.
- Las dos sondas A02 no muestran una etiqueta legible del modelo. Su conexión queda pendiente del proveedor o manual; no conectarlas directamente a estos pines.

## Material y conexión propuesta

Necesitamos cable USB-C de datos, cables Dupont, protoboard y adaptación de ECHO para lógica de 3,3 V. Conviene disponer de multímetro para comprobar la alimentación. Confirmar primero qué componentes tienes.

| JSN | Destino | Condición |
| --- | --- | --- |
| Trig | D18 / GPIO18 del ESP32 | Señal de disparo del programa |
| Echo | Adaptación de nivel → D19 / GPIO19 | Nunca conectar directamente una salida de 5 V |
| GND | GND del ESP32 y del suministro del sensor | Masa común |
| 5V | Alimentación regulada de 5 V | Verificar la fuente y polaridad antes de alimentar |

El ESP32 se alimentará por USB. No asumir que VIN entrega 5 V al conectar USB: la placa es una variante comercial y hay que comprobar su circuito o medirlo. Si se utiliza una fuente separada para el sensor, unir las masas; no unir su positivo al VIN del ESP32 mientras este está alimentado por USB.

**Ejemplo de adaptación, únicamente si ECHO entrega lógica de 5 V:** divisor de resistencias de 10 kΩ y 15 kΩ, preferiblemente 1 %. La salida nominal es 3,0 V: `5 × 15 / (10 + 15)`. Si ECHO resulta ser de 3,3 V, este divisor reduciría demasiado la señal y hay que escoger otra adaptación. Un multímetro corriente puede mostrar un promedio de los pulsos, no su nivel alto: para ese dato usar la ficha del proveedor o una medición adecuada con osciloscopio.

```text
JSN Echo ─── [10 kΩ] ───┬─── D19 / GPIO19
                       │
                    [15 kΩ]
                       │
GND común ──────────────┘

ESP32 D18 ───────────────── JSN Trig
ESP32 GND ───────────────── JSN GND
5 V regulados ────────────── JSN 5V
```

Montar con la alimentación desconectada. El conector blanco de dos contactos del JSN corresponde a su sonda; no es un adaptador RS485. No modificar los puentes MODE ni el ajuste de la bobina para esta primera prueba. Las fotos no confirman por sí solas el modo activo.

## Arduino IDE: primera carga

1. Instalar el paquete **esp32 by Espressif Systems** desde el gestor de placas. Si hace falta, añadir en Preferencias la URL oficial: `https://espressif.github.io/arduino-esp32/package_esp32_index.json`.
2. Seleccionar el perfil para ESP32 clásico, por ejemplo **ESP32 Dev Module**, y el puerto COM de la placa. No seleccionar ESP32-S3/C3 basándose en que tiene USB-C. La capacidad de memoria y los detalles del módulo se verificarán con la detección de la placa.
3. Abrir `firmware/parkeo_esp32/parkeo_esp32.ino`. Copiar `config.example.h` como `config.h` dentro de esa misma carpeta.
4. Compilar inicialmente con `HARDWARE_CONFIRMED = false`. Abrir el monitor serie a **115200 baudios**: debe mostrar el aviso de configuración pendiente. Si el aviso se perdió al abrir el monitor, pulsar EN para reiniciar.
5. Tras verificar cableado, alimentación y modo TRIG/ECHO, cambiar solo `HARDWARE_CONFIRMED = true`. Mantener **`SEND_TO_SERVER = false`** y las credenciales vacías. Cargar de nuevo.
6. Colocar una superficie plana frente a la sonda y comparar con una cinta métrica, por ejemplo a 50, 100 y 150 cm. Registrar al menos 10 muestras por distancia. El programa toma aproximadamente una muestra por segundo y muestra centímetros o `Sin eco válido`.

Si no aparecen lecturas, revisar primero masa, alimentación, pines, adaptación de ECHO y modo del sensor. No puentear la adaptación para intentar que funcione. Una falta de eco no demuestra que el espacio esté libre. Las distancias mostradas son mediciones aproximadas; el rango útil y la calibración se comprobarán físicamente.

## Después: conectar con la cochera

Mantener el espacio en control manual mientras se mide en la instalación real. Registrar distancia con espacio vacío y con vehículo. Configurar en **Sensores IoT** los límites válidos y los umbrales ocupado/libre a partir de esas medidas; no usar los valores de la mesa como calibración definitiva.

Generar la credencial de ese sensor en el panel y completar `config.h` según [firmware/README.md](../firmware/README.md). Activar el envío y comprobar varias lecturas estables en el panel antes de cambiar el espacio a control por sensor. No pegar contraseñas ni tokens en capturas o en el repositorio.

La prueba física, compilación para la placa y calibración siguen pendientes. Esta entrega prepara el programa y el procedimiento; no acredita que los sensores estén conectados ni que la cochera ya reciba sus mediciones reales.

## Referencias

- [Instalación oficial de Arduino ESP32](https://docs.espressif.com/projects/arduino-esp32/en/latest/installing.html).
- [Ficha eléctrica oficial del ESP32](https://documentation.espressif.com/esp32_datasheet_en.html): los GPIO trabajan con lógica de 3,3 V; no son entradas directas de 5 V.
- Identificación y etiquetas: fotografías aportadas por el usuario el 13/09/2026. Falta la ficha del proveedor del sensor concreto para confirmar sus características eléctricas y modos.
