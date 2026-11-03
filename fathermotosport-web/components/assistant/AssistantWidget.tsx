"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Image from "next/image";
import { useLocale, useTranslations } from "next-intl";
import { Link, usePathname } from "@/lib/i18n/navigation";
import { MessageCircle, Send, X, RotateCcw, ExternalLink } from "lucide-react";
import { useStoreSettings } from "@/components/layout/StoreSettingsProvider";
import { whatsappUrl } from "@/lib/api/storeSettings";
import { formatPrice } from "@/lib/utils";
import type { AssistantReply, ChatMessage, ProductCard } from "@/lib/assistant/types";

interface UiMessage extends ChatMessage {
  products?: ProductCard[];
  error?: boolean;
}

const STORAGE_KEY = "fms_assistant_v1";

/** Evento para analítica: lo recoge Google Tag Manager / GA4 si la tienda lo tiene instalado. */
function track(event: string, data: Record<string, unknown> = {}) {
  try {
    const w = window as unknown as { dataLayer?: Record<string, unknown>[] };
    w.dataLayer?.push({ event, ...data });
  } catch {
    /* sin analítica: no pasa nada */
  }
}

function ProductCardView({ p, onNavigate }: { p: ProductCard; onNavigate: () => void }) {
  const t = useTranslations("assistant");
  return (
    <Link
      href={`/product/${p.slug}`}
      onClick={() => {
        track("assistant_product_click", { product: p.slug });
        onNavigate();
      }}
      className="group flex gap-3 rounded-xl border border-white/10 bg-brand-dark p-2.5 transition hover:border-brand-red/60"
    >
      <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-white">
        {p.image ? (
          <Image src={p.image} alt={p.name} fill sizes="64px" className="object-contain p-1" unoptimized />
        ) : null}
      </div>
      <div className="min-w-0 flex-1">
        {p.brand ? <p className="text-[10px] font-semibold uppercase tracking-wider text-brand-gold">{p.brand}</p> : null}
        <p className="line-clamp-2 text-[13px] font-medium leading-snug text-brand-white">{p.name}</p>
        <div className="mt-1 flex flex-wrap items-baseline gap-x-2">
          <span className="text-sm font-bold text-brand-white">{formatPrice(p.price)}</span>
          {p.listPrice ? (
            <>
              <span className="text-xs text-brand-muted line-through">{formatPrice(p.listPrice)}</span>
              <span className="text-[11px] font-semibold text-brand-red">-{p.discountPercent}%</span>
            </>
          ) : null}
        </div>
        <p className="mt-0.5 text-[11px] text-brand-muted">
          {p.inStock ? (p.sizes.length ? `${t("sizes")}: ${p.sizes.join(", ")}` : "") : t("out_of_stock")}
        </p>
      </div>
      <ExternalLink className="mt-1 h-3.5 w-3.5 shrink-0 text-brand-muted transition group-hover:text-brand-red" aria-hidden />
    </Link>
  );
}

