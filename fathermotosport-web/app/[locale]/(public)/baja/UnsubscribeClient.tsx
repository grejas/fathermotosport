"use client";

import { useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { CheckCircle2, MailX, XCircle } from "lucide-react";
import { Link } from "@/lib/i18n/navigation";
import { Button } from "@/components/ui/Button";
import { Spinner } from "@/components/ui/Spinner";
import { confirmUnsubscribe, getUnsubscribeStatus } from "@/lib/api/unsubscribe";

type Estado = "cargando" | "invalido" | "pregunta" | "listo" | "error";

/**
 * Baja de las promociones desde el enlace del correo. Abrir la página NO da de baja
 * (los antivirus del correo abren los enlaces solos): hace falta el botón.
 */
export function UnsubscribeClient() {
  const t = useTranslations("unsubscribe");
  const params = useSearchParams();
  const u = params.get("u") ?? "";
  const token = params.get("t") ?? "";
  const [estado, setEstado] = useState<Estado>("cargando");
  const [email, setEmail] = useState("");
  const [enviando, setEnviando] = useState(false);

  useEffect(() => {
    if (!u || !token) {
      setEstado("invalido");
      return;
    }
    getUnsubscribeStatus(u, token).then((res) => {
      if (!res?.valid) return setEstado("invalido");
      setEmail(res.email ?? "");
      setEstado(res.unsubscribed ? "listo" : "pregunta");
    });
  }, [u, token]);

  const confirmar = async () => {
    setEnviando(true);
    setEstado((await confirmUnsubscribe(u, token)) ? "listo" : "error");
    setEnviando(false);
  };

  if (estado === "cargando") {
    return (
      <div className="flex justify-center py-10">
        <Spinner size={32} />
      </div>
    );
  }

  const icono =
    estado === "listo" ? (
      <CheckCircle2 size={32} className="text-cat-boots" />
    ) : estado === "pregunta" ? (
      <MailX size={32} className="text-brand-red" />
    ) : (
      <XCircle size={32} className="text-brand-red" />
    );

  return (
    <div className="flex flex-col items-center gap-4 text-center">
      <span className="flex h-16 w-16 items-center justify-center rounded-full bg-white/5">{icono}</span>

      {estado === "pregunta" && (
        <>
          <h1 className="text-2xl font-bold text-brand-white">{t("title")}</h1>
          <p className="text-sm text-brand-muted">{t("question", { email })}</p>
          <p className="text-xs text-brand-muted">{t("transactional_note")}</p>
          <Button variant="primary" onClick={confirmar} loading={enviando}>
            {t("confirm")}
          </Button>
        </>
      )}

      {estado === "listo" && (
        <>
          <h1 className="text-2xl font-bold text-brand-white">{t("done_title")}</h1>
          <p className="text-sm text-brand-muted">{t("done_text")}</p>
        </>
      )}

      {estado === "invalido" && (
        <>
          <h1 className="text-2xl font-bold text-brand-white">{t("invalid_title")}</h1>
          <p className="text-sm text-brand-muted">{t("invalid_text")}</p>
        </>
      )}

      {estado === "error" && (
        <>
          <h1 className="text-2xl font-bold text-brand-white">{t("error_title")}</h1>
          <p className="text-sm text-brand-muted">{t("error_text")}</p>
          <Button variant="glass" onClick={confirmar} loading={enviando}>
            {t("retry")}
          </Button>
        </>
      )}

      {estado !== "pregunta" && (
        <Link href="/" className="text-sm text-brand-red hover:underline">
          {t("back_home")}
        </Link>
      )}
    </div>
  );
}
