# Reconocimiento local de placas

Actualización 19/09/2026: se añadió captura con cámara USB, vigilancia asistida y consulta de clientes por matrícula. Ver [guía de cámara de entrada](../docs/camara-entrada.md). La subida de fotografías descrita abajo sigue disponible como prueba independiente.

## Uso desde la página

Entrar como administrador u operador y abrir **Reconocer placas** (`/admin/placas`). Subir una fotografía JPG, PNG o WebP de hasta 8 MB, 20 megapíxeles y 6000 píxeles por lado. El resultado muestra las detecciones numeradas, sus recortes y una sugerencia de texto por placa.

Comparar cada carácter, corregirlo y marcar la revisión antes de confirmar. La confirmación pertenece únicamente a la prueba de esa fotografía; no genera ingresos, tickets, pagos ni asignaciones de espacio. Si no detecta una placa, acercar la cámara o mejorar el encuadre y continuar usando el registro manual.

## Modelos elegidos

- Detector: **YOLOv9-s, entrada 608**, entrenado para placas, publicado por Open Image Models. Se eligió como base para fotografías por su mayor capacidad que la variante tiny; no se afirma que sea el mejor modelo para una cámara todavía no evaluada.
- OCR: **CCT-s v2 global**, de FastPlateOCR. Lee el recorte; no se entrenó un modelo propio en esta entrega.
- Ejecución: ONNX Runtime en CPU, dos hilos de inferencia. No requiere GPU ni envía fotos a un servicio de IA externo. Las descargas ocurren durante la preparación, no al analizar imágenes.
- `model-profile.json` registra nombres, procedencia y SHA-256 de los pesos utilizados. `requirements-lock.txt` registra las versiones instaladas en Windows/Python 3.12.

Una puntuación alta no equivale a exactitud verificada. Tampoco se usa una supuesta nacionalidad de la placa como decisión. No se reemplazan automáticamente O por 0 ni I por 1. Se acepta la corrección de 5 a 10 caracteres alfanuméricos sin forzar un único formato regional.

## Instalación reproducible

En PowerShell, desde la raíz del proyecto, con Python 3.12 disponible:

```powershell
python -m venv vision/.venv
& ./vision/.venv/Scripts/python.exe -m pip install -r vision/requirements-lock.txt
& ./vision/.venv/Scripts/python.exe vision/prepare_models.py
& ./vision/.venv/Scripts/python.exe vision/recognize.py --check
```

El último comando debe devolver `ready: true`. La preparación descarga cerca de 34 MB de modelos a `vision/models`; la carpeta está excluida de Git. La primera instalación de dependencias necesita más espacio. No cargar archivos `.pt` o scripts de procedencia desconocida en este flujo.

Laravel usa automáticamente `vision/.venv/Scripts/python.exe` en Windows y `vision/.venv/bin/python` en Linux. `VISION_PYTHON` permite configurar otra ruta absoluta si el despliegue lo requiere. El usuario del servidor debe poder ejecutar Python y leer los modelos. El directorio público del sitio debe ser exclusivamente `public`, como corresponde a Laravel.

En Windows, `artisan serve` puede eliminar variables que Python necesita. En ese caso definir en `.env` **los valores reales de ese equipo** para `VISION_SYSTEM_ROOT` y `VISION_USER_PROFILE` (consultarlos en PowerShell con `$env:SystemRoot` y `$env:USERPROFILE`). Se configuraron para esta PC. Ejecutar `php artisan config:clear` después de cambiarlos. No copiar la ruta personal de otro equipo.

## Datos y límites de la prueba

- El original se guarda temporalmente en almacenamiento privado y se elimina al terminar el proceso, incluso si falla. No se añade a ningún dataset.
- Vista reducida, recortes y texto permanecen en la sesión del operador. **Borrar prueba** los retira; cargar otra imagen los sustituye. A los 30 minutos el resultado deja de estar disponible al consultarlo. La eliminación física de sesiones antiguas sigue la política de sesiones de Laravel.
- Se conserva la orientación EXIF, pero la vista JPEG generada no conserva los metadatos originales.
- Un análisis a la vez, hasta 10 detecciones por foto, con límite de 25 segundos. Esta versión inicia el motor por fotografía; la cámara en vivo requerirá un proceso persistente y otra estrategia de captura.
- Una terminación abrupta del servidor podría dejar un original temporal; revisar `storage/app/private/vision-temporal` al administrar el servicio. No se expone mediante rutas de descarga.

## Verificación

```powershell
php artisan test --compact --filter=PlacaVisionTest
& ./vision/.venv/Scripts/python.exe -m unittest discover -s vision -p 'test_*.py'
& ./vision/.venv/Scripts/python.exe vision/recognize.py --image ruta/a/fotografia.jpg
```

Las pruebas automatizadas cubren permisos, carga inválida, limpieza ante fallos, confirmación y caducidad, ausencia de efectos sobre reservas/pagos/estadías, ausencia de detecciones, recortes fuera de límites, orientación y lectura incierta. Son distintas de una evaluación de precisión del modelo.

La prueba de inferencia con [la imagen de ejemplo de FastALPR](https://github.com/ankandrew/fast-alpr/blob/master/assets/test_image.png) produjo una lectura principal `5AU5341` y una detección diminuta con lectura dudosa `44A4`. La inferencia directa tardó aproximadamente un segundo en esta PC. Ese ejemplo sirve para comprobar el funcionamiento, no para estimar rendimiento con placas peruanas ni con la cámara de la cochera. La imagen descargada queda en `vision/data`, fuera de Git.

## Fuentes de los autores

- [Open Image Models: modelos YOLOv9 para placas](https://github.com/ankandrew/open-image-models).
- [FastPlateOCR](https://github.com/ankandrew/fast-plate-ocr).
- [FastALPR: integración de detector y OCR](https://github.com/ankandrew/fast-alpr).

Estos proyectos publican su código con licencia MIT. Conservar sus avisos al distribuir dependencias; la licencia del código no acredita por sí sola derechos sobre todas las fotografías de entrenamiento. Los enlaces exactos a los pesos están registrados en `model-profile.json`.
