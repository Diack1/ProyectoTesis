# Plano y tarifas — 26/10/2026

Plano compartido público/administración: 41 espacios. T01–T10 a la derecha, de abajo hacia arriba; T11–T16 al fondo, de derecha a izquierda; T17–T23 a la izquierda; T24–T26 junto a la oficina, alineados con su borde superior. La zona interior se ordena por columnas: T24–T33 y T34–T41, de arriba hacia abajo. Los 15 añadidos T27–T41 usan control manual del personal y quedan fuera del estudio con sensores; se conservan sus estados y registros históricos. T significa techo. Los 15 centrales son rectángulos horizontales con las mismas dimensiones que los de oficina y morados cuando están libres. Reservado, ocupado y no disponible mantienen su estado. En móvil el plano conserva las zonas y permite desplazamiento horizontal.

## Aplicación en Railway

Subir el código y realizar el build habitual (`composer install` y `npm run build`). Ejecutar una sola vez mediante las migraciones de despliegue:

```sh
php artisan migrate --force
```

La primera migración renombra los códigos E a T. La tercera aplica el recorrido final conservando IDs, estados, sensores, reservas y tickets, incluidos los siete originales E21–E27 de la izquierda y los tres E28–E30 junto a la oficina. Los cuatro espacios sobrantes de arriba/derecha se reubican en el centro sin borrar registros. Completa el plano hasta 41. Las migraciones deben ejecutarse en orden una sola vez mediante Artisan.

Para auto y camioneta, activa S/ 5 por hora o fracción, mínimo una hora. La segunda migración establece 5 minutos de tolerancia de exceso. Desactiva las tarifas anteriores de esos dos tipos (incluidas nocturnas/diarias), pero conserva los precios capturados en reservas y estadías ya creadas. La tarifa de moto no cambia. Si falta Camioneta, la crea y la permite en los espacios que admiten Auto.

Para una reserva de una hora (S/ 5): hasta 65 minutos no hay exceso; a los 65 minutos y un segundo se agregan S/ 5. La tolerancia de llegada es independiente: las nuevas reservas con QR tienen 15 minutos desde su creación para llegar y pagar.

No ejecutar `migrate:fresh` ni reseed de producción. El rollback no elimina espacios utilizados ni intenta reconstruir precios anteriores desconocidos.

La quinta migración unifica E01/T1 como T01 (y los demás códigos), crea los espacios centrales faltantes, activa T27–T41 y les asigna los tipos de vehículo activos. Conserva estados ocupado, reservado y mantenimiento; nunca simula sensores. Ejecutar php artisan migrate --force tras subir los cambios.
