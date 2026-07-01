import Link from "next/link";
import { Share2, AtSign, Music2, MessageCircle } from "lucide-react";

const whatsapp = process.env.NEXT_PUBLIC_WHATSAPP ?? "+59168736384";

const categoryLinks = [
  { label: "Cascos", href: "/catalog?category=1" },
  { label: "Guantes", href: "/catalog?category=2" },
  { label: "Botas", href: "/catalog?category=3" },
  { label: "Chamarras", href: "/catalog?category=5" },
  { label: "Repuestos", href: "/catalog?category=6" },
];

export function Footer() {
  return (
    <footer className="border-t border-white/10 bg-brand-dark">
      <div className="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-4">
        <div>
          <Link href="/" className="text-xl font-extrabold">
            <span className="text-brand-red">Father</span>
            <span className="text-brand-white">Motosport</span>
          </Link>
          <p className="mt-3 max-w-xs text-sm text-brand-muted">
            Equipamiento premium para motociclistas. Cascos, guantes, botas y más, con envío gratis en
            Bolivia y Brasil.
          </p>
        </div>

        <div>
          <h4 className="mb-3 text-sm font-bold uppercase tracking-wide text-brand-white">Categorías</h4>
          <ul className="space-y-2">
            {categoryLinks.map((l) => (
              <li key={l.label}>
                <Link href={l.href} className="text-sm text-brand-muted transition hover:text-brand-red">
                  {l.label}
                </Link>
              </li>
            ))}
          </ul>
        </div>

        <div>
          <h4 className="mb-3 text-sm font-bold uppercase tracking-wide text-brand-white">Pagos</h4>
          <div className="flex flex-wrap gap-2">
            {["PayPal", "Visa", "MasterCard", "MercadoPago"].map((m) => (
              <span
                key={m}
                className="rounded-md border border-white/10 bg-brand-card px-2.5 py-1 text-xs text-brand-muted"
              >
                {m}
              </span>
            ))}
          </div>
        </div>

        <div>
          <h4 className="mb-3 text-sm font-bold uppercase tracking-wide text-brand-white">Contacto</h4>
          <a
            href={`https://wa.me/${whatsapp.replace(/[^0-9]/g, "")}`}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-2 text-sm text-brand-muted transition hover:text-cat-boots"
          >
            <MessageCircle size={16} /> WhatsApp {whatsapp}
          </a>
          <div className="mt-4 flex gap-3">
            <a href="#" aria-label="Facebook" className="text-brand-muted transition hover:text-brand-red">
              <Share2 size={20} />
            </a>
            <a href="#" aria-label="Instagram" className="text-brand-muted transition hover:text-brand-red">
              <AtSign size={20} />
            </a>
            <a href="#" aria-label="YouTube" className="text-brand-muted transition hover:text-brand-red">
              <Music2 size={20} />
            </a>
          </div>
        </div>
      </div>

      <div className="border-t border-white/10 py-5 text-center text-xs text-brand-muted">
        © {new Date().getFullYear()} FatherMotoSport. Todos los derechos reservados.
      </div>
    </footer>
  );
}
