<?php
// Actualiza el banner hero (id 6) con el VSL nuevo. Se ejecuta una vez y se borra.
$base = '/home/u206357267/domains/fathermotosport.com/api';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$b = App\Models\Banner::find(6);
$b->update([
    'video_path' => 'banners/videos/vsl-portada-720.mp4',
    'video_mobile_path' => 'banners/videos/vsl-portada-480.mp4',
    'poster_path' => 'banners/posters/vsl-portada-poster.jpg',
    'button_text' => 'Ver ofertas Black Friday',
    'link_url' => '/black-friday?utm_source=web&utm_medium=vsl_home&utm_campaign=black_friday_2026',
]);
echo json_encode($b->fresh()->only(['video_path', 'video_mobile_path', 'poster_path', 'button_text', 'link_url', 'video_with_sound', 'media_type']));
