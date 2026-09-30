#!/bin/bash
#
# Verifica que los archivos del backend en el servidor coincidan con los del repo.
# Nació de un despliegue en el que faltó subir app/Models/ProductVariant.php y el
# panel tiraba un 500 al guardar variantes.
#
# Uso (desde la raíz del repo):
#   ssh -p PUERTO usuario@host 'bash -s' -- /ruta/al/api < scripts/verificar-despliegue.sh
#
# Imprime FALTA / DIFIERE por archivo y, al final, la lista lista para subir:
#   tar czf - -C fathermotosport-api -T faltantes.txt | ssh ... "tar xzf - -C /ruta/al/api"
#
# Las huellas son acumulativas: cubren el despliegue de tallas/color + envío + PayPal
# (commit a358a2d) MÁS todo lo acumulado hasta 97ee5b8 — 66 archivos. Revalidar lo
# viejo junto con lo nuevo no cuesta nada y atrapa un archivo que se haya quedado
# sin subir en un despliegue anterior.
#
# Para regenerarlas después de otros cambios (incluye el árbol de trabajo, así que
# sirve igual con cambios sin comitear):
#
#   cd fathermotosport-api
#   { git diff --name-only <commit-base>..HEAD -- .
#     cd .. && git status --porcelain -- fathermotosport-api/ | awk '{print $2}'
#   } | sed 's|^fathermotosport-api/||' | sort -u
#
# y rehashear con md5sum, reemplazando el bloque HASHES (formato: "md5 TIPO ruta").
# TIPO: APP = código (obligatorio) · TEST = tests (opcional) · DOC = documentación.
#
API="${1:-.}"
cd "$API" || { echo "No existe la ruta: $API"; exit 1; }
echo "Verificando en: $(pwd)"
echo
faltantes=""
while read -r hash tipo ruta; do
  [ -z "$ruta" ] && continue
  if [ ! -f "$ruta" ]; then
    echo "FALTA    [$tipo] $ruta"; faltantes="$faltantes $ruta"
  else
    actual=$(md5sum "$ruta" 2>/dev/null | cut -d' ' -f1)
    if [ "$actual" != "$hash" ]; then
      echo "DIFIERE  [$tipo] $ruta"; faltantes="$faltantes $ruta"
    fi
  fi
