<?php
// Pone en el banner hero (id 6) el VSL de Black Friday aprobado por Gerson. Se ejecuta una vez y se borra.
$base = '/home/u206357267/domains/fathermotosport.com/api';
$pub = $base . '/storage/app/public/banners';
$src = 'https://raw.githubusercontent.com/grejas/fathermotosport/tmp-vsl-bf2/';
foreach (['videos/vsl-bf2-720.mp4' => 'vsl-bf2-720.mp4', 'videos/vsl-bf2-480.mp4' => 'vsl-bf2-480.mp4', 'posters/vsl-bf2-poster.jpg' => 'vsl-bf2-poster.jpg'] as $dst => $file) {
    if (!is_file("$pub/$dst") || filesize("$pub/$dst") < 1000) {
        copy($src . $file, "$pub/$dst");
    }
}
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$b = App\Models\Banner::find(6);
$b->update([
    'video_path' => 'banners/videos/vsl-bf2-720.mp4',
    'video_mobile_path' => 'banners/videos/vsl-bf2-480.mp4',
    'poster_path' => 'banners/posters/vsl-bf2-poster.jpg',
]);
echo json_encode($b->fresh()->only(['video_path', 'video_mobile_path', 'poster_path', 'button_text', 'link_url', 'video_with_sound']));
