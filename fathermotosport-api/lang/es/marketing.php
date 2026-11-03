<?php

// Correos promocionales (plantilla emails/layouts/promocional). Los transaccionales no
// usan estos textos ni llevan enlace de baja.
return [
    'footer_reason' => 'Recibes este correo porque tienes una cuenta en FatherMotoSport.',
    'unsubscribe' => 'Darte de baja de las promociones',
    'visit_store' => 'Ver tienda',

    // Correo de cumpleaños (CorreoCumpleanos).
    'birthday' => [
        'subject' => '¡Feliz cumpleaños, :name! Tienes un regalo de FatherMotoSport',
        'subject_plain' => '¡Feliz cumpleaños! Tienes un regalo de FatherMotoSport',
        'preheader' => 'Celebramos contigo con un cupón de :percent de descuento.',
        'greeting' => '¡Feliz cumpleaños, :name!',
        'greeting_plain' => '¡Feliz cumpleaños!',
        'wish' => 'Te deseamos que este nuevo año te vaya todo mejor, en la ruta y fuera de ella.',
        'celebrate' => 'En FatherMotoSport celebramos contigo y queremos regalarte un cupón de descuento.',
        'coupon_label' => 'Tu cupón de cumpleaños',
        'discount' => ':percent de descuento en tu próxima compra',
        'expires' => 'Válido hasta el :date.',
        'button' => 'Quiero reclamar mi descuento',
        'personal' => 'Es personal y de un solo uso: úsalo con la sesión iniciada en tu cuenta.',
        'code_hint' => 'Si el botón no abre, escribe el código en el carrito.',
    ],

    // Aviso de descuento de categoría (CorreoAvisoDescuento). No es un cupón: el
    // descuento ya está aplicado en los precios de la web. Urgencia solo si hay fecha
    // de fin real: sin ella no se dice "por tiempo limitado".
    'category_discount' => [
        'subject_until' => ':category con :percent menos: solo hasta el :date',
        'subject' => ':category con :percent menos en FatherMotoSport',
        'preheader' => 'El descuento ya está aplicado en el precio, sin códigos: elige lo tuyo y paga menos.',
        'headline' => '-:percent en :category',
        'subtitle' => 'Protección y calidad para cada salida. El descuento ya está aplicado en el precio: no necesitas ningún código.',
        'valid_until' => 'Válido hasta el :date.',
        'except' => 'No incluye algunos productos.',
        'button' => 'Ver las ofertas en :category',
        'featured' => 'Destacados con descuento',
        'before' => 'Antes',
        'now' => 'Ahora',
        'view_product' => 'Ver producto',
        'store_link' => 'O mira toda la tienda',
        'secure_payment' => 'Pago seguro',
        'contact' => 'Escríbenos a :email',
    ],
];
