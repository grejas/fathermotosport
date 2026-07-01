const tokens = [
  "Envío gratis",
  "PayPal",
  "Stripe",
  "MercadoPago",
  "Cascos ECE 22.06",
  "AGV",
  "Shoei",
  "Shark",
];

export function MarqueeBar() {
  const row = [...tokens, ...tokens];
  return (
    <div className="overflow-hidden border-y border-brand-red/20 bg-brand-red/[0.07] py-3">
      <div className="flex w-max animate-marquee gap-8 whitespace-nowrap">
        {row.map((t, i) => (
          <span key={i} className="flex items-center gap-8 text-sm font-semibold text-brand-white/80">
            {t}
            <span className="text-brand-red">·</span>
          </span>
        ))}
      </div>
    </div>
  );
}
