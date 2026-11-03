<?php

// E-mails promocionais (layout emails/layouts/promocional).
return [
    'footer_reason' => 'Você recebe este e-mail porque tem uma conta na FatherMotoSport.',
    'unsubscribe' => 'Cancelar o recebimento de promoções',
    'visit_store' => 'Ver loja',

    // E-mail de aniversário (CorreoCumpleanos).
    'birthday' => [
        'subject' => 'Feliz aniversário, :name! Você tem um presente da FatherMotoSport',
        'subject_plain' => 'Feliz aniversário! Você tem um presente da FatherMotoSport',
        'preheader' => 'Comemoramos com você com um cupom de :percent de desconto.',
        'greeting' => 'Feliz aniversário, :name!',
        'greeting_plain' => 'Feliz aniversário!',
        'wish' => 'Desejamos que este novo ano seja ainda melhor para você, na estrada e fora dela.',
        'celebrate' => 'A FatherMotoSport comemora com você e quer te dar de presente um cupom de desconto.',
        'coupon_label' => 'Seu cupom de aniversário',
        'discount' => ':percent de desconto na sua próxima compra',
        'expires' => 'Válido até :date.',
        'button' => 'Quero resgatar meu desconto',
        'personal' => 'É pessoal e de uso único: use-o com a sessão iniciada na sua conta.',
        'code_hint' => 'Se o botão não abrir, digite o código no carrinho.',
    ],

    // Aviso de desconto de categoria (CorreoAvisoDescuento). Não é um cupom.
    'category_discount' => [
        'subject_until' => ':category com :percent de desconto: só até :date',
        'subject' => ':category com :percent de desconto na FatherMotoSport',
        'preheader' => 'O desconto já está aplicado no preço, sem códigos: escolha o seu e pague menos.',
        'headline' => '-:percent em :category',
        'subtitle' => 'Proteção e qualidade para cada saída. O desconto já está aplicado no preço: você não precisa de nenhum código.',
        'valid_until' => 'Válido até :date.',
        'except' => 'Não inclui alguns produtos.',
        'button' => 'Ver as ofertas em :category',
        'featured' => 'Destaques com desconto',
        'before' => 'Antes',
        'now' => 'Agora',
        'view_product' => 'Ver produto',
        'store_link' => 'Ou veja a loja completa',
        'secure_payment' => 'Pagamento seguro',
        'contact' => 'Fale conosco em :email',
    ],
];
