"use client";

import { useEffect, useRef } from "react";
import Script from "next/script";
import { usePathname, useSearchParams } from "next/navigation";
import { TIKTOK_PIXEL_ID, tiktokPage, tiktokPixelEnabled } from "@/lib/analytics/tiktok";
import { META_PIXEL_ID, metaPageView, metaPixelEnabled } from "@/lib/analytics/meta";
import { GA4_ID, ga4Enabled } from "@/lib/analytics/ga4";

/**
 * Carga los códigos base de TikTok, Meta y Google Analytics 4. Cada uno se carga solo si su
 * variable de entorno tiene un ID válido:
 *   NEXT_PUBLIC_TIKTOK_PIXEL_ID · NEXT_PUBLIC_META_PIXEL_ID · NEXT_PUBLIC_GA4_ID
 *
 * Vistas de página: los códigos base registran la primera. Las siguientes (el sitio navega
 * sin recargar) las envía RouteChangeTracker a TikTok y Meta; GA4 las detecta solo con su
 * medición mejorada.
 */
export function AnalyticsScripts() {
  if (!tiktokPixelEnabled && !metaPixelEnabled && !ga4Enabled) return null;

  return (
    <>
      {tiktokPixelEnabled && (
        <Script id="tiktok-pixel" strategy="afterInteractive">
          {`!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script");n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};
  ttq.load('${TIKTOK_PIXEL_ID}');
  ttq.page();
}(window, document, 'ttq');`}
        </Script>
      )}

      {metaPixelEnabled && (
        <Script id="meta-pixel" strategy="afterInteractive">
          {`!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '${META_PIXEL_ID}');
fbq('track', 'PageView');`}
        </Script>
      )}

      {ga4Enabled && (
        <>
          <Script id="ga4-lib" src={`https://www.googletagmanager.com/gtag/js?id=${GA4_ID}`} strategy="afterInteractive" />
          <Script id="ga4-config" strategy="afterInteractive">
            {`window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '${GA4_ID}');`}
          </Script>
        </>
      )}

      <RouteChangeTracker />
    </>
  );
}

function RouteChangeTracker() {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const primera = useRef(true);

  useEffect(() => {
    if (primera.current) {
      primera.current = false;
      return;
    }
    tiktokPage();
    metaPageView();
  }, [pathname, searchParams]);

  return null;
}
