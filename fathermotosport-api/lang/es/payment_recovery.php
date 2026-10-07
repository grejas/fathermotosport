<?php

// Correo de recuperación de pago (PaymentRecoveryMail). Mismo texto sea cual sea la
// causa: no se le dice al cliente si fue saldo insuficiente u otra cosa.
return [
    'subject' => 'No pudimos recibir tu pago en FatherMotoSport',
    'badge' => 'Pago pendiente',
    'greeting' => 'Hola',
    'title' => 'Tu pedido :number está esperando el pago',
    'message' => 'No pudimos recibir tu pago. Recuerda que puedes pagar con PayPal o con tarjeta de crédito o débito: tienes más de una opción.',
    'summary' => 'Resumen de tu pedido',
    'size' => 'Talla',
    'quantity' => 'Cantidad',
    'preheader' => 'Tu pedido sigue disponible: puedes pagarlo con PayPal o con tarjeta.',
    'product' => 'Producto',
    'subtotal' => 'Subtotal',
    'discount' => 'Descuento',
    'shipping' => 'Envío',
    'total' => 'Total',
    'button' => 'Completar mi pago',
    'contact' => '¿Tienes dudas? Escríbenos a :email.',
    'single_notice' => 'Este es un único aviso sobre este pedido.',
];
