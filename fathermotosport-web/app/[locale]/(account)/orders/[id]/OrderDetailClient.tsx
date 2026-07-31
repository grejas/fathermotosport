"use client";

import Image from "next/image";
import { useOrder } from "@/lib/hooks/useOrders";
import { Badge } from "@/components/ui/Badge";
import { Skeleton } from "@/components/ui/Skeleton";
import { formatPrice, formatDate, orderStatusLabels, getImageUrl } from "@/lib/utils";

export function OrderDetailClient({ id }: { id: string }) {
  const { data: order, isLoading } = useOrder(id);

  if (isLoading) return <Skeleton className="h-96 w-full rounded-2xl" />;
  if (!order) return <p className="text-brand-muted">Pedido no encontrado.</p>;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-extrabold text-brand-white">{order.order_number}</h1>
          <p className="text-sm text-brand-muted">{order.created_at ? formatDate(order.created_at) : ""}</p>
        </div>
        <Badge variant={order.status === "delivered" ? "green" : "gold"}>
          {orderStatusLabels[order.status]}
        </Badge>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <div className="rounded-xl border border-white/10 bg-brand-card p-4">
          <p className="text-xs uppercase text-brand-muted">Pago</p>
          <p className="font-semibold capitalize text-brand-white">{order.payment_status}</p>
          <p className="text-xs text-brand-muted">{order.payment_method ?? "—"}</p>
        </div>
        <div className="rounded-xl border border-white/10 bg-brand-card p-4">
          <p className="text-xs uppercase text-brand-muted">Envío</p>
          <p className="font-semibold capitalize text-brand-white">{order.shipping_status}</p>
          {order.shipment?.tracking_number && (
            <p className="text-xs text-brand-muted">Tracking: {order.shipment.tracking_number}</p>
          )}
        </div>
        <div className="rounded-xl border border-white/10 bg-brand-card p-4">
          <p className="text-xs uppercase text-brand-muted">Total</p>
          <p className="text-lg font-bold text-brand-white">{formatPrice(order.total)}</p>
        </div>
      </div>

      <div className="rounded-2xl border border-white/10 bg-brand-card p-5">
        <h3 className="mb-4 font-bold text-brand-white">Productos</h3>
        <div className="space-y-3">
          {order.items?.map((item) => (
            <div key={item.id} className="flex gap-3 border-b border-white/[0.06] pb-3 last:border-0">
              <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-brand-dark">
                {item.variant?.product?.primary_image && (
                  <Image src={getImageUrl(item.variant.product.primary_image)} alt="" fill className="object-cover" sizes="64px" />
                )}
              </div>
              <div className="flex-1">
                <p className="text-sm font-semibold text-brand-white">
                  {item.variant?.product?.name ?? "Producto"}
                </p>
                <p className="text-xs text-brand-muted">
                  {[item.size, item.color].filter(Boolean).join(" · ")} · x{item.quantity}
                </p>
              </div>
              <span className="text-sm font-bold text-brand-white">{formatPrice(item.subtotal)}</span>
            </div>
          ))}
        </div>
      </div>

      {order.address && (
        <div className="rounded-2xl border border-white/10 bg-brand-card p-5">
          <h3 className="mb-2 font-bold text-brand-white">Dirección de entrega</h3>
          <p className="text-sm text-brand-muted">
            {order.address.full_name} · {order.address.phone}
            <br />
            {order.address.address_line}, {order.address.city}, {order.address.country}
            {order.address.reference && <> · {order.address.reference}</>}
          </p>
        </div>
      )}
    </div>
  );
}
