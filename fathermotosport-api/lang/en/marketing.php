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
];
