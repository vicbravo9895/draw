# Supervisor: Reverb y cola

Para tener **Reverb** (WebSockets) y el **worker de cola** (jobs de inspecciones, broadcasts) siempre activos, puedes usar Supervisor (en el host o dentro de un contenedor).

## Con Laravel Sail (recomendado en desarrollo)

Sail ya incluye los servicios `reverb` y `horizon` en `compose.yaml`. Solo necesitas:

1. En `.env` con Sail: `REVERB_HOST=reverb`, `QUEUE_CONNECTION=redis`, `REDIS_HOST=redis`.
2. Levantar los servicios:
   ```bash
   ./vendor/bin/sail up -d
   ```
   (Reverb y Horizon se inician con el resto de contenedores.)
3. Validar que todo esté activo (con `REVERB_HOST=reverb` en `.env`):
   ```bash
   ./vendor/bin/sail exec laravel.test bash scripts/validate-realtime.sh
   ```

Si algo falla, revisa logs: `./vendor/bin/sail logs reverb` y `./vendor/bin/sail logs horizon`.

## Con Supervisor (host o contenedor sin Sail)

1. Ajusta la ruta en `draw-app.conf`: reemplaza `/var/www/html` por la ruta real de la app (por ejemplo la ruta del proyecto en el servidor o en el contenedor).
2. Copia el `.conf` a la configuración de Supervisor:
   - **Linux (host):** `sudo cp draw-app.conf /etc/supervisor/conf.d/`
   - **Docker:** copia el archivo a la imagen y en el entrypoint arranca `supervisord -c /ruta/draw-app.conf` (o inclúyelo en tu configuración global de supervisor).
3. Recarga Supervisor:
   ```bash
   sudo supervisorctl reread && sudo supervisorctl update
   sudo supervisorctl start draw-app-reverb draw-app-queue
   ```

- **Reverb** (`draw-app-reverb`): mantiene el servidor WebSocket encendido.
- **Cola** (`draw-app-queue`): procesa jobs (envío de eventos de inspección vía Reverb, etc.). Usa esto cuando `QUEUE_CONNECTION=database`.
- **Horizon** (opcional): si usas Redis y Laravel Horizon, comenta `draw-app-queue` y descomenta el bloque `draw-app-horizon`, y ejecuta `php artisan horizon` en su lugar.

Asegúrate de que `storage/logs` exista y sea escribible para los logs de Reverb y de la cola.
