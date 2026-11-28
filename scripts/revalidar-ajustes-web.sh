#!/bin/bash
# Pide a la web (Vercel) que vuelva a leer store/settings.
cd /home/u206357267/domains/fathermotosport.com/api || exit 1
/opt/alt/php82/usr/bin/php artisan tinker --execute='echo "revalidate: ".(app(App\Services\FrontendRevalidator::class)->revalidate(App\Models\StoreConfig::REVALIDATE_TAG) ? "OK" : "FALLO");'
