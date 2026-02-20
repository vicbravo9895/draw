#!/usr/bin/env bash
# Valida que Reverb (WebSockets) y Horizon estén activos.
# Uso con Sail: ./vendor/bin/sail bash scripts/validate-realtime.sh

set -e

# Cargar REVERB_* desde .env si existe (útil dentro de Sail)
if [ -f .env ]; then
    export REVERB_HOST="${REVERB_HOST:-$(grep -E '^REVERB_HOST=' .env 2>/dev/null | cut -d= -f2- | tr -d '"' | tr -d "'" | xargs)}"
    export REVERB_PORT="${REVERB_PORT:-$(grep -E '^REVERB_PORT=' .env 2>/dev/null | cut -d= -f2- | tr -d '"' | tr -d "'" | xargs)}"
fi
REVERB_HOST="${REVERB_HOST:-localhost}"
REVERB_PORT="${REVERB_PORT:-8080}"

echo "=== Validación Reverb + Horizon ==="
echo ""

# 1. Reverb (puerto 8080) — comprobar con PHP para portabilidad
echo -n "Reverb (${REVERB_HOST}:${REVERB_PORT}) ... "
if php -r "\$f=@fsockopen('${REVERB_HOST}',${REVERB_PORT},\$e,\$s,2); echo \$f?'ok':'fail'; if(\$f)fclose(\$f);" 2>/dev/null | grep -q ok; then
    echo "OK (socket abierto)"
else
    echo "FALLO (no hay servicio en el puerto)"
    exit 1
fi

# 2. Horizon
echo -n "Horizon (queue worker) ... "
if php artisan horizon:status 2>/dev/null | grep -q "running"; then
    echo "OK (en ejecución)"
else
    echo "FALLO (Horizon no está corriendo. Ejecuta: php artisan horizon)"
    exit 1
fi

echo ""
echo "Reverb y Horizon están activos."
