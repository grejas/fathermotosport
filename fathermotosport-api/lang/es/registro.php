<?php

// Mensajes del registro y de "Completa tus datos". El idioma sale de la cabecera
// X-Locale que manda la web (middleware IdiomaDeLaPeticion).
return [
    'phone_required' => 'El teléfono es obligatorio.',
    'phone_invalid' => 'Ingresa el teléfono con el código de país, por ejemplo +591 76543210 (8 a 15 dígitos).',
    'country_required' => 'El país es obligatorio.',
    'country_invalid' => 'Selecciona un país válido.',
    'birth_date_invalid' => 'Ingresa una fecha de nacimiento válida, entre 1926 y hoy.',
    'birth_date_future' => 'La fecha de nacimiento no puede ser futura.',
    'underage' => 'Debes tener 18 años o más para crear una cuenta.',
    'adult_required' => 'Confirma que tienes 18 años o más.',
    'email_no_mx' => 'Ese dominio no recibe correos. Revisa que el correo esté bien escrito.',
    'email_disposable' => 'No aceptamos correos temporales o desechables. Usa tu correo habitual.',
    'code_invalid' => 'El código es inválido o ha expirado.',
    'code_sent' => 'Te enviamos un código de verificación a tu correo.',
    'email_already_verified' => 'Tu correo ya está verificado.',
    'email_verified' => 'Correo verificado.',
    'profile_saved' => 'Datos guardados.',
    'coupon_owner' => 'Este cupón es personal: inicia sesión con la cuenta a la que pertenece.',
    'coupon_used' => 'Este cupón ya se usó.',
];
