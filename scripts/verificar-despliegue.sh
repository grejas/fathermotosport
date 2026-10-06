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
# Cubre TODO el backend en 03c239f: los 346 archivos trackeados que forman parte de un
# despliegue, no solo los de la última tanda. La idea es que el script sea la fuente de
# verdad del estado del servidor, sin depender de recordar qué se subió y qué no.
#
# Quedan FUERA a propósito (20 de los 366 trackeados):
#   - storage/**/.gitignore, bootstrap/cache/.gitignore, database/.gitignore: marcadores
#     de carpeta. En el servidor esos directorios tienen contenido de runtime y
#     compararlos solo genera ruido.
#   - .editorconfig, .gitattributes, .gitignore, README.md, package.json, vite.config.js:
#     herramientas de desarrollo que no se despliegan.
#   - public/.htaccess y public/index.php: los personaliza el hosting. Reportarlos como
#     DIFIERE invitaría a sobreescribirlos y eso puede tumbar el sitio. Si tu despliegue
#     usa la estructura estándar de Laravel, podés sumarlos a la lista.
#
# Para regenerar las huellas después de otros cambios, desde la raíz del repo:
#
#   git ls-files fathermotosport-api/ | sed 's|^fathermotosport-api/||' \
#     | grep -vE '^storage/|^bootstrap/cache/\.gitignore$|^database/\.gitignore$|^\.editorconfig$|^\.gitattributes$|^\.gitignore$|^README\.md$|^package\.json$|^vite\.config\.js$|^public/\.htaccess$|^public/index\.php$' \
#     | sort
#
# y rehashear con md5sum desde fathermotosport-api/ (formato: "md5 TIPO ruta").
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
4d3fc4ce89d95f4fa939e0c68e13d6a1 APP app/Console/Commands/CancelAbandonedOrders.php
b0bfbb41997af71a54d6f34a74d0173b APP app/Console/Commands/FixCommaSizes.php
74ff6786e51e8aa395d9a7dc29195b6a APP app/Console/Commands/GenerateFlashPromoOccurrences.php
3bc9700a4f3a6e961eb2bdbc0b7e0cd2 APP app/Filament/Concerns/SincronizaOrdenConFiltro.php
251b76077bd523abc5fdb2b1c9b394f2 APP app/Filament/Pages/ShippingReturnsPage.php
7ecc786f59faa28e7f0d5f6e0db1c0bd APP app/Filament/Pages/StoreSettingsPage.php
66eb384c2d5b320f568cd888a326b1fe APP app/Filament/Resources/BannerResource.php
74c550d0ca540120f7f448a05409c2b2 APP app/Filament/Resources/BannerResource/Pages/CreateBanner.php
d6a41f37ad4f4a84ef6257dc529df838 APP app/Filament/Resources/BannerResource/Pages/EditBanner.php
12abf8d9ffedccaaa0332f6910532f9e APP app/Filament/Resources/BannerResource/Pages/ListBanners.php
ee63fe009f07717229518f427c8ecb37 APP app/Filament/Resources/BrandResource.php
ed0fb9c6464b50fabf06a9cd512d71f2 APP app/Filament/Resources/BrandResource/Pages/CreateBrand.php
29c300c6bcdca3a65c1f54d3592b6c1a APP app/Filament/Resources/BrandResource/Pages/EditBrand.php
72cb1ab578b79dc06cddecb5d5c1b35b APP app/Filament/Resources/BrandResource/Pages/ListBrands.php
21bf907f0c72bff6d4574bb998536d60 APP app/Filament/Resources/CategoryResource.php
ad522d09a6e035d1b29a1ce4acd4a3d8 APP app/Filament/Resources/CategoryResource/Pages/CreateCategory.php
939b623dff44febbd024545eedc5993b APP app/Filament/Resources/CategoryResource/Pages/EditCategory.php
762cc72ffc03706a031a22edc3c9930c APP app/Filament/Resources/CategoryResource/Pages/ListCategories.php
4ff848043b4261aaeb1191ee5ed770e1 APP app/Filament/Resources/CouponResource.php
c15709edd819615ae9ae39cf736fd8a5 APP app/Filament/Resources/CouponResource/Pages/CreateCoupon.php
11c6bf5788dc64e3396a9aecda342881 APP app/Filament/Resources/CouponResource/Pages/EditCoupon.php
c09b8c3d4060886131a7cea802b5fdd5 APP app/Filament/Resources/CouponResource/Pages/ListCoupons.php
468cbf726b8234e612fac02c4a1011d9 APP app/Filament/Resources/CustomerResource.php
749ad95d0336ef8592e0e18e6513d2a1 APP app/Filament/Resources/CustomerResource/Pages/ListCustomers.php
906c94692e8c101f22def73988690c65 APP app/Filament/Resources/CustomerResource/Pages/ViewCustomer.php
f7514dda9fbd7d5ba22d6da1e9585297 APP app/Filament/Resources/EmployeeResource.php
7080deb8e7e86d2a3daaa48113d6e2c0 APP app/Filament/Resources/EmployeeResource/Pages/CreateEmployee.php
343894dbfa0d980081fe837829c2e567 APP app/Filament/Resources/EmployeeResource/Pages/EditEmployee.php
46828122b1aacb5e36af7722d3987db6 APP app/Filament/Resources/EmployeeResource/Pages/ListEmployees.php
0d657d08c43e81507699636cd8825457 APP app/Filament/Resources/FlashPromoResource.php
54d7b73968424bbba96b81ae66b092ca APP app/Filament/Resources/FlashPromoResource/Pages/CreateFlashPromo.php
1922b4ae3079500739f08794713c9afe APP app/Filament/Resources/FlashPromoResource/Pages/EditFlashPromo.php
822b6f49bc1f15a20f05156b55502914 APP app/Filament/Resources/FlashPromoResource/Pages/ListFlashPromos.php
16c68558c5d4cb5413fb1727e2e5e3ed APP app/Filament/Resources/InventoryMovementResource.php
204d05cbdc7ac7fefc8a859d81d4b4f9 APP app/Filament/Resources/InventoryMovementResource/Pages/CreateInventoryMovement.php
1f2d442718cb102b9f93d937cbff0d98 APP app/Filament/Resources/InventoryMovementResource/Pages/EditInventoryMovement.php
ac78755784027f6a52c51c4bc2ed91ae APP app/Filament/Resources/InventoryMovementResource/Pages/ListInventoryMovements.php
9f2f79c45130630900141db2e628dda2 APP app/Filament/Resources/InventoryMovementResource/Pages/ViewInventoryMovement.php
39a062f9d264718b8316c39533e9b11f APP app/Filament/Resources/OrderResource.php
8888b428060d04e98160f19efbc45026 APP app/Filament/Resources/OrderResource/Pages/CreateOrder.php
5f8a5d0a8f1fa1c38123a2a9b7537380 APP app/Filament/Resources/OrderResource/Pages/EditOrder.php
73db53c8df1bef5627293fbda9b381e2 APP app/Filament/Resources/OrderResource/Pages/ListOrders.php
99c04536d65cd9b03c44145c83d505f6 APP app/Filament/Resources/OrderResource/Pages/ViewOrder.php
fe33a57c5363e4cb9a036161e87c8338 APP app/Filament/Resources/PostResource.php
f2c50195abec234d4a7d4d2e5bb6d13a APP app/Filament/Resources/PostResource/Pages/CreatePost.php
8f39dd47cf400fab1690c6a697ea71fd APP app/Filament/Resources/PostResource/Pages/EditPost.php
3b19d8f70deac56cf27ccc5d5c73c74b APP app/Filament/Resources/PostResource/Pages/ListPosts.php
94bbe594b6db26a88aa0a6e3b42b1503 APP app/Filament/Resources/ProductResource.php
eb8b5dde8bed4865aa1acaa9be3229ce APP app/Filament/Resources/ProductResource/Pages/CreateProduct.php
e40037f55754c6cef78a064ecad02a50 APP app/Filament/Resources/ProductResource/Pages/EditProduct.php
8903ac99fec02ac01ebbdcfb8d4935b4 APP app/Filament/Resources/ProductResource/Pages/ListProducts.php
eff8f29bb1371e31641505cdb81df384 APP app/Filament/Resources/ReviewResource.php
ca899e9cbf3e9718a3c445e4326d4eae APP app/Filament/Resources/ReviewResource/Pages/CreateReview.php
2f039d810e38e95b0b69f2a7d1ca0f5d APP app/Filament/Resources/ReviewResource/Pages/EditReview.php
cda6a3022fabd11b6c0b58c9996d084a APP app/Filament/Resources/ReviewResource/Pages/ListReviews.php
aea8eb179913a229f682a1c6d1dfb7b9 APP app/Filament/Resources/ShippingMethodResource.php
dd6754d856a65efaa85eb3605d80544b APP app/Filament/Resources/ShippingMethodResource/Pages/CreateShippingMethod.php
205918f1921309f6777f14c8f2ad88f2 APP app/Filament/Resources/ShippingMethodResource/Pages/EditShippingMethod.php
c02dae8580e9bad4e1d3d4d3960a5841 APP app/Filament/Resources/ShippingMethodResource/Pages/ListShippingMethods.php
5d3067a6864ab22ae7f303be98736adf APP app/Filament/Resources/ShippingOptionResource.php
5c31d5ac3a1d5ad894a428caeb602ba9 APP app/Filament/Resources/ShippingOptionResource/Pages/CreateShippingOption.php
369e46544f27da28399d1d9535a36250 APP app/Filament/Resources/ShippingOptionResource/Pages/EditShippingOption.php
0e5f1eb0bfc46b540244423476c4bc99 APP app/Filament/Resources/ShippingOptionResource/Pages/ListShippingOptions.php
c9bc29f168822f7122243db854e5e1c8 APP app/Filament/Resources/UpsellRuleResource.php
73aa81783a59063189ed376690bd56ca APP app/Filament/Resources/UpsellRuleResource/Pages/CreateUpsellRule.php
d432570a89a56d23245fad361336e7c4 APP app/Filament/Resources/UpsellRuleResource/Pages/EditUpsellRule.php
fedcb7d80a66dbd7d0524666921381ce APP app/Filament/Resources/UpsellRuleResource/Pages/ListUpsellRules.php
8679320fef5174f9146c03365e885a15 APP app/Filament/Resources/VisorColorResource.php
424f8ed874b7adf30c33542d12a4e080 APP app/Filament/Resources/VisorColorResource/Pages/CreateVisorColor.php
fd5c60c0201db7114817de12c7811c33 APP app/Filament/Resources/VisorColorResource/Pages/EditVisorColor.php
53044c07ebd2b8d6ef1dca12e77a5182 APP app/Filament/Resources/VisorColorResource/Pages/ListVisorColors.php
ecbb07f1de65da7c9f4e40466559798d APP app/Filament/Support/FiltroDeOrden.php
b34051c63825e22c0e63d44e74111ed4 APP app/Filament/Widgets/LowStockWidget.php
c396a2568f90a06c31dc7c77acb143c3 APP app/Filament/Widgets/RecentOrdersWidget.php
cfb084b44f6e5d03c44964e5a7130781 APP app/Filament/Widgets/SalesChartWidget.php
21bd2c50cb86ce8bb17eae5328d3942d APP app/Filament/Widgets/StatsOverviewWidget.php
4f43754b25bedfd44b682dfda5e3c08d APP app/Filament/Widgets/TopProductsWidget.php
614928152d8d7e4fcd0a68eb640e64f5 APP app/Http/Controllers/Api/Admin/CustomerController.php
054e506c55baed4feb82200621d68f87 APP app/Http/Controllers/Api/Admin/DashboardController.php
aa46ac107754a82761b1210cb81f47ae APP app/Http/Controllers/Api/Admin/OrderController.php
db7a546d743f15d1ad2d06ec51063545 APP app/Http/Controllers/Api/Admin/ProductController.php
dfe89c0c5b47bdc5f1144a50da119137 APP app/Http/Controllers/Api/AuthController.php
d8d50096a1aff652af257ec641a3f8db APP app/Http/Controllers/Api/BannerController.php
9bcc7cfc5fe54ab56b6a70b42f5e02b2 APP app/Http/Controllers/Api/BrandController.php
77e7f831c26aea2fc15bd36fa9ec204a APP app/Http/Controllers/Api/CartController.php
ad9f45aab1d478c3391498bb64e4ea52 APP app/Http/Controllers/Api/CategoryController.php
0674645ddb446dca4556e1c347714d0e APP app/Http/Controllers/Api/CouponController.php
0842345bc20f11e44eef09817d199ccd APP app/Http/Controllers/Api/FlashPromoController.php
c4686e4ea179ba8ad4395a9df6f1906a APP app/Http/Controllers/Api/OrderController.php
8ff1e6dfb21dcf20d038cd457eb70018 APP app/Http/Controllers/Api/PaymentController.php
ac0988f343681cbcc928a3023b23c67d APP app/Http/Controllers/Api/PostController.php
a7b35f422a7f758a6dac5d5b3e315449 APP app/Http/Controllers/Api/ProductController.php
c08929c866756d078a1ada5c8e091ade APP app/Http/Controllers/Api/ReviewController.php
c9b22c42f79d98fc11dbef94c3bb7725 APP app/Http/Controllers/Api/ShippingController.php
7e61589b5fefe7607583a55d6e7c52f8 APP app/Http/Controllers/Api/ShippingReturnsController.php
74513d2bfd2ff5e5afff5c5d2381b0c8 APP app/Http/Controllers/Api/UpsellController.php
12b42913e1449e09ffbb0678bc2e84aa APP app/Http/Controllers/Api/UserController.php
c2a20fdca609dee26d4c79cfae6381ec APP app/Http/Controllers/Api/VisorColorController.php
25a307335c0719bc9a91b3f7babd186d APP app/Http/Controllers/Controller.php
5e9e640b0a68cb530b5b2dda49e5baf7 APP app/Http/Middleware/CheckRole.php
6c57a3c988f2bab5501065350146ac80 APP app/Http/Middleware/CompressResponse.php
8ff5b0fa70ffbf5b650b9a24d4626a45 APP app/Http/Middleware/EnsurePanelAccess.php
bb05b0c4b81a52b7d206eafc98ac5c9d APP app/Http/Middleware/SecurityHeaders.php
6c0eaf6f40bab189602767b4c301de01 APP app/Http/Requests/Auth/ForgotPasswordRequest.php
74e67939e0fafdf1ef60b2ad6f7fb6b0 APP app/Http/Requests/Auth/LoginRequest.php
10bfd7c1ee768d3db56230723bdb01f0 APP app/Http/Requests/Auth/RegisterFromOrderRequest.php
f66e32a89951412bedf9aec2852a322e APP app/Http/Requests/Auth/ResetPasswordRequest.php
1e423be946c1cd194cc9efb2f6e27461 APP app/Http/Requests/Auth/SendVerificationCodeRequest.php
650a8a3fd4c1ee3b2c27844dc562f879 APP app/Http/Requests/Auth/VerifyAndRegisterRequest.php
eab92c776a006cbe04a6c95d93e9c69c APP app/Http/Requests/Cart/AddCartItemRequest.php
a58a0b7641ff662597e2253c3c728f4c APP app/Http/Requests/Cart/UpdateCartItemRequest.php
59be57aea37bc0285f39a43279d36aeb APP app/Http/Requests/Order/StoreExpressOrderRequest.php
64b3f427b2482c49a5e5624f5e89860b APP app/Http/Requests/Order/StoreOrderRequest.php
741d3af361cb8718a74da3a0dda6d348 APP app/Http/Requests/Order/StoreUpsellRequest.php
88b571feb65db61a6654e9fa5229ec78 APP app/Http/Requests/Product/StoreProductRequest.php
f371ef7a55c0f64449d24fdfc66e8173 APP app/Http/Requests/Product/UpdateProductRequest.php
8998e9c9734b5e8e1e7dc10454f4aac3 APP app/Http/Requests/User/StoreAddressRequest.php
43ee759fd9e24e573381e003ea310f46 APP app/Http/Requests/User/UpdateProfileRequest.php
8071ffc15bff2b07b1ec56585d5e0f4b APP app/Http/Resources/AddressResource.php
306b2256cde9e3910f1e8bb8dd253d36 APP app/Http/Resources/BannerResource.php
19ff8a699fab1f9efa630a7b2e19b88b APP app/Http/Resources/BrandResource.php
0cd3dbee3fe4f52ed80f9c6ce97bbd35 APP app/Http/Resources/CartItemResource.php
c7426b83ef6070b5178f5357bfe613da APP app/Http/Resources/CartResource.php
6a89bed5b2d22f56dce77a9b96a3539b APP app/Http/Resources/CategoryResource.php
7e5a9277b5041e477c5159a18214ca85 APP app/Http/Resources/OrderItemResource.php
9a2479b4d4e1541dc8df72d62d5ac41a APP app/Http/Resources/OrderResource.php
71345aed6ee3e5899fb0ed41acad830e APP app/Http/Resources/PaymentResource.php
37dd33e7471c05110b528d5556b77459 APP app/Http/Resources/PostResource.php
1cf734318de9ecf8f6e2b97487e6f866 APP app/Http/Resources/Product3dModelResource.php
da681d0d30f4dfa506b2fc9a5bc85ba4 APP app/Http/Resources/ProductImageResource.php
57eb6b15abe98cebbc551c140218d005 APP app/Http/Resources/ProductResource.php
590fdaa1f035fadd8e0acacbdba05b75 APP app/Http/Resources/ProductVariantResource.php
7c014a9afd0d7c1e34dfc02cd1bc795e APP app/Http/Resources/ReviewResource.php
ff6c95a3e988c6b7e21133f74e703dae APP app/Http/Resources/UserResource.php
7f8eca4422afefd5d0f3288264ccba91 APP app/Http/Resources/VisorColorResource.php
cfd441691e1ff1573ff0ea553006ccce APP app/Mail/EmailVerificationCodeMail.php
51dbba15e2d47452cda0376565b8fc03 APP app/Mail/OrderConfirmedMail.php
163380478d1f0bda4cfd370ce6fce1e0 APP app/Mail/OrderReceivedMail.php
dd113bd12e2add2635a7c285b39cf61f APP app/Mail/ResetPasswordMail.php
068d41c2bff6bb36f785593fee135c26 APP app/Mail/ShippingUpdateMail.php
0e529e827cd1f52ba2ca1a950403937d APP app/Mail/WelcomeEmployeeMail.php
e0ba9f2514bf5a55ca9ecff197db0484 APP app/Mail/WelcomeMail.php
f56bc8e2303c59d97e66270eade968ee APP app/Models/Address.php
e2d3d7458e18177fa2a76394a1c5bd5c APP app/Models/Banner.php
e5c04f5dc1db62a14f12c9ea5cad5e9f APP app/Models/Brand.php
353c806423540a0bb034f2c6952d7531 APP app/Models/Cart.php
1da89e1ef077905152ed0b0948c689df APP app/Models/CartItem.php
d95b5fbe37fc3072cfe0ab17aed7f506 APP app/Models/Category.php
d86c4f973da0a4f16a76422245bfdf3e APP app/Models/Concerns/HasUuid.php
2f2522ef96ec852ef8148fe11563f15a APP app/Models/Coupon.php
226aaf9fd5d910085269bbb88ca13f6f APP app/Models/EmailVerificationCode.php
b120497dfdbab99a8b70d0291331b161 APP app/Models/Favorite.php
c5b7aa67d436d8b6e285f90370e26c27 APP app/Models/FlashPromo.php
121d9c6338e2d12c344b2df673b8f2c8 APP app/Models/InventoryMovement.php
8bbe9079602c51737a0b76f0a58a9928 APP app/Models/Order.php
c8ec264de7a156f677e7f36bb5402340 APP app/Models/OrderCoupon.php
b5f1daf32ca25c19611babb7679ded5c APP app/Models/OrderItem.php
6d2b6cc8a86bd27fad819c0cc405bbe5 APP app/Models/Payment.php
01947020070e788667f13e0892a7d7cd APP app/Models/Permission.php
a2205294e661c44a50835643f2e86d5b APP app/Models/Post.php
cb595a30b32a3e0b1772a7f67826e04c APP app/Models/Product.php
a45beab482607cb2600028be8b6fcd76 APP app/Models/Product3dModel.php
e33c0184a7becd0bf8769844048cdbc0 APP app/Models/ProductImage.php
1c7c08c92295ced5e89bac5f37ce5ef2 APP app/Models/ProductVariant.php
bb7963962401672559e95ec3ea4aac4a APP app/Models/Review.php
82fc19be1ef9dd26caca433ae3c9b418 APP app/Models/Role.php
3c9c36fbc84346d3f94fde3ad2673cf3 APP app/Models/Shipment.php
bc9e6b015d09ad5ed418bbb30b8fc4e8 APP app/Models/ShippingMethod.php
f133d908ce35016e6aeb8576314c9dfc APP app/Models/ShippingOption.php
3b0774c41c80ce8dab06a4022f946f5f APP app/Models/ShippingReturnsSetting.php
4eabb0f314662ca6b31d4f3bca4bf574 APP app/Models/StoreConfig.php
4108080de0220f4680f33b49ef645ee6 APP app/Models/UpsellRule.php
126ac2173285cdf7568c8b8ffb147ada APP app/Models/User.php
60fe6bb2f85113dd4a57cd75ea9bb363 APP app/Models/VisorColor.php
c0094f35cad6bba7636002a62186dae4 APP app/Providers/AppServiceProvider.php
d417c9fc28640cc0d4e0617ba6902f32 APP app/Providers/Filament/AdminPanelProvider.php
64cc6d7c7a0cb126681a832857e4f9aa APP app/Rules/ValidPhone.php
99d42267f4b7d8572f258b8fd77d6fd5 APP app/Services/CartService.php
d6023236d13a06321fa71ef5b8fcb872 APP app/Services/CouponService.php
367eaaa4ce3729792942da332c45939a APP app/Services/EmailService.php
b314cf224e9ba70f6c4091b09b91511b APP app/Services/FrontendRevalidator.php
6d11de86260f43771f77c00121d67f13 APP app/Services/OrderService.php
0dd60cb6468e57f98c058b066ab98436 APP app/Services/Payments/MercadoPagoService.php
b1056b32bc6743ab53cb1f5a53ff592d APP app/Services/Payments/PaypalService.php
8334852d295e7fad8cd29863b485a89c APP app/Services/Payments/StripeService.php
e0167d31244ab663aad925d91e083613 APP app/Services/ShippingWeightService.php
558cfc5b9dc3b1ce22949b1f4dea47ef APP app/Services/StorageService.php
c4d266ef47fb4ce488ac0887f23a7461 APP app/Services/UpsellService.php
f004bc121829c4fdc6810cb78eff4af5 APP app/Support/Countries.php
11794c60ea09facec4cf4fda401a658c APP app/Support/ShippingCountries.php
d4e6c270d034c912a3166a4440fe55e6 APP app/Support/SizeCatalog.php
e66bb13691f6184c762c196427d8e685 APP artisan
97d6fd3f47c4991c245c6f57652b8385 APP bootstrap/app.php
607aa0bfa40bdaba26371d1dbc4d3219 APP bootstrap/providers.php
16db62090ddbf8d65e6aeee1db70cd18 APP composer.json
01b31b72e98f3282ed7458dbf7b1cdea APP composer.lock
2ba6815aefb125f7fd68e47eb53a3348 APP config/app.php
26354511ab860b301f2a3554bc388b91 APP config/auth.php
022fdf20e95ddb79949954243e367003 APP config/cache.php
b5a859610854da3325e0c98011e0b118 APP config/cors.php
3cd69c7ecc842e4552e44eade546d91f APP config/database.php
2d6ecd90b3177b7d891eacdd42dba452 APP config/filesystems.php
d8255a5c07d3da72c7c9363f0c44bddd APP config/logging.php
18601a927b48d38337ba2077426a7d60 APP config/mail.php
f98ce92e7bf51db1fabaf8a30e12fc5a APP config/queue.php
e3887ebee464a1b2c014cb16696a2b50 APP config/sanctum.php
e4b66b6741e94aecb48b5c9a5910df74 APP config/services.php
6d9336e9ca1183588abbce7ff7a6f29a APP config/session.php
9cbb9c89bbe7f2809b3788f94a9d91ee TEST database/factories/ProductFactory.php
c66ce61fdb6acd5aeb7012096c111ee5 TEST database/factories/ProductImageFactory.php
52b75fed16f17227ba3f6be49084e5e6 TEST database/factories/ProductVariantFactory.php
060a48806257c315c70b840862c9736a TEST database/factories/ReviewFactory.php
6aae9efd08400e8dee0d1636a70bbcbd TEST database/factories/UserFactory.php
fdffdfb30e888c34ce54d86c772d2931 APP database/migrations/0001_01_01_000000_create_users_table.php
f3f38b41650112a6282c8dd1f635afb0 APP database/migrations/0001_01_01_000001_create_cache_table.php
ead9cce2650b36345454f85e8c0214a0 APP database/migrations/0001_01_01_000002_create_jobs_table.php
48edfe37d5e1173bef0831b2cd743d00 APP database/migrations/2026_01_01_100001_create_roles_table.php
1cd80485f3da741df70530a2747a01a7 APP database/migrations/2026_01_01_100002_create_permissions_table.php
b724d44b4c3ddcaf4fba0ad8faf32fc0 APP database/migrations/2026_01_01_100003_create_role_permissions_table.php
cb831b428b8e9cbd512916262cbd5d3d APP database/migrations/2026_01_01_100004_create_brands_table.php
163e5183da99ac2b2019b37bbf2f9c42 APP database/migrations/2026_01_01_100005_create_categories_table.php
ecde503dd5fb66e71733381ba09ae3b9 APP database/migrations/2026_01_01_100006_create_shipping_methods_table.php
b346e9dcc5ca9751ce37c4e241aa071b APP database/migrations/2026_01_01_100007_create_coupons_table.php
f663ad2591c0c544d871f305e5d807f4 APP database/migrations/2026_01_01_100008_create_banners_table.php
025b3ec34531e2964ced0d6b5fb7bfe3 APP database/migrations/2026_01_01_100009_create_store_config_table.php
8fa01ea41d634a88ddaf39e29e21344d APP database/migrations/2026_01_02_100001_create_users_table.php
1bab81fc20337735ea5cb7fa1dd2e1ac APP database/migrations/2026_01_02_100002_create_products_table.php
147a36f30a8e0167e11956382f95de72 APP database/migrations/2026_01_02_100003_create_addresses_table.php
9a0f2d500a6e016b1e17292a40365c05 APP database/migrations/2026_01_02_100004_create_carts_table.php
5af5064ace47ab8dd10a1ea9e210e15a APP database/migrations/2026_01_03_100001_create_product_variants_table.php
adf9391804f72e759761c27b1d6fb093 APP database/migrations/2026_01_03_100002_create_product_images_table.php
a3a0ef0e48a132938638e7df29f83309 APP database/migrations/2026_01_03_100003_create_product_3d_models_table.php
1e260038266ce13b55df4fd091660b7a APP database/migrations/2026_01_03_100004_create_inventory_movements_table.php
89eb34940d4f030d7462e36765f48c03 APP database/migrations/2026_01_03_100005_create_favorites_table.php
e73e357a32ec2ada441a6aa51e12698c APP database/migrations/2026_01_03_100006_create_reviews_table.php
19173d48bf878d3c2db685522bf541c9 APP database/migrations/2026_01_04_100001_create_cart_items_table.php
e6c059138be1c34efc44c4676fb0bef8 APP database/migrations/2026_01_04_100002_create_orders_table.php
cff7b85bcd16985ccc2a0fce4c78f7c9 APP database/migrations/2026_01_05_100001_create_order_items_table.php
7609415373043c7f1e8c89f175893a7a APP database/migrations/2026_01_05_100002_create_payments_table.php
44df3d31ec709ec4bddbb2c3aeecdb14 APP database/migrations/2026_01_05_100003_create_shipments_table.php
62339145792f845d9a56e627ea90f1db APP database/migrations/2026_01_05_100004_create_order_coupons_table.php
d4b89f2b1f37c5238dcfa0fbc8a0e717 APP database/migrations/2026_06_26_204300_create_personal_access_tokens_table.php
5d5f09331a2494118eb6f0587524078b APP database/migrations/2026_06_26_210000_add_user_id_to_coupons_table.php
84a65ee4d6b1c018874f656a70ba7c27 APP database/migrations/2026_07_01_100001_add_profile_fields_to_users_table.php
6f43a9dbb9290331d6ac0180e0694401 APP database/migrations/2026_07_01_100002_add_expires_at_to_coupons_table.php
34664e10a5e2795ebcd2b98644ccedc7 APP database/migrations/2026_07_01_100003_make_name_fields_nullable_on_users_table.php
0b040dfac9168284de5f238bfc0d72e3 APP database/migrations/2026_07_02_100001_add_performance_indexes.php
e6a6bff82b55970a94a1325294bd9bbe APP database/migrations/2026_07_07_100001_add_tiktok_to_store_config_table.php
a47c577e0bb0b1a2ef045bb418ced11b APP database/migrations/2026_07_10_100001_change_country_fields_to_string.php
4d23508c486c11932f461c36fcca9121 APP database/migrations/2026_07_23_100001_create_visor_colors_table.php
847c2190ac170faab085f709c2b41a8a APP database/migrations/2026_07_23_100002_create_product_visor_colors_table.php
233bced6c47097597f114bb4a88829db APP database/migrations/2026_08_10_100001_add_spin_url_to_products_table.php
d9d37529d586331f400e04ee13627608 APP database/migrations/2026_08_12_100001_create_email_verification_codes_table.php
547d3b30c60d93434177ee6f1eedb967 APP database/migrations/2026_08_21_100001_create_posts_table.php
698e003e4e379728ed31b3450076deda APP database/migrations/2026_08_27_100001_add_country_to_users_table.php
4b4e670c04789ec13d09df337540aeb7 APP database/migrations/2026_09_09_100001_create_flash_promos_table.php
6de38e9397a8f86b445670899048292b APP database/migrations/2026_09_09_100002_add_category_id_to_flash_promos_table.php
8d607e4b8297fd599697d29819db0617 APP database/migrations/2026_09_10_100001_recreate_flash_promos_scheduled.php
0bb66fba044ff74e31e7412c3563e8c9 APP database/migrations/2026_09_10_100002_create_category_flash_promo_pivot.php
fd822d7b09c7372d49d33386ffbe3298 APP database/migrations/2026_09_11_100001_add_recurring_to_flash_promos.php
722fc259ed16ee4e182c41aa71e0f469 APP database/migrations/2026_09_21_100001_add_color_to_products_table.php
4f843b84e388c1f24b839b91655e7eb6 APP database/migrations/2026_09_22_100001_create_shipping_options_table.php
b7f75cd860aa81b06c41afdc469344cb APP database/migrations/2026_09_22_100002_simplify_shipping_methods_table.php
5bb1993407f4e6b49d656ed6fd04c88e APP database/migrations/2026_09_22_100003_add_shipping_option_to_orders_table.php
0014df4adb978629343012a20685422b APP database/migrations/2026_09_23_100001_add_access_token_to_orders_table.php
cb6eadff613d98ba0c5f408d93069fe4 APP database/migrations/2026_09_24_100001_change_payment_method_to_string_on_orders.php
17f2bb8cbd9e96a6bedc59e2820cbebd APP database/migrations/2026_09_30_100001_add_email_verificado_por_to_orders_table.php
a27d897f007e9d983ee9a90e2929dcd1 APP database/migrations/2026_10_01_100001_add_attention_reason_to_orders_table.php
124ad937e7f08d7dc3efff243ce135c5 APP database/migrations/2026_10_02_100001_create_upsell_rules_table.php
8c593ae426f2e889c065e51f77dbb152 APP database/migrations/2026_10_05_100001_add_upsell_window_to_orders_table.php
71e9d1e92fc38e56b0740e8040fc2759 APP database/migrations/2026_10_05_100001_create_shipping_returns_settings_table.php
2e48ae742cb93d2f1b92da51a12b639e APP database/seeders/AdminUserSeeder.php
754586202649f4c72fa085e9f36c60c4 APP database/seeders/BrandSeeder.php
e31e5c442de49b636f0a118a7f370786 APP database/seeders/CategorySeeder.php
de2d2e55e4b883e7010f4cf186c804fa APP database/seeders/DatabaseSeeder.php
4e83ba1ef7e86289340278e5019bf890 APP database/seeders/FlashPromoSeeder.php
baffe5d7871be7033b21b6a3bcc3e7f3 APP database/seeders/RoleSeeder.php
be473521a6acaecac743dbe3cf7dd4a1 APP database/seeders/ShippingMethodSeeder.php
c2d16007ed8ff017903f04f54c309459 APP database/seeders/ShippingReturnsSettingsSeeder.php
f846f589c5a0652604c1c24b764f1fe9 APP database/seeders/StoreConfigSeeder.php
2bcd3ce99aeb9da0e231c84b725d4305 TEST phpunit.xml
b47546deac54c7da15ac289fc4aefd67 APP public/css/filament/filament/app.css
52a22e8c274255c724f848d751b0f568 APP public/css/filament/forms/forms.css
dd125f44d48295c22d22feee06b574bd APP public/css/filament/support/support.css
d41d8cd98f00b204e9800998ecf8427e APP public/favicon.ico
055af451d117e11009c5600efa7e0247 APP public/js/filament/filament/app.js
9a7993fa9a704f0e97d5c701b08e6bce APP public/js/filament/filament/echo.js
4393c422493e0e6143a80d58647ff2b7 APP public/js/filament/forms/components/color-picker.js
81dde65996cea522022e7e01f12aa195 APP public/js/filament/forms/components/date-time-picker.js
e6b4d840b25668da2a477ea8b2cdf026 APP public/js/filament/forms/components/file-upload.js
b23b4554a7073f7911cb39a35b88f4e9 APP public/js/filament/forms/components/key-value.js
69b95c6980262efcf7a49aa01859a7c2 APP public/js/filament/forms/components/markdown-editor.js
3c0f7f9d1c1c13febc0e4d0a5c3698b5 APP public/js/filament/forms/components/rich-editor.js
c51957de2de3f9c1553ddf8c40ffcbf1 APP public/js/filament/forms/components/select.js
0c2de9797b4b240e4c0f706863491e03 APP public/js/filament/forms/components/tags-input.js
abb98c0edb3e7228142c14a8422ef7c6 APP public/js/filament/forms/components/textarea.js
f873fe0caab22e4a8740d51d4f31a6da APP public/js/filament/notifications/notifications.js
7a114a87d2fcdefefc3e4f9012dbdbbb APP public/js/filament/support/support.js
414ff611c888683f502c4f14f7191def APP public/js/filament/tables/components/table.js
f214520fae0f668a51d73056581cd884 APP public/js/filament/widgets/components/chart.js
42945d911451ca2ed6a66f6380dcfce0 APP public/js/filament/widgets/components/stats-overview/stat/chart.js
b6216d61c03e6ce0c9aea6ca7808f7ca APP public/robots.txt
c975d4e4cf9b38d9e07f8499a0714a27 APP resources/css/app.css
1ccf2b9101110f40ecaab1608a6f91cf APP resources/js/app.js
78f909acc19aea4717eddca5a87c1ba3 APP resources/js/bootstrap.js
c618024b443bd9cbb1bead992e34bdc5 APP resources/views/emails/layouts/base.blade.php
8526b351703bbb8b66ccb3f604924e0c APP resources/views/emails/order-confirmed.blade.php
db2fe6958db45fb14f0384cba9599252 APP resources/views/emails/order-received.blade.php
b8e73e3bb5646158ecd3fca54c397d77 APP resources/views/emails/reset-password.blade.php
8a0c16358d0a67b161dbdbd1849953a5 APP resources/views/emails/shipping-update.blade.php
a8e49bb150340021897980cc716b60dc APP resources/views/emails/verification-code.blade.php
b26c722849e708761904ccd5423aa337 APP resources/views/emails/welcome-employee.blade.php
ea31b313963964bd4a5bf95fd06ed22b APP resources/views/emails/welcome.blade.php
436dda3b22d3eea4569a9b56e6a3cb3d APP resources/views/filament/pages/store-settings-page.blade.php
f9f90b3bab40fd1d366e9b54466deac7 APP resources/views/welcome.blade.php
423e8d039e6e8a39bbd64f9f5d313d52 APP routes/api.php
7a84e84d40e85f2b1c6eeb37cc39ee18 APP routes/console.php
84625c52e743b1a4178bb27b7afb3615 APP routes/web.php
a9abbb01c2ea0da5aeada2d937b10e1a TEST tests/Feature/AdminPanelTest.php
9361a037c15bfce408d416f78796fb62 TEST tests/Feature/AdminSortSelectorTest.php
d086fb86c8ecc25fe479b61f73439e81 TEST tests/Feature/AdminSortingAndRedirectTest.php
ca7593e7daed1a7481c7dc1adaf7a957 TEST tests/Feature/CancelAbandonedOrdersTest.php
0f870bb01125ffdc7708d1fbd96bc1f9 TEST tests/Feature/EmployeePermissionsTest.php
0f4d91c9c5bbcd4e2f46cf885acb7fb6 TEST tests/Feature/ExampleTest.php
3b4e7630783b2beb8d2a0589683e0a1f TEST tests/Feature/FilamentLoginTest.php
f3517dc2e7748851a10e065f96e9ff37 TEST tests/Feature/InactiveProductVisibilityTest.php
c3004b8c3acd3539960b92fc8714edb2 TEST tests/Feature/OrderAccessTest.php
0d1fb27c4a9c7f7f3e5fc5f93fcf2dcf TEST tests/Feature/OrderPanelFiltersTest.php
667eb88e4a21e7bb69afa1275f8d5f92 TEST tests/Feature/PaymentRoutesTest.php
8f3fbf8b978ed45898f6ed4f2a84e41c TEST tests/Feature/PaypalCaptureLoggingTest.php
4b88869a064e1e8e2e6a7a72aa7f5911 TEST tests/Feature/PaypalExpressTest.php
fb2797609be6a1f00c3bf7f497ae8623 TEST tests/Feature/PaypalWebhookTest.php
8d4b971fe4f40e0f88ceedd732081fd1 TEST tests/Feature/ProductImageUploadTest.php
0f9ed8d8e1317eae2148eca92d4104b1 TEST tests/Feature/ReclaimUnverifiedAccountTest.php
1b911e92dce0dde181536881518c86a4 TEST tests/Feature/RegisterFromOrderTest.php
895bbf1502fb8e842a61a8d204c3d83d TEST tests/Feature/ShippingOptionTest.php
7f23963c65a4969d4bc619439acfacdf TEST tests/Feature/ShippingReturnsTest.php
1420e2ca24f8e42bb425d072b2e2ee2c TEST tests/Feature/StockAtPaymentTest.php
6d86787afbc21750b7ed31bc2f6d2e35 TEST tests/Feature/StoreSettingsSaveTest.php
4bbfb42d8ccf31b85af71bcbf6cfcc1c TEST tests/Feature/StripePaymentTest.php
ec2e82fba213bb4e57fb58e8bedb2445 TEST tests/Feature/UpsellBulkCreateTest.php
ca7f72c6556454e35f487be2a57c3018 TEST tests/Feature/UpsellTest.php
950c6a6ed8b2bf7411a1a1b8ba1f31e8 TEST tests/Feature/VariantSizeToolsTest.php
7e567dc27e68d5b57bae16af07f68907 TEST tests/TestCase.php
3f679e9508c834f0ad95943455fcd836 TEST tests/Unit/ExampleTest.php
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
