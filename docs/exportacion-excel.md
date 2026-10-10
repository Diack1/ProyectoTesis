# Exportación de reportes

El botón principal descarga XLSX con estilos, fechas nativas, filtros y encabezados fijos. CSV sigue disponible y declara `sep=;` para Excel. Los dos formatos utilizan los mismos filtros que la pantalla y requieren superadministrador.

## Despliegue

Subir también `composer.json` y `composer.lock`. Railpack instala las extensiones declaradas `ext-zip`, `ext-dom` y `ext-xmlreader` y ejecuta Composer durante el build. No se requiere una migración ni cambiar credenciales.

En instalaciones manuales: habilitar esas extensiones y ejecutar `composer install --no-dev --optimize-autoloader`, seguido de `php artisan optimize:clear`.

En el PHP local de XAMPP, ZIP estaba deshabilitado. Para probar sin modificar el php.ini global:

```powershell
C:/xampp/php/php.exe -d extension=zip vendor/phpunit/phpunit/phpunit --filter='ReporteExcelTest|ReceptionNavigationTest'
```

Para utilizar la descarga desde el servidor web local, habilitar `extension=zip` en el php.ini de ese servidor y reiniciarlo.

## Fallos externos de build

`HEAD ... registry-1.docker.io/v2/library/composer/manifests/latest: 429 Too Many Requests` indica que Docker Hub rechazó temporalmente la descarga de la imagen de Composer. Reintentar con una pausa; si persiste, compartir ese build con soporte Railway. No implica que haya fallado una consulta o la generación del Excel.

Referencias: https://railpack.com/languages/php/ y https://docs.docker.com/docker-hub/troubleshoot/