export function AssistantWidget() {
  const t = useTranslations("assistant");
  const locale = useLocale();
  const pathname = usePathname();
  const { whatsappDigits } = useStoreSettings();

  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState<UiMessage[]>([]);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);
  const bottomRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Recupera la conversación de esta pestaña (sessionStorage puede no existir: se ignora).
  useEffect(() => {
    try {
      const saved = sessionStorage.getItem(STORAGE_KEY);
      if (saved) setMessages(JSON.parse(saved) as UiMessage[]);
    } catch {
      /* ignorar */
    }
  }, []);

  useEffect(() => {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(messages.slice(-20)));
    } catch {
      /* ignorar */
    }
  }, [messages]);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth", block: "end" });
  }, [messages, loading, open]);

  useEffect(() => {
    if (open) inputRef.current?.focus();
  }, [open]);

  const send = useCallback(
    async (text: string) => {
      const content = text.trim();
      if (!content || loading) return;

      const next: UiMessage[] = [...messages.filter((m) => !m.error), { role: "user", content }];
      setMessages(next);
      setInput("");
      setLoading(true);
      track("assistant_message", { length: content.length });

      try {
        const res = await fetch("/api/assistant", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            locale,
            messages: next.map(({ role, content: c }) => ({ role, content: c })),
          }),
        });
        if (res.status === 429) throw new Error("rate");
        if (!res.ok) throw new Error("http");
        const data = (await res.json()) as AssistantReply;
        setMessages([...next, { role: "assistant", content: data.reply, products: data.products }]);
      } catch (e) {
        const key = e instanceof Error && e.message === "rate" ? "rate_limited" : "error";
        setMessages([...next, { role: "assistant", content: t(key), error: true }]);
      } finally {
        setLoading(false);
      }
    },
    [messages, loading, locale, t],
  );

  // Fuera del checkout: ahí el pago debe ser lo único en pantalla.
  if (pathname.startsWith("/checkout")) return null;

  const userTexts = messages.filter((m) => m.role === "user").map((m) => m.content);
  const summary = userTexts.slice(-3).join(" / ") || "—";
  const waLink = whatsappUrl(whatsappDigits, t("whatsapp_message", { summary }));

  const chips = [
    ["chip_helmets", "chip_helmets_q"],
    ["chip_gloves", "chip_gloves_q"],
    ["chip_shipping", "chip_shipping_q"],
    ["chip_payment", "chip_payment_q"],
    ["chip_returns", "chip_returns_q"],
  ] as const;

  return (
    <>
      {!open && (
        <button
          type="button"
          onClick={() => {
            setOpen(true);
            track("assistant_open");
          }}
          aria-label={t("open")}
          className="fixed bottom-4 right-4 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-brand-red text-white shadow-[0_8px_30px_rgba(232,0,29,0.45)] transition hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-white sm:bottom-6 sm:right-6"
        >
          <MessageCircle className="h-6 w-6" aria-hidden />
          <span className="absolute right-0 top-0 h-3 w-3 rounded-full border-2 border-brand-carbon bg-brand-gold" aria-hidden />
        </button>
      )}

      {open && (
        <section
          role="dialog"
          aria-label={t("title")}
          className="fixed inset-x-0 bottom-0 z-[55] flex h-[85dvh] flex-col overflow-hidden rounded-t-2xl border border-white/10 bg-brand-card shadow-2xl sm:inset-x-auto sm:bottom-6 sm:right-6 sm:h-[600px] sm:max-h-[calc(100dvh-3rem)] sm:w-[400px] sm:rounded-2xl"
        >
          <header className="flex items-center gap-3 border-b border-white/10 bg-brand-dark px-4 py-3">
            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-brand-red text-white">
              <MessageCircle className="h-4.5 w-4.5" aria-hidden />
            </span>
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-semibold text-brand-white">{t("title")}</p>
              <p className="truncate text-[11px] text-brand-muted">{t("subtitle")}</p>
            </div>
            {messages.length > 0 && (
              <button
                type="button"
                onClick={() => setMessages([])}
                aria-label={t("new_chat")}
                title={t("new_chat")}
                className="rounded-full p-2 text-brand-muted transition hover:bg-white/10 hover:text-brand-white"
              >
                <RotateCcw className="h-4 w-4" aria-hidden />
              </button>
            )}
            <button
              type="button"
              onClick={() => setOpen(false)}
              aria-label={t("close")}
              className="rounded-full p-2 text-brand-muted transition hover:bg-white/10 hover:text-brand-white"
            >
              <X className="h-5 w-5" aria-hidden />
            </button>
          </header>

          <div className="flex-1 space-y-3 overflow-y-auto px-4 py-4" aria-live="polite">
            <div className="max-w-[88%] rounded-2xl rounded-tl-sm bg-brand-dark px-3.5 py-2.5 text-sm leading-relaxed text-brand-white">
              {t("welcome")}
            </div>

            {messages.length === 0 && (
              <div className="flex flex-wrap gap-2 pt-1">
                {chips.map(([label, question]) => (
                  <button
                    key={label}
                    type="button"
                    onClick={() => send(t(question))}
                    className="rounded-full border border-white/15 px-3 py-1.5 text-xs text-brand-white transition hover:border-brand-red hover:text-brand-red"
                  >
                    {t(label)}
                  </button>
                ))}
              </div>
            )}

            {messages.map((m, i) => (
              <div key={i} className={m.role === "user" ? "flex justify-end" : "flex flex-col items-start gap-2"}>
                <div
                  className={
                    m.role === "user"
                      ? "max-w-[85%] whitespace-pre-line rounded-2xl rounded-tr-sm bg-brand-red px-3.5 py-2.5 text-sm leading-relaxed text-white"
                      : `max-w-[88%] whitespace-pre-line rounded-2xl rounded-tl-sm px-3.5 py-2.5 text-sm leading-relaxed ${
                          m.error ? "bg-brand-red/15 text-brand-white" : "bg-brand-dark text-brand-white"
                        }`
                  }
                >
                  {m.content}
                </div>
                {m.products && m.products.length > 0 && (
                  <div className="w-full space-y-2">
                    {m.products.map((p) => (
                      <ProductCardView key={p.slug} p={p} onNavigate={() => setOpen(false)} />
                    ))}
                  </div>
                )}
              </div>
            ))}

            {loading && (
              <div className="flex max-w-[88%] items-center gap-1.5 rounded-2xl rounded-tl-sm bg-brand-dark px-3.5 py-3" role="status">
                <span className="sr-only">{t("typing")}</span>
                {[0, 1, 2].map((d) => (
                  <span
                    key={d}
                    className="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-muted"
                    style={{ animationDelay: `${d * 150}ms` }}
                  />
                ))}
              </div>
            )}
            <div ref={bottomRef} />
          </div>

          <footer className="border-t border-white/10 bg-brand-dark px-3 pb-3 pt-2.5 [padding-bottom:max(0.75rem,env(safe-area-inset-bottom))]">
            {waLink && (
              <a
                href={waLink}
                target="_blank"
                rel="noopener noreferrer"
                onClick={() => track("assistant_whatsapp_click", { messages: messages.length })}
                className="mb-2.5 flex items-center justify-between gap-3 rounded-xl border border-[#25D366]/40 bg-[#25D366]/10 px-3 py-2 text-left transition hover:bg-[#25D366]/20"
              >
                <span className="min-w-0">
                  <span className="block text-[13px] font-semibold text-brand-white">{t("whatsapp")}</span>
                  <span className="block truncate text-[11px] text-brand-muted">{t("whatsapp_note")}</span>
                </span>
                <ExternalLink className="h-4 w-4 shrink-0 text-[#25D366]" aria-hidden />
              </a>
            )}
            <form
              onSubmit={(e) => {
                e.preventDefault();
                send(input);
              }}
              className="flex items-center gap-2"
            >
              <input
                ref={inputRef}
                value={input}
                onChange={(e) => setInput(e.target.value)}
                maxLength={500}
                placeholder={t("placeholder")}
                aria-label={t("placeholder")}
                className="min-w-0 flex-1 rounded-full border border-white/15 bg-brand-carbon px-4 py-2.5 text-base text-brand-white placeholder:text-brand-muted focus:border-brand-red focus:outline-none sm:text-sm"
              />
              <button
                type="submit"
                disabled={loading || !input.trim()}
                aria-label={t("send")}
                className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-red text-white transition enabled:hover:brightness-110 disabled:opacity-40"
              >
                <Send className="h-4 w-4" aria-hidden />
              </button>
            </form>
            <p className="mt-2 text-center text-[10px] text-brand-muted">{t("disclaimer")}</p>
          </footer>
        </section>
      )}
    </>
  );
}
