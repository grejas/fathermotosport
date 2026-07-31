import { createNavigation } from "next-intl/navigation";

export const locales = ["es", "pt", "en"] as const;
export const defaultLocale = "es" as const;

export const { Link, redirect, usePathname, useRouter, getPathname } = createNavigation({
  locales,
  defaultLocale,
  localePrefix: "as-needed",
});
