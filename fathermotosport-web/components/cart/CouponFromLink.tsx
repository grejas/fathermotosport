"use client";

import { useEffect } from "react";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { Tag } from "lucide-react";
import { Link } from "@/lib/i18n/navigation";
import { useAuthStore } from "@/store/authStore";
import { useCartStore } from "@/store/cartStore";

/**
 * ?cupon=CODIGO en el carrito (el botón de un correo): el código se guarda y se aplica
 * en el carrito y en el pago. Los cupones personales exigen la sesión de su dueño: sin
 * sesión se pide iniciarla y, al volver al carrito, se aplica.
 */
export function CouponFromLink() {
  const t = useTranslations("cart");
  const params = useSearchParams();
  const isAuth = useAuthStore((s) => s.isAuth);
  const pendingCoupon = useCartStore((s) => s.pendingCoupon);
  const setPendingCoupon = useCartStore((s) => s.setPendingCoupon);
  const desdeEnlace = params.get("cupon");

  useEffect(() => {
    if (desdeEnlace?.trim()) setPendingCoupon(desdeEnlace);
  }, [desdeEnlace, setPendingCoupon]);

  if (!pendingCoupon) return null;

  return (
    <div
      role="status"
      className="mx-auto mb-6 flex max-w-2xl items-start gap-3 rounded-xl border border-brand-gold/30 bg-brand-gold/5 px-4 py-3 text-left text-sm text-brand-muted"
    >
      <Tag size={18} className="mt-0.5 shrink-0 text-brand-gold" />
      {isAuth ? (
        <p>{t("coupon_from_link", { code: pendingCoupon })}</p>
      ) : (
        <p>
          {t("coupon_login_needed", { code: pendingCoupon })}{" "}
          <Link href="/login?redirect=/cart" className="font-semibold text-brand-red hover:underline">
            {t("coupon_login")}
          </Link>
        </p>
      )}
    </div>
  );
}
