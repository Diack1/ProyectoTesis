# Propuesta de reconocimiento de placas con YOLO

YOLO localizará la placa en la imagen; una etapa OCR leerá sus caracteres. La detección y la lectura deben evaluarse por separado. Un modelo genérico que detecta vehículos no sustituye un modelo entrenado para placas.

## Flujo propuesto

1. Cámara o video de prueba → fotograma.
2. YOLO con pesos para placas → región y confianza de detección.
3. Recorte de la placa → OCR → texto y confianza de lectura.
4. Comparación de varias imágenes y normalización del texto.
5. Presentar una sugerencia al operador para que confirme/corrija la placa antes de emitir el ticket.

La cámara indica identidad probable; el sensor indica ocupación de un espacio. No conviene asignar un espacio a una placa usando solamente que apareció un vehículo en la cámara de entrada. Se conservará la asignación explícita en el ticket. La lectura de placa tampoco aprobará pagos ni cerrará estadías por sí sola.

## Trabajo antes de entrenar

- Identificar la cámara y comprobar acceso a imágenes/video, resolución, ángulo, iluminación y legibilidad de placas en el lugar real.
- Obtener imágenes de prueba autorizadas, incluyendo motos y autos, día/noche, posiciones reales y casos difíciles.
- Anotar las cajas de las placas; conservar conjuntos distintos de entrenamiento, validación y prueba. Evitar repartir fotogramas casi iguales del mismo video entre conjuntos.
- Evaluar la base instalada (YOLOv9-s 608 + CCT-s v2 global) con fotografías reales y comparar alternativas si falla en las condiciones de la cochera.
- Medir detección, lectura completa correcta, falsos positivos, latencia y necesidad de corrección humana. Reportar resultados reales en la tesis; no asumir porcentajes.

## Alcance actual

Se implementó una prueba local con fotografías en **Reconocer placas**, disponible para administradores y operadores. Incluye detección, recorte, OCR y confirmación/corrección manual. Los pesos preentrenados se descargaron y se cargan localmente en CPU. Ver [instalación, modelos y límites](../vision/README.md).

La confirmación de fotografías sigue validando el resultado de esa prueba en la sesión. Desde el 19/09/2026 se añadió [cámara USB y vigilancia asistida](camara-entrada.md), con consulta de clientes por asociación explícita y enlaces al formulario de ingreso. Se exige revisión manual antes de emitir un ticket. El entrenamiento con imágenes propias, la evaluación en la cochera y la detección de cruces de entrada para operar sin supervisión siguen pendientes.

Referencia primaria: Ultralytics, [YOLO11 for ANPR](https://www.ultralytics.com/blog/using-ultralytics-yolo11-for-automatic-number-plate-recognition).
