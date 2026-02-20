#!/bin/sh
set -e

# Generar APP_KEY solo para esta ejecución si no está definido (para pruebas rápidas)
if [ -z "$APP_KEY" ]; then
  echo "APP_KEY no definido, generando una temporal..."
  export APP_KEY=$(php artisan key:generate --show)
fi

# Esperar a que PostgreSQL esté listo
until php artisan db:show 2>/dev/null; do
  echo "Esperando base de datos..."
  sleep 2
done

echo "Ejecutando migraciones..."
php artisan migrate --force

echo "Ejecutando seeder de producción (inspector + admin)..."
php artisan db:seed --class=ProductionSeeder --force

echo "Iniciando servidor en el puerto 8000"
exec php artisan serve --host=0.0.0.0 --port=8000
