// ───────────────────────── Entidades de dominio ─────────────────────────

export interface Role {
  id: number;
  name: string;
  slug: string;
}

export interface User {
  id: string;
  first_name: string | null;
  last_name: string | null;
  full_name: string;
  email: string;
  phone: string | null;
  birth_date?: string | null;
  avatar: string | null;
  status: "active" | "inactive" | "banned";
  loyalty_discount_used: boolean;
  email_verified_at: string | null;
  last_password_change?: string | null;
  security_reminder_dismissed_at?: string | null;
  needs_security_reminder?: boolean;
  role?: Role;
  created_at?: string;
}

export interface Brand {
  id: number;
  name: string;
  slug: string;
  logo_url: string | null;
  country?: string | null;
  description?: string | null;
  is_active?: boolean;
  products_count?: number;
}

export interface Category {
  id: number;
  parent_id: number | null;
  name: string;
  slug: string;
  icon: string | null;
  image_url: string | null;
  description: string | null;
  is_active: boolean;
  sort_order: number;
  children?: Category[];
  products_count?: number;
}

export interface ProductImage {
  id: string;
  url: string;
  thumbnail_url: string | null;
  sort_order: number;
  is_primary: boolean;
}

export interface ProductVariant {
  id: string;
  product_id: string;
  color: string | null;
  size: string | null;
  finish: string | null;
  sku: string;
  stock: number;
  price: string | null;
  in_stock: boolean;
  is_active: boolean;
}

export interface Product3DModel {
  id: string;
  file_glb_url: string;
  file_draco_url: string | null;
  preview_url: string | null;
  file_size_kb: number | null;
  version: string;
}

export interface VisorColor {
  id: string;
  name: string;
  hex_color: string;
  image_url: string | null;
  sort_order: number;
}

export interface Review {
  id: string;
  rating: number;
  title: string | null;
  comment: string | null;
  is_approved: boolean;
  user?: { id: string; name: string; avatar: string | null };
  product?: { id: string; name: string; slug: string };
  created_at: string;
}

export interface ReviewsSummary {
  average: number;
  total: number;
  distribution: { rating: number; count: number; percent: number }[];
}

export interface Product {
  id: string;
  sku: string;
  barcode: string | null;
  name: string;
  slug: string;
  short_description: string | null;
  description: string | null;
  price: string;
  sale_price: string | null;
  cost?: string | null;
  weight: string | null;
  minimum_stock: number;
  is_active: boolean;
  is_featured: boolean;
  is_new: boolean;
  is_popular: boolean;
  specs: Record<string, string> | null;
  certification: string | null;
  spin_url?: string | null;
  discount_percent: number;
  primary_image: string | null;
  has_3d_model: boolean;
  in_stock: boolean;
  total_stock?: number;
  brand?: Pick<Brand, "id" | "name" | "slug" | "logo_url">;
  category?: Pick<Category, "id" | "name" | "slug" | "icon">;
  images?: ProductImage[];
  variants?: ProductVariant[];
  model_3d?: Product3DModel | null;
  visor_colors?: VisorColor[];
  reviews?: Review[];
  reviews_count?: number;
  rating_avg?: number;
  created_at?: string;
}

export interface PostAuthor {
  id: string;
  name: string;
}

export interface Post {
  id: string;
  title: string;
  slug: string;
  excerpt: string | null;
  content: string;
  cover_image: string | null;
  author?: PostAuthor;
  published_at: string | null;
  created_at?: string;
}

export interface Address {
  id: string;
  user_id: string | null;
  full_name: string;
  phone: string | null;
  country: string;
  state: string | null;
  city: string | null;
  postal_code: string | null;
  address_line: string;
  reference: string | null;
  is_default: boolean;
}

export interface OrderItem {
  id: string;
  product_variant_id: string;
  quantity: number;
  unit_price: string;
  subtotal: string;
  size: string | null;
  color: string | null;
  variant?: {
    id: string;
    sku: string;
    product?: { id: string; name: string; slug: string; primary_image: string | null };
  };
}

export type OrderStatus = "pending" | "processing" | "shipped" | "delivered" | "cancelled";
export type PaymentStatus = "pending" | "paid" | "failed" | "refunded";
export type ShippingStatus = "pending" | "preparing" | "in_transit" | "delivered";
export type PaymentMethod = "paypal" | "stripe" | "mercadopago";

export interface Order {
  id: string;
  order_number: string;
  user_id: string | null;
  guest_email: string | null;
  status: OrderStatus;
  subtotal: string;
  discount: string;
  shipping: string;
  tax: string;
  total: string;
  payment_status: PaymentStatus;
  shipping_status: ShippingStatus;
  payment_method: PaymentMethod | null;
  country: string;
  notes: string | null;
  items?: OrderItem[];
  address?: Address;
  payment?: { id: string; provider: string; status: string; amount: string } | null;
  shipment?: {
    tracking_number: string | null;
    carrier: string | null;
    status: string;
    shipped_at: string | null;
    delivered_at: string | null;
  } | null;
  created_at?: string;
}

export interface CartItem {
  id: string;
  product_variant_id: string;
  quantity: number;
  price: string;
  subtotal: string;
  variant?: {
    id: string;
    sku: string;
    color: string | null;
    size: string | null;
    stock: number;
    in_stock: boolean;
    product?: { id: string; name: string; slug: string; primary_image: string | null };
  };
}

export interface Cart {
  id: string;
  user_id: string | null;
  session_id: string;
  expires_at: string | null;
  items: CartItem[];
  items_count: number;
  subtotal: string;
  shipping: string;
  total: string;
}

export interface Coupon {
  code: string;
  type: "percentage" | "fixed";
  value: string;
}

// ───────────────────────── Respuestas de la API ─────────────────────────

export interface ApiResponse<T> {
  data: T;
}

export interface PaginatedResponse<T> {
  data: T[];
  links: { first: string; last: string; prev: string | null; next: string | null };
  meta: {
    current_page: number;
    from: number;
    last_page: number;
    path: string;
    per_page: number;
    to: number;
    total: number;
  };
}

export interface ProductFilters {
  category?: string | number;
  brand?: string | number;
  min_price?: number;
  max_price?: number;
  search?: string;
  sort?: "price_asc" | "price_desc" | "name_asc" | "newest" | "popular";
  is_new?: boolean;
  is_featured?: boolean;
  is_popular?: boolean;
  per_page?: number;
  page?: number;
}

export interface AuthResponse {
  user: User;
  token: string;
  welcome_coupon?: { code: string; value: string };
}
