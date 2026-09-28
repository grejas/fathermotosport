<?php

use App\Http\Controllers\Api\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\FlashPromoController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VisorColorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Rutas públicas (sin autenticación)
    |--------------------------------------------------------------------------
    */

    // Catálogo
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/featured', [ProductController::class, 'featured']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);

    Route::get('/brands', [BrandController::class, 'index']);

    // Costo de envío según país y peso del pedido
    Route::get('/shipping/calculate', [ShippingController::class, 'calculate']);

    // Banners (home / hero / sidebar / footer)
    Route::get('/banners', [BannerController::class, 'index']);
    Route::get('/banners/{position}', [BannerController::class, 'byPosition']);

    // Colores de visor para cascos
    Route::get('/visor-colors', [VisorColorController::class, 'index']);

    // Promoción flash recurrente (estado de la ventana actual)
    Route::get('/flash-promo', [FlashPromoController::class, 'show']);

    // Blog (artículos publicados)
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{slug}', [PostController::class, 'show']);

    // Reseñas (globales, de todos los productos)
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::get('/reviews/summary', [ReviewController::class, 'summary']);

    // Autenticación (rate limiting: 5 intentos por minuto y por IP, contra fuerza bruta)
    Route::middleware('throttle:5,1')->group(function () {
        // Registro en dos pasos: envía un código de 6 dígitos y luego lo verifica.
        Route::post('/auth/send-verification-code', [AuthController::class, 'sendVerificationCode']);
        Route::post('/auth/verify-and-register', [AuthController::class, 'verifyAndRegister']);
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    });

    // Google OAuth (Socialite). El callback real es /api/v1/auth/google/callback:
    // ese es el URI que debe registrarse en Google Cloud Console y en GOOGLE_REDIRECT_URL.
    Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

    // Carrito (guest por session_id)
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::put('/cart/items/{id}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // Pedidos (guest checkout)
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);

    // PayPal ACTIVO. Va antes del catch-all de abajo porque Laravel resuelve las rutas
    // por orden de registro: estas dos pasan y el resto de /payments/* sigue bloqueado.
    // El modo (sandbox o live) y las credenciales salen del .env del servidor.
    // throttle: cada petición llama a la API de PayPal.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/payments/paypal/create', [PaymentController::class, 'paypalCreate']);
        // {paypalOrderId} es el id de la orden de PayPal (el ?token= de la URL de retorno),
        // no el id de nuestro pedido.
        Route::post('/payments/paypal/capture/{paypalOrderId}', [PaymentController::class, 'paypalCapture']);
    });

    // Resto de pagos (stripe, mercadopago) — DESHABILITADOS hasta contar con credenciales.
    // Devuelven un 503 limpio dirigiendo al cliente a WhatsApp.
    Route::any('payments/{any}', function () {
        return response()->json([
            'success' => false,
            'message' => 'Pagos no disponibles aún. Contactanos por WhatsApp.',
            'whatsapp' => '+59168736384',
        ], 503);
    })->where('any', '.*');

    // Rutas de pago pendientes (reactivar cuando existan credenciales):
    // Route::post('/payments/stripe/intent', [PaymentController::class, 'stripeIntent']);
    // Route::post('/payments/stripe/confirm', [PaymentController::class, 'stripeConfirm']);
    // Route::post('/payments/mercadopago/create', [PaymentController::class, 'mercadopagoCreate']);

    // Cupones
    Route::post('/coupons/validate', [CouponController::class, 'validate']);

    // Webhooks de pasarelas
    Route::post('/webhooks/paypal', [PaymentController::class, 'paypalWebhook']);
    Route::post('/webhooks/stripe', [PaymentController::class, 'stripeWebhook']);
    Route::post('/webhooks/mercadopago', [PaymentController::class, 'mercadopagoWebhook']);

    /*
    |--------------------------------------------------------------------------
    | Rutas autenticadas (Sanctum)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Perfil y cuenta
        Route::get('/user/profile', [UserController::class, 'profile']);
        Route::put('/user/profile', [UserController::class, 'updateProfile']);
        Route::get('/user/orders', [UserController::class, 'orders']);
        Route::get('/user/orders/{id}', [OrderController::class, 'show']);
        Route::get('/user/favorites', [UserController::class, 'favorites']);
        Route::post('/user/favorites/{productId}', [UserController::class, 'toggleFavorite']);
        Route::get('/user/addresses', [UserController::class, 'addresses']);
        Route::post('/user/addresses', [UserController::class, 'storeAddress']);
        Route::delete('/user/addresses/{id}', [UserController::class, 'deleteAddress']);
        Route::get('/user/coupon', [UserController::class, 'myCoupon']);

        // Seguridad de la cuenta
        Route::put('/user/password', [UserController::class, 'changePassword']);
        Route::put('/user/email', [UserController::class, 'changeEmail']);
        Route::post('/user/dismiss-security-reminder', [UserController::class, 'dismissSecurityReminder']);

        // Carrito autenticado
        Route::post('/cart/merge', [CartController::class, 'merge']);

        // Reseñas
        Route::post('/products/{product:slug}/reviews', [ReviewController::class, 'store']);

        /*
        |----------------------------------------------------------------------
        | Admin o Empleado (role:admin,employee)
        |----------------------------------------------------------------------
        */
        Route::middleware('role:admin,employee')->prefix('admin')->group(function () {
            Route::get('/orders', [AdminOrderController::class, 'index']);
            Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
        });

        /*
        |----------------------------------------------------------------------
        | Solo Administrador (role:admin)
        |----------------------------------------------------------------------
        */
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
            Route::get('/dashboard/sales-chart', [DashboardController::class, 'salesChart']);
            Route::get('/dashboard/top-products', [DashboardController::class, 'topProducts']);

            Route::put('/orders/{id}/tracking', [AdminOrderController::class, 'updateTracking']);

            Route::get('/customers', [AdminCustomerController::class, 'index']);

            Route::post('/products', [AdminProductController::class, 'store']);
            Route::put('/products/{product}', [AdminProductController::class, 'update']);
            Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);
        });
    });
});
