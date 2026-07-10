import Link from "next/link";
import { MessageCircle } from "lucide-react";

const whatsapp = process.env.NEXT_PUBLIC_WHATSAPP ?? "+59168736384";

// Redes sociales — URLs reales (sobreescribibles por env). Íconos de marca en SVG.
const socialLinks = [
  {
    label: "Facebook",
    href: process.env.NEXT_PUBLIC_FACEBOOK ?? "https://facebook.com/Fathersonimport",
    path: "M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12z",
  },
  {
    label: "Instagram",
    href: process.env.NEXT_PUBLIC_INSTAGRAM ?? "https://instagram.com/fathermotosport",
    path: "M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41 1.27-.06 1.65-.07 4.85-.07zm0 1.62c-3.15 0-3.52.01-4.76.07-.9.04-1.39.19-1.71.32-.43.17-.74.37-1.06.69-.32.32-.52.63-.69 1.06-.13.32-.28.81-.32 1.71-.06 1.24-.07 1.61-.07 4.76s.01 3.52.07 4.76c.04.9.19 1.39.32 1.71.17.43.37.74.69 1.06.32.32.63.52 1.06.69.32.13.81.28 1.71.32 1.24.06 1.61.07 4.76.07s3.52-.01 4.76-.07c.9-.04 1.39-.19 1.71-.32.43-.17.74-.37 1.06-.69.32-.32.52-.63.69-1.06.13-.32.28-.81.32-1.71.06-1.24.07-1.61.07-4.76s-.01-3.52-.07-4.76c-.04-.9-.19-1.39-.32-1.71a2.85 2.85 0 0 0-.69-1.06 2.85 2.85 0 0 0-1.06-.69c-.32-.13-.81-.28-1.71-.32-1.24-.06-1.61-.07-4.76-.07zm0 2.76a5.46 5.46 0 1 1 0 10.92 5.46 5.46 0 0 1 0-10.92zm0 9a3.54 3.54 0 1 0 0-7.08 3.54 3.54 0 0 0 0 7.08zm6.95-9.22a1.28 1.28 0 1 1-2.56 0 1.28 1.28 0 0 1 2.56 0z",
  },
  {
    label: "YouTube",
    href: process.env.NEXT_PUBLIC_YOUTUBE ?? "https://youtube.com/@FatherMotoSports",
    path: "M23.5 6.2a3.02 3.02 0 0 0-2.12-2.14C19.5 3.55 12 3.55 12 3.55s-7.5 0-9.38.51A3.02 3.02 0 0 0 .5 6.2 31.5 31.5 0 0 0 0 12a31.5 31.5 0 0 0 .5 5.8 3.02 3.02 0 0 0 2.12 2.14c1.88.51 9.38.51 9.38.51s7.5 0 9.38-.51a3.02 3.02 0 0 0 2.12-2.14A31.5 31.5 0 0 0 24 12a31.5 31.5 0 0 0-.5-5.8zM9.6 15.6V8.4l6.2 3.6-6.2 3.6z",
  },
  {
    label: "TikTok",
    href: process.env.NEXT_PUBLIC_TIKTOK ?? "https://tiktok.com/@fathermotosport",
    path: "M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.69a8.18 8.18 0 0 0 4.78 1.52V6.76a4.85 4.85 0 0 1-1.01-.07z",
  },
];

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
            Equipamiento premium para motociclistas. Cascos, guantes, botas y más, con envío gratis.
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
            {socialLinks.map((s) => (
              <a
                key={s.label}
                href={s.href}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={s.label}
                className="flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-white/5 text-white/50 transition-all duration-200 hover:border-white/30 hover:bg-white/10 hover:text-white"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                  <path d={s.path} />
                </svg>
              </a>
            ))}
          </div>
        </div>
      </div>

      <div className="border-t border-white/10 py-5 text-center text-xs text-brand-muted">
        © {new Date().getFullYear()} FatherMotoSport. Todos los derechos reservados.
      </div>
    </footer>
  );
}
