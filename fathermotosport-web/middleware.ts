import createMiddleware from "next-intl/middleware";

export default createMiddleware({
  locales: ["es", "pt", "en"],
  defaultLocale: "es",
  localePrefix: "as-needed",
});

export const config = {
  matcher: ["/((?!api|_next|.*\\..*).*)"],
};
