"use client";

import { useTranslations } from "next-intl";
import { Link } from "@/lib/i18n/navigation";
import { ChevronRight, Package } from "lucide-react";
import { useMyOrders } from "@/lib/hooks/useOrders";
import { Badge } from "@/components/ui/Badge";
import { Skeleton } from "@/components/ui/Skeleton";
import { formatPrice, formatDate, orderStatusLabels } from "@/lib/utils";
import type { OrderStatus } from "@/lib/types";

const badgeVariant: Record<OrderStatus, "red" | "gold" | "green" | "gray"> = {
  pending: "gold",
  processing: "gold",
  shipped: "gray",
  delivered: "green",
  cancelled: "red",
};

export default function OrdersPage() {
  const t = useTranslations("orders");
  const { data, isLoading } = useMyOrders();
  const orders = data?.data ?? [];

  return (
    <div>
      <h1 className="mb-6 text-2xl font-extrabold text-brand-white">{t("my_orders")}</h1>

      {isLoading ? (
        <div className="space-y-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-20 w-full rounded-2xl" />
          ))}
        </div>
      ) : orders.length === 0 ? (
        <div className="flex flex-col items-center gap-3 rounded-2xl border border-white/10 bg-brand-card py-16 text-brand-muted">
          <Package size={48} className="opacity-40" />
          <p>{t("empty")}</p>
        </div>
      ) : (
        <div className="space-y-3">
          {orders.map((o) => (
            <Link
              key={o.id}
              href={`/orders/${o.id}`}
              className="flex items-center justify-between rounded-2xl border border-white/[0.06] bg-brand-card p-5 transition hover:border-white/20"
            >
              <div>
                <p className="font-bold text-brand-white">{o.order_number}</p>
                <p
                  className="text-sm text-brand-muted"
                  aria-label={o.created_at ? `${t("date")}: ${formatDate(o.created_at)}` : undefined}
                >
                  {o.created_at ? formatDate(o.created_at) : ""}
                </p>
              </div>
              <div className="flex items-center gap-4">
                <span aria-label={`${t("status")}: ${orderStatusLabels[o.status]}`}>
                  <Badge variant={badgeVariant[o.status]}>{orderStatusLabels[o.status]}</Badge>
                </span>
                <span className="font-bold text-brand-white" aria-label={`${t("total")}: ${formatPrice(o.total)}`}>
                  {formatPrice(o.total)}
                </span>
                <ChevronRight size={18} className="text-brand-muted" />
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
