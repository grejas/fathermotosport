<?php

// Promotional emails (emails/layouts/promocional layout).
return [
    'footer_reason' => 'You are receiving this email because you have a FatherMotoSport account.',
    'unsubscribe' => 'Unsubscribe from promotions',
    'visit_store' => 'Visit store',

    // Birthday email (CorreoCumpleanos).
    'birthday' => [
        'subject' => 'Happy birthday, :name! You have a gift from FatherMotoSport',
        'subject_plain' => 'Happy birthday! You have a gift from FatherMotoSport',
        'preheader' => 'We are celebrating with you with a :percent discount coupon.',
        'greeting' => 'Happy birthday, :name!',
        'greeting_plain' => 'Happy birthday!',
        'wish' => 'We hope this new year brings you all the best, on the road and off it.',
        'celebrate' => 'FatherMotoSport is celebrating with you and wants to give you a discount coupon.',
        'coupon_label' => 'Your birthday coupon',
        'discount' => ':percent off your next purchase',
        'expires' => 'Valid until :date.',
        'button' => 'Claim my discount',
        'personal' => 'It is personal and single-use: use it while logged in to your account.',
        'code_hint' => 'If the button does not open, enter the code in the cart.',
    ],

    // Category discount notice (CorreoAvisoDescuento). Not a coupon.
    'category_discount' => [
        'subject_until' => ':percent off :category: only until :date',
        'subject' => ':percent off :category at FatherMotoSport',
        'preheader' => 'The discount is already in the price, no codes: pick yours and pay less.',
        'headline' => ':percent off :category',
        'subtitle' => 'Protection and quality for every ride. The discount is already applied to the price: no code needed.',
        'valid_until' => 'Valid until :date.',
        'except' => 'Some products are not included.',
        'button' => 'See the deals on :category',
        'featured' => 'Featured deals',
        'before' => 'Was',
        'now' => 'Now',
        'view_product' => 'View product',
        'store_link' => 'Or browse the whole store',
        'secure_payment' => 'Secure payment',
        'contact' => 'Write to us at :email',
    ],

    'popup_coupon' => [
        'footer_reason' => 'You are receiving this email because you requested your discount coupon at fathermotosport.com.',
        'bienvenida_subject' => 'Your :percent coupon is ready: :code',
        'bienvenida_preheader' => 'Save it: it works on your first FatherMotoSport order.',
        'bienvenida_title' => 'You got :percent off!',
        'bienvenida_text' => 'Thanks for joining FatherMotoSport. Here is your coupon for motorcycle helmets, gloves, boots and jackets. Use it on your first order.',
        'bienvenida_button' => 'Use my coupon',
        'recordatorio_subject' => 'Your :percent coupon expires soon',
        'recordatorio_preheader' => 'You can still use :code before it expires.',
        'recordatorio_title' => 'Your :percent off is about to expire',
        'recordatorio_text' => 'Your coupon has not been used yet. If you were unsure about a size or shipping, message us on WhatsApp and we will help before it expires.',
        'recordatorio_button' => 'Claim my discount',
        'coupon_label' => 'Your coupon',
        'coupon_off' => ':percent off',
        'expires' => 'Valid until :date (Bolivia time)',
        'hint' => 'The button saves the coupon in your cart. You can also copy the code and paste it at checkout.',
        'benefit_shipping' => 'Free standard shipping',
        'benefit_size' => 'Sizing help on WhatsApp',
        'benefit_secure' => 'Secure checkout with card or PayPal',
    ],
];
