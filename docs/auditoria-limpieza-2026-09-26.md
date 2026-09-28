# Revisión de limpieza y rendimiento — 26/09/2026

## Alcance
Revisión de rutas, controladores, servicios, modelos, vistas, recursos públicos, dependencias, tareas programadas y pruebas. No se borraron datos de clientes, pagos, archivos privados, modelos YOLO ni dependencias instaladas. No se hizo commit ni se publicó el proyecto.

## Eliminado con referencias verificadas
- `public/css/parkeo.css`: diseño descartado; ningún layout lo carga (el abandono ya estaba documentado en segundo-bloque-operacion.md).
- `resources/views/welcome.blade.php`: bienvenida original sin ruta.
- `resources/views/dashboard.blade.php`: dashboard original sin renderizador; la ruta /dashboard redirige por rol.
- `resources/views/partials/nav-user.blade.php`: menú antiguo sin includes.
- `tests/Unit/ExampleTest.php`: comprobaba únicamente true === true. Retirada también su suite vacía de phpunit.xml.
- `.docx-review`: carpeta vacía de revisión temporal.
- Métodos privados `espacioBaseDisponible` y `mensajeBloqueo`: sin llamadas en ReservaDisponibilidadService.

Los cinco archivos retirados ocupaban 131299 bytes aproximadamente. Este ahorro de disco no es una medición de velocidad.

## Mejora real aplicada
El accessor estado_actual consultaba estadías y reservas en cada lectura. La portada lo invoca al contar cada estado y al dibujar tarjetas. Se añadió conEstadoOperativo(), que incorpora indicadores EXISTS en la consulta de espacios. Portada, plano/actualizaciones y dashboard lo usan. El resto conserva las consultas originales para no reutilizar estados precargados durante operaciones de escritura.

El dashboard precarga además pagos de reservas y reserva de las estadías, utilizados en los detalles del plano. La portada precarga sensores. No se almacenó disponibilidad en caché ni se relajaron verificaciones al reservar.

Prueba añadida: consultar cinco veces el estado de un espacio con indicadores precargados no ejecuta consultas adicionales y conserva el resultado de la lectura normal.

## Conservado por uso comprobado
- vendor, node_modules, archivos lock y configuración Vite: dependencias reproducibles; perfil y pantallas de autenticación todavía usan componentes del scaffold.
- resources/views/layouts/app y guest, componentes Blade y resources/js: referencias activas; no son automáticamente basura de Breeze.
- vision/.venv, models, data y firmware: soporte de placas y sensores; no se considera basura el material de entrenamiento o modelos por su tamaño.
- migraciones, tests funcionales, documentación y respaldos privados.
- hojas CSS compartidas: contienen reglas de diferentes módulos. No se borran con una búsqueda simple de nombres porque hay clases dinámicas y estados generados por JavaScript.

## Riesgos y siguientes pasos de despliegue
- app/services usa carpeta minúscula con namespace App\Services. Antes de publicar en Linux debe normalizarse la capitalización y verificarse el autoload en ese entorno.
- El servidor de desarrollo y el túnel no equivalen a una instalación de producción. No se midió capacidad concurrente ni latencia del túnel.
- Las tareas automáticas también se ejecutan en peticiones públicas. Migrarlas exclusivamente al scheduler requiere garantizar primero un trabajador permanente; se conservan para no dejar reservas vencidas bloqueando espacios.
- Evaluar índices compuestos con consultas y EXPLAIN de datos representativos, antes de modificar la base real.
- Consolidar CSS requiere comparación visual por módulo; esta revisión no demuestra que toda regla aparentemente antigua esté sin uso.

## Validación
96 pruebas PHP (568 comprobaciones), 3 pruebas JavaScript de seguimiento de placas. La nueva prueba detecta regresiones de consultas por lectura. Sin errores de espacios en git diff --check. No se afirma una mejora porcentual de velocidad ni escalabilidad ilimitada.


## Segunda revisión
- Extendida la precarga del estado operativo a MonitoreoController, EspacioController (listado) y EstadiaController (opciones de ingreso). Las comprobaciones al guardar conservan su flujo original.
- Nueva búsqueda de métodos privados sin referencias: sin candidatos adicionales.
- Validación repetida: 96 pruebas PHP / 568 comprobaciones; 4 pruebas Python de visión sin descargar modelos; vistas Blade compiladas; npm run build completado; diff sin errores de whitespace.
- Advertencia de entorno confirmada por Vite: Node 20.18.0 es inferior al mínimo 20.19 de la rama 20. El build finalizó, pero debe actualizarse Node antes de establecer una compilación de producción soportada. No se cambió la instalación global.
- Esta revisión no sustituye una prueba de carga, una auditoría de seguridad ni una validación sobre Linux. No se eliminaron dependencias basándose solo en su tamaño.
