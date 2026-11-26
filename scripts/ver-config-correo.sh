#!/bin/bash
# Solo lectura: desde qué cuenta envía correos la tienda (sin mostrar contraseñas).
set -u
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || exit 1
$PHP artisan tinker --execute='
echo "mailer: ".config("mail.default").PHP_EOL;
echo "host: ".config("mail.mailers.smtp.host").":".config("mail.mailers.smtp.port").PHP_EOL;
echo "usuario: ".config("mail.mailers.smtp.username").PHP_EOL;
echo "from: ".config("mail.from.name")." <".config("mail.from.address").">".PHP_EOL;
'
