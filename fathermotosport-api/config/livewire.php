<?php

/*
|--------------------------------------------------------------------------
| Livewire: solo la subida temporal de archivos
|--------------------------------------------------------------------------
|
| El resto de las opciones sale de los valores por defecto del paquete
| (mergeConfigFrom une por clave de primer nivel: esta reemplaza entera a
| 'temporary_file_upload', por eso están todas sus claves).
|
| Por qué existe: los banners de video llegan a 150 MB y Livewire sube cada archivo
| en UNA petición, con un tope de fábrica de 12 MB y 5 minutos. Cada campo de
| Filament sigue validando lo suyo al guardar (tipo y tamaño): una imagen de
| producto no pasa a aceptar 150 MB por esto.
|
| En el servidor hace falta además que PHP acepte la petición: upload_max_filesize
| y post_max_size por encima de 150 MB, y max_input_time para conexiones lentas
| (hPanel → Avanzado → Configuración de PHP).
|
*/

return [

    'temporary_file_upload' => [
        'disk' => null,
        // 153600 KB = 150 MB, el tope de BannerResource::MAX_VIDEO_KB.
        'rules' => ['required', 'file', 'max:153600'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        // Minutos que vale la URL firmada de la subida: 150 MB por una conexión lenta
        // (2 Mbps) tardan unos 10 minutos.
        'max_upload_time' => 30,
        'cleanup' => true,
    ],

];
