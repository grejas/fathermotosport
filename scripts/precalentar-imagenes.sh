#!/bin/bash
# Genera de antemano las versiones WebP más usadas (320, 640 y 960 px) de todas las
# imágenes de productos y banners, para que ningún cliente espere la primera vez.
cd /home/u206357267/domains/fathermotosport.com/api || exit 1
PHP="/opt/alt/php82/usr/bin/php"
nohup nice -n 15 $PHP artisan tinker --execute='
$s = app(App\Services\VariantesDeImagen::class); $n = 0;
foreach (["products","banners"] as $c) {
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(storage_path("app/public/$c"), FilesystemIterator::SKIP_DOTS)) as $f) {
    if (! preg_match("/\.(jpe?g|png|webp)$/i", $f->getFilename())) continue;
    $rel = substr($f->getPathname(), strlen(storage_path("app/public")) + 1);
    foreach ([320, 640, 960] as $w) { if ($s->generar($w, $rel.".webp")) $n++; }
  }
}
echo "Variantes listas: $n\n";
' > storage/logs/precalentar.log 2>&1 &
sleep 50; cat storage/logs/precalentar.log; ls storage/app/public/v/640/products 2>/dev/null | wc -l
