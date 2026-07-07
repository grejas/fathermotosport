<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Productos — filtros más usados en catálogo y panel
        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'is_featured', 'created_at'], 'products_active_featured_created_idx');
            $table->index(['brand_id', 'category_id', 'is_active'], 'products_brand_category_active_idx');
        });

        // Pedidos — filtros del panel admin
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'payment_status', 'created_at'], 'orders_status_payment_created_idx');
            $table->index(['user_id', 'created_at'], 'orders_user_created_idx');
            $table->index(['country', 'created_at'], 'orders_country_created_idx');
        });

        // Variantes — stock y disponibilidad
        Schema::table('product_variants', function (Blueprint $table) {
            $table->index(['product_id', 'is_active', 'stock'], 'variants_product_active_stock_idx');
        });

        // Imágenes — carga de galería / imagen principal
        Schema::table('product_images', function (Blueprint $table) {
            $table->index(['product_id', 'is_primary', 'sort_order'], 'images_product_primary_sort_idx');
        });

        // Usuarios — búsquedas por rol/estado
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role_id', 'status', 'created_at'], 'users_role_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_active_featured_created_idx');
            $table->dropIndex('products_brand_category_active_idx');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_payment_created_idx');
            $table->dropIndex('orders_user_created_idx');
            $table->dropIndex('orders_country_created_idx');
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('variants_product_active_stock_idx');
        });
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex('images_product_primary_sort_idx');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_status_created_idx');
        });
    }
};
