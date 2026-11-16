#!/usr/bin/env bash
# Arma un .zip listo para subir al hosting (incluye vendor/ ya instalado),
# útil si tu plan no tiene acceso SSH/Composer (ej. algunos planes de DonWeb).
set -euo pipefail
cd "$(dirname "$0")/.."
composer install --no-dev --optimize-autoloader --no-interaction
rm -f pipo-deploy.zip
zip -rq pipo-deploy.zip . -x '.git/*' 'config/config.php' 'storage/logs/*.log' 'pipo-deploy.zip' '*.DS_Store'
echo "Listo: pipo-deploy.zip"