done <<'HASHES'
ce154cbda0fc9b2eb53bfd977994f258 DOC .env.example
6266a0fe50f8739c2ad00f939873e07b APP app/Console/Commands/CancelAbandonedOrders.php
b0bfbb41997af71a54d6f34a74d0173b APP app/Console/Commands/FixCommaSizes.php
f0b1d4e59ca28740a27dea85e425f08f APP app/Filament/Resources/OrderResource.php
ab5830e4e8f9c609f2d8f92f5d01d023 APP app/Filament/Resources/ProductResource.php
aea8eb179913a229f682a1c6d1dfb7b9 APP app/Filament/Resources/ShippingMethodResource.php
5d3067a6864ab22ae7f303be98736adf APP app/Filament/Resources/ShippingOptionResource.php
5c31d5ac3a1d5ad894a428caeb602ba9 APP app/Filament/Resources/ShippingOptionResource/Pages/CreateShippingOption.php
369e46544f27da28399d1d9535a36250 APP app/Filament/Resources/ShippingOptionResource/Pages/EditShippingOption.php
0e5f1eb0bfc46b540244423476c4bc99 APP app/Filament/Resources/ShippingOptionResource/Pages/ListShippingOptions.php
b34051c63825e22c0e63d44e74111ed4 APP app/Filament/Widgets/LowStockWidget.php
0b1e27301ab8990ae2001fbda2944880 APP app/Http/Controllers/Api/AuthController.php
102f55f15b476deb639ecc086a96f8ce APP app/Http/Controllers/Api/CartController.php
f873204d75d8f40fd19ca965dea18fe1 APP app/Http/Controllers/Api/OrderController.php
2e6d1d882d414eaa4c207f42d1b0c8d6 APP app/Http/Controllers/Api/PaymentController.php
c9b22c42f79d98fc11dbef94c3bb7725 APP app/Http/Controllers/Api/ShippingController.php
10bfd7c1ee768d3db56230723bdb01f0 APP app/Http/Requests/Auth/RegisterFromOrderRequest.php
59be57aea37bc0285f39a43279d36aeb APP app/Http/Requests/Order/StoreExpressOrderRequest.php
64b3f427b2482c49a5e5624f5e89860b APP app/Http/Requests/Order/StoreOrderRequest.php
88b571feb65db61a6654e9fa5229ec78 APP app/Http/Requests/Product/StoreProductRequest.php
f371ef7a55c0f64449d24fdfc66e8173 APP app/Http/Requests/Product/UpdateProductRequest.php
0cd3dbee3fe4f52ed80f9c6ce97bbd35 APP app/Http/Resources/CartItemResource.php
69a66f5faad439f5c2610eb008e61564 APP app/Http/Resources/OrderResource.php
57eb6b15abe98cebbc551c140218d005 APP app/Http/Resources/ProductResource.php
590fdaa1f035fadd8e0acacbdba05b75 APP app/Http/Resources/ProductVariantResource.php
163380478d1f0bda4cfd370ce6fce1e0 APP app/Mail/OrderReceivedMail.php
d7bcd96c92f6f7edadde2f17a982bc6d APP app/Models/Order.php
cb595a30b32a3e0b1772a7f67826e04c APP app/Models/Product.php
1c7c08c92295ced5e89bac5f37ce5ef2 APP app/Models/ProductVariant.php
bc9e6b015d09ad5ed418bbb30b8fc4e8 APP app/Models/ShippingMethod.php
f133d908ce35016e6aeb8576314c9dfc APP app/Models/ShippingOption.php
367eaaa4ce3729792942da332c45939a APP app/Services/EmailService.php
df80323dac380edff8b5427295c6e926 APP app/Services/OrderService.php
b1056b32bc6743ab53cb1f5a53ff592d APP app/Services/Payments/PaypalService.php
e0167d31244ab663aad925d91e083613 APP app/Services/ShippingWeightService.php
f004bc121829c4fdc6810cb78eff4af5 APP app/Support/Countries.php
11794c60ea09facec4cf4fda401a658c APP app/Support/ShippingCountries.php
d4e6c270d034c912a3166a4440fe55e6 APP app/Support/SizeCatalog.php
9cbb9c89bbe7f2809b3788f94a9d91ee APP database/factories/ProductFactory.php
52b75fed16f17227ba3f6be49084e5e6 APP database/factories/ProductVariantFactory.php
6aae9efd08400e8dee0d1636a70bbcbd APP database/factories/UserFactory.php
722fc259ed16ee4e182c41aa71e0f469 APP database/migrations/2026_09_21_100001_add_color_to_products_table.php
4f843b84e388c1f24b839b91655e7eb6 APP database/migrations/2026_09_22_100001_create_shipping_options_table.php
b7f75cd860aa81b06c41afdc469344cb APP database/migrations/2026_09_22_100002_simplify_shipping_methods_table.php
5bb1993407f4e6b49d656ed6fd04c88e APP database/migrations/2026_09_22_100003_add_shipping_option_to_orders_table.php
0014df4adb978629343012a20685422b APP database/migrations/2026_09_23_100001_add_access_token_to_orders_table.php
cb6eadff613d98ba0c5f408d93069fe4 APP database/migrations/2026_09_24_100001_change_payment_method_to_string_on_orders.php
17f2bb8cbd9e96a6bedc59e2820cbebd APP database/migrations/2026_09_30_100001_add_email_verificado_por_to_orders_table.php
a27d897f007e9d983ee9a90e2929dcd1 APP database/migrations/2026_10_01_100001_add_attention_reason_to_orders_table.php
be473521a6acaecac743dbe3cf7dd4a1 APP database/seeders/ShippingMethodSeeder.php
8526b351703bbb8b66ccb3f604924e0c APP resources/views/emails/order-confirmed.blade.php
db2fe6958db45fb14f0384cba9599252 APP resources/views/emails/order-received.blade.php
11c800801b828d93e71a941dfd5e26d3 APP routes/api.php
7a84e84d40e85f2b1c6eeb37cc39ee18 APP routes/console.php
a9abbb01c2ea0da5aeada2d937b10e1a TEST tests/Feature/AdminPanelTest.php
51bcc219eb2d96961adcbc94178ccd28 TEST tests/Feature/CancelAbandonedOrdersTest.php
c3004b8c3acd3539960b92fc8714edb2 TEST tests/Feature/OrderAccessTest.php
0d1fb27c4a9c7f7f3e5fc5f93fcf2dcf TEST tests/Feature/OrderPanelFiltersTest.php
c5b7c29cce56eb85a741e68439d3cb77 TEST tests/Feature/PaymentRoutesTest.php
8f3fbf8b978ed45898f6ed4f2a84e41c TEST tests/Feature/PaypalCaptureLoggingTest.php
e26a08c1d3168dc1d90b49da899da679 TEST tests/Feature/PaypalExpressTest.php
fb2797609be6a1f00c3bf7f497ae8623 TEST tests/Feature/PaypalWebhookTest.php
8a44f9b025f9a42c8f0741732932fa58 TEST tests/Feature/RegisterFromOrderTest.php
895bbf1502fb8e842a61a8d204c3d83d TEST tests/Feature/ShippingOptionTest.php
1420e2ca24f8e42bb425d072b2e2ee2c TEST tests/Feature/StockAtPaymentTest.php
950c6a6ed8b2bf7411a1a1b8ba1f31e8 TEST tests/Feature/VariantSizeToolsTest.php
HASHES
echo
if [ -z "$faltantes" ]; then
  echo "TODO OK: los archivos del servidor coinciden con el repo."
else
  echo "── Archivos a subir ──"
  for f in $faltantes; do echo "$f"; done
  echo
  echo "Total a subir: $(echo $faltantes | wc -w)"
fi
