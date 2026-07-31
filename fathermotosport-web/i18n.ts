import { getRequestConfig } from "next-intl/server";

const locales = ["es", "pt", "en"];

export default getRequestConfig(async ({ requestLocale }) => {
  let locale = await requestLocale;
  if (!locale || !locales.includes(locale)) {
    locale = "es";
  }

  return {
    locale,
    messages: (await import(`./messages/${locale}.json`)).default,
  };
});
