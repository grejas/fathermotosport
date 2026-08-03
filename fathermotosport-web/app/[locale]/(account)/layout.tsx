"use client";

import { useEffect } from "react";
import { useTranslations } from "next-intl";
import { Link, usePathname, useRouter } from "@/lib/i18n/navigation";
import { Heart, Package, User as UserIcon } from "lucide-react";
import { useAuthStore } from "@/store/authStore";
import { useMounted } from "@/lib/hooks/useMounted";
import { Spinner } from "@/components/ui/Spinner";
import { SecurityReminderBanner } from "@/components/account/SecurityReminderBanner";
import { cn } from "@/lib/utils";

export default function AccountLayout({ children }: { children: React.ReactNode }) {
  const t = useTranslations("orders");
  const tNav = useTranslations("nav");
  const router = useRouter();
  const pathname = usePathname();
  const mounted = useMounted();
  const isAuth = useAuthStore((s) => s.isAuth);

  const nav = [
    { href: "/profile", label: tNav("profile"), icon: UserIcon },
    { href: "/orders", label: t("my_orders"), icon: Package },
    { href: "/favorites", label: tNav("favorites"), icon: Heart },
  ];

  useEffect(() => {
    if (mounted && !isAuth) router.replace(`/login?redirect=${pathname}`);
  }, [mounted, isAuth, router, pathname]);

  // Hasta el montaje (SSR + primera hidratación) mostramos el spinner para que
  // el HTML del servidor y del cliente coincidan; luego se evalúa la sesión.
  if (!mounted || !isAuth) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <Spinner size={32} />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-6xl px-4 pb-16 pt-24 sm:px-6">
      <SecurityReminderBanner />
      <div className="grid gap-8 lg:grid-cols-[220px_1fr]">
        <aside className="space-y-1">
          {nav.map((n) => {
            const Icon = n.icon;
            const active = pathname.startsWith(n.href);
            return (
              <Link
                key={n.href}
                href={n.href}
                className={cn(
                  "flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition",
                  active
                    ? "bg-brand-red/10 text-brand-red"
                    : "text-brand-muted hover:bg-white/5 hover:text-brand-white"
                )}
              >
                <Icon size={18} /> {n.label}
              </Link>
            );
          })}
        </aside>
        <div>{children}</div>
      </div>
    </div>
  );
}
