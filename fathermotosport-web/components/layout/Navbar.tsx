"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { Heart, Menu, Search, User, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { useAuthStore } from "@/store/authStore";
import { useFavoritesStore } from "@/store/favoritesStore";
import { useMounted } from "@/lib/hooks/useMounted";
import { LanguageSelector } from "@/components/ui/LanguageSelector";
import { CartIcon } from "./CartIcon";

const links = [
  { label: "Cascos", href: "/catalog?category=1" },
  { label: "Guantes", href: "/catalog?category=2" },
  { label: "Botas", href: "/catalog?category=3" },
  { label: "Chamarras", href: "/catalog?category=5" },
  { label: "Repuestos", href: "/catalog?category=6" },
  { label: "Marcas", href: "/catalog" },
];

export function Navbar() {
  const pathname = usePathname();
  const router = useRouter();
  const isHome = pathname === "/";
  const [scrolled, setScrolled] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [query, setQuery] = useState("");

  const mounted = useMounted();
  const isAuthRaw = useAuthStore((s) => s.isAuth);
  const favCountRaw = useFavoritesStore((s) => s.count());
  // En el primer render (SSR + hidratación) usamos los valores por defecto;
  // los datos de localStorage se aplican tras el montaje para evitar mismatch.
  const isAuth = mounted && isAuthRaw;
  const favCount = mounted ? favCountRaw : 0;

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 20);
    onScroll();
    window.addEventListener("scroll", onScroll);
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  const onSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (query.trim()) {
      router.push(`/catalog?search=${encodeURIComponent(query.trim())}`);
      setSearchOpen(false);
      setMobileOpen(false);
    }
  };

  const solid = scrolled || !isHome;

  return (
    <header
      className={cn(
        "fixed inset-x-0 top-0 z-50 transition-all duration-300",
        solid ? "border-b border-white/10 bg-brand-carbon/85 backdrop-blur-md" : "bg-transparent"
      )}
    >
      <nav className="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6">
        <Link href="/" className="flex items-center gap-2">
          <span className="text-xl font-extrabold tracking-wide ">
            <span className="text-brand-red">Father</span>
            <span className="text-brand-white">Motosport</span>
          </span>
        </Link>

        <ul className="ml-6 hidden items-center gap-5 lg:flex">
          {links.map((l) => (
            <li key={l.label}>
              <Link
                href={l.href}
                className="text-sm font-medium text-brand-white/80 transition hover:text-brand-red"
              >
                {l.label}
              </Link>
            </li>
          ))}
        </ul>

        <div className="ml-auto flex items-center gap-1">
          <form onSubmit={onSearch} className="hidden items-center sm:flex">
            <div
              className={cn(
                "flex items-center overflow-hidden rounded-lg transition-all duration-300",
                searchOpen ? "w-56 border border-white/10 bg-brand-dark px-2" : "w-9"
              )}
            >
              {searchOpen && (
                <input
                  autoFocus
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  placeholder="Buscar productos…"
                  className="w-full bg-transparent py-2 text-sm text-brand-white outline-none"
                />
              )}
              <button
                type="button"
                onClick={() => setSearchOpen((v) => !v)}
                className="rounded-lg p-2 text-brand-white transition hover:bg-white/10"
                aria-label="Buscar"
              >
                <Search size={20} />
              </button>
            </div>
          </form>

          <Link
            href="/favorites"
            className="relative rounded-lg p-2 text-brand-white transition hover:bg-white/10"
            aria-label="Favoritos"
          >
            <Heart size={22} />
            {favCount > 0 && (
              <span className="absolute -right-1 -top-1 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-brand-gold px-1 text-[10px] font-bold text-brand-carbon">
                {favCount}
              </span>
            )}
          </Link>

          <LanguageSelector />

          <CartIcon />

          <Link
            href={isAuth ? "/profile" : "/login"}
            className="ml-1 hidden items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-brand-white transition hover:bg-white/10 sm:flex"
          >
            <User size={18} />
            {isAuth ? "Mi cuenta" : "Ingresar"}
          </Link>

          <button
            onClick={() => setMobileOpen((v) => !v)}
            className="rounded-lg p-2 text-brand-white transition hover:bg-white/10 lg:hidden"
            aria-label="Menú"
          >
            {mobileOpen ? <X size={22} /> : <Menu size={22} />}
          </button>
        </div>
      </nav>

      {mobileOpen && (
        <div className="border-t border-white/10 bg-brand-carbon/95 px-4 py-4 lg:hidden">
          <form onSubmit={onSearch} className="mb-3 flex items-center gap-2 rounded-lg border border-white/10 bg-brand-dark px-3">
            <Search size={18} className="text-brand-muted" />
            <input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Buscar…"
              className="w-full bg-transparent py-2 text-sm text-brand-white outline-none"
            />
          </form>
          <ul className="flex flex-col gap-1">
            {links.map((l) => (
              <li key={l.label}>
                <Link
                  href={l.href}
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-lg px-3 py-2 text-sm font-medium text-brand-white/90 hover:bg-white/10"
                >
                  {l.label}
                </Link>
              </li>
            ))}
            <li>
              <Link
                href={isAuth ? "/profile" : "/login"}
                onClick={() => setMobileOpen(false)}
                className="block rounded-lg px-3 py-2 text-sm font-semibold text-brand-red hover:bg-white/10"
              >
                {isAuth ? "Mi cuenta" : "Ingresar"}
              </Link>
            </li>
          </ul>
        </div>
      )}
    </header>
  );
}
