import { Truck, ShieldCheck, Lock, MessageCircle } from "lucide-react";

const benefits = [
  { icon: Truck, title: "Envío gratis", desc: "Bolivia y Brasil" },
  { icon: ShieldCheck, title: "Garantía", desc: "Productos originales" },
  { icon: Lock, title: "Pago seguro", desc: "Encriptación SSL" },
  { icon: MessageCircle, title: "Soporte", desc: "WhatsApp directo" },
];

export function FooterStrip() {
  return (
    <section className="mx-auto max-w-7xl px-4 py-12 sm:px-6">
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {benefits.map((b) => {
          const Icon = b.icon;
          return (
            <div
              key={b.title}
              className="flex items-center gap-3 rounded-2xl border border-white/[0.06] bg-brand-card p-5"
            >
              <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-red/10 text-brand-red">
                <Icon size={22} />
              </span>
              <div>
                <p className="text-sm font-bold text-brand-white">{b.title}</p>
                <p className="text-xs text-brand-muted">{b.desc}</p>
              </div>
            </div>
          );
        })}
      </div>
    </section>
  );
}
