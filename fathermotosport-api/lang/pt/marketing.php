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

    'popup_coupon' => [
        'footer_reason' => 'Você recebe este e-mail porque pediu seu cupom de desconto em fathermotosport.com.',
        'bienvenida_subject' => 'Seu cupom de :percent está pronto: :code',
        'bienvenida_preheader' => 'Guarde-o: vale para sua primeira compra na FatherMotoSport.',
        'bienvenida_title' => 'Você ganhou :percent de desconto!',
        'bienvenida_text' => 'Obrigado por se juntar à FatherMotoSport. Este é seu cupom para capacetes, luvas, botas e jaquetas de moto. Use na sua primeira compra.',
        'bienvenida_button' => 'Usar meu cupom',
        'recordatorio_subject' => 'Seu cupom de :percent vence em breve',
        'recordatorio_preheader' => 'Você ainda pode usar :code antes que vença.',
        'recordatorio_title' => 'Seu desconto de :percent está quase vencendo',
        'recordatorio_text' => 'Seu cupom ainda não foi usado. Se ficou em dúvida sobre tamanho ou frete, fale com a gente no WhatsApp e ajudamos antes que vença.',
        'recordatorio_button' => 'Aproveitar meu desconto',
        'coupon_label' => 'Seu cupom',
        'coupon_off' => ':percent de desconto',
        'expires' => 'Válido até :date (horário da Bolívia)',
        'hint' => 'O botão salva o cupom no seu carrinho. Você também pode copiar o código e colar no pagamento.',
        'benefit_shipping' => 'Frete padrão grátis',
        'benefit_size' => 'Ajuda com tamanho pelo WhatsApp',
        'benefit_secure' => 'Pagamento seguro com cartão ou PayPal',
    ],
];
