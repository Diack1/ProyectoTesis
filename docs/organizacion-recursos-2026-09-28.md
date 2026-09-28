# Organización y limpieza — 28/09/2026

## Alcance y criterio

Revisión de layouts, referencias a CSS y JavaScript, rutas/controladores,
servicios, modelos, notificaciones, jobs, dependencias del frontend y estructura
de visión/firmware. No se borraron datos, archivos privados, migraciones,
modelos entrenados ni dependencias por su tamaño. No se hizo commit.

## Cambios comprobados

- Se trasladaron los 15 CSS de public/css a resources/css, agrupados en shared,
  public, admin, auth y pages. El SVG de hexágonos pasó a resources/images.
- Vite compila, minifica y versiona los estilos. Las pantallas principales pasan
  de 9 CSS (público/admin) y 7 (auth) a 2 paquetes por área. Los estilos exclusivos
  de placas y ticket siguen cargándose únicamente en esas pantallas.
- Se conserva el orden antes/después de @stack('styles'): los formularios y sensores
  todavía incluyen estilos particulares. Unir todo ciegamente cambiaría la cascada.
  Los archivos base.css/theme.css son puntos de entrada, no copias del código.
- Se retiró pagination del acceso, donde no existen listados paginados.
- plano.js se carga desde el componente del plano, una sola vez; deja de cargarse
  en todas las páginas públicas y administrativas. reserva-publica.js se carga solo
  en el formulario de nueva reserva. La portada pasa de 3 scripts externos a 1.
- Eliminados EmailVerificationNotificationController, EmailVerificationPromptController
  y VerifyEmailController: sin referencias en rutas ni código; CustomerVerificationController
  ya maneja la verificación por código. Se mantienen las pruebas de ese flujo.
- Eliminados axios y @tailwindcss/vite de package.json y lockfile. Axios solo se
  inicializaba en bootstrap.js, sin consumidores; se retiraron ese archivo y su import.
  El proyecto utiliza Tailwind 3 mediante PostCSS, no el plugin Vite de Tailwind 4.
  npm retiró 35 paquetes contando dependencias transitivas. Alpine sigue en uso.
- El JS compilado de las pantallas que usan app.js pasó de 88.55 kB a 45.89 kB
  (gzip: 32.75 a 16.52 kB). No es una medición de latencia ni de toda la aplicación.
- Corregido un selector de auth-diagonal que ocultaba también el botón Cerrar sesión
  de la pantalla de límite de intentos: ahora solo oculta el enlace duplicado al inicio.

## Conservado y límites de la revisión

Los controladores restantes y las clases de servicios, modelos, notificaciones y
jobs tienen referencias estáticas. Los ocho scripts públicos tienen consumidores
(plate-tracker.js se importa desde camara-placas.js y se prueba con Node).
Eso demuestra uso, no que cada método o selector sea indispensable.

Se conservan reglas CSS superpuestas de anteriores diseños cuando no hay evidencia
de que retirarlas sea equivalente en todos los estados. La consolidación reduce
solicitudes y organiza fuentes; no pretende haber eliminado todo selector redundante.
Se mantienen vendor/node_modules, vision/.venv, modelos, datos de entrenamiento,
firmware y respaldos: no son basura ni todos se descargan al abrir la web.

app/services permanece en minúscula: composer.json declara explícitamente
App\\Services hacia app/services. No se necesita un cambio de mayúsculas a ciegas.
La compatibilidad final del despliegue debe validarse en el sistema operativo destino.

## Validación

- Compilación Vite correcta; manifiesto y rutas de recursos actualizados.
- 127 pruebas PHP y 782 comprobaciones correctas tras la primera compilación.
- Tras limitar scripts al componente/formulario: 15 pruebas de plano/reservas,
  78 comprobaciones correctas; todas las vistas Blade compilan.
- 3 pruebas JavaScript de seguimiento de placas y 4 pruebas Python de visión correctas.
- Inspección del navegador: portada con dos CSS y un script externo; diseño preservado.
  En ancho móvil 390px funcionan el menú lateral y el detalle del espacio E01;
  sin desbordamiento horizontal del documento. No se crearon reservas reales.
- git diff --check sin errores. .env, claves, vendor, node_modules y modelos de visión
  no aparecen entre los archivos rastreados consultados.

Node local es 20.18.0: Vite advierte que requiere 20.19+ o 22.12+; la compilación
finaliza, pero actualizar Node es necesario para usar una versión soportada.
No se alteró la instalación global. public/build está ignorado: otra copia necesita
npm ci y npm run build, o recibir los artefactos compilados del despliegue.
