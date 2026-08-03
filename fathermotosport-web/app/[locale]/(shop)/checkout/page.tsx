"use client";

import { useTranslations } from "next-intl";
import { Link } from "@/lib/i18n/navigation";
import { useCartStore } from "@/store/cartStore";
import { CheckoutForm } from "@/components/checkout/CheckoutForm";
import { Button } from "@/components/ui/Button";
import { Spinner } from "@/components/ui/Spinner";
import { useMounted } from "@/lib/hooks/useMounted";

export default function CheckoutPage() {
  const t = useTranslations("checkout");
  const mounted = useMounted();
  const items = useCartStore((s) => s.items);

  if (!mounted) {
    return (
      <div className="flex min-h-[70vh] items-center justify-center pt-20">
        <Spinner size={32} />
      </div>
    );
  }

  if (!items.length) {
    return (
      <div className="flex min-h-[70vh] flex-col items-center justify-center gap-4 px-4 pt-20 text-center">
        <h1 className="text-2xl font-bold text-brand-white">{t("no_products_title")}</h1>
        <Link href="/catalog">
          <Button variant="primary">{t("go_to_catalog")}</Button>
        </Link>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-6xl px-4 pb-16 pt-24 sm:px-6">
      <h1 className="mb-8 text-3xl font-extrabold text-brand-white">{t("finalize_purchase")}</h1>
      <CheckoutForm />
    </div>
  );
}
