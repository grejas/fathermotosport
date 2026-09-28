"use client";

import { useEffect, useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";
import { CalendarDays, LogOut, Mail, Phone, ShieldCheck, Tag, Pencil } from "lucide-react";
import { useAuthStore } from "@/store/authStore";
import { useLogout } from "@/lib/hooks/useAuth";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { Modal } from "@/components/ui/Modal";
import { Spinner } from "@/components/ui/Spinner";
import { cn, maskEmail, formatDate } from "@/lib/utils";
import apiClient from "@/lib/api/client";
import type { User } from "@/lib/types";
import toast from "react-hot-toast";

interface MyCoupon {
  code: string;
  value: string;
  used: boolean;
  expires_at: string | null;
  is_expired: boolean;
}

/** Formatea el tiempo restante hasta expires_at para el countdown del cupón. */
function formatRemaining(expiresAt: string, nowMs: number): string {
  const diff = new Date(expiresAt).getTime() - nowMs;
  if (diff <= 0) {
    const past = Math.max(1, Math.floor(-diff / 3_600_000));
    return `venció hace ${past} ${past === 1 ? "hora" : "horas"}`;
  }
  const hours = Math.floor(diff / 3_600_000);
  const minutes = Math.floor((diff % 3_600_000) / 60_000);
  if (hours > 0) return `${hours} h ${minutes} min`;
  return `${minutes} min`;
}

export function ProfileClient() {
  const params = useSearchParams();
  const user = useAuthStore((s) => s.user);
  const setUser = useAuthStore((s) => s.setUser);
  const refreshUser = useAuthStore((s) => s.refreshUser);
  const logout = useLogout();

  const [intentoDeRecuperacion, setIntentoDeRecuperacion] = useState(false);
  const [coupon, setCoupon] = useState<MyCoupon | null>(null);
  const [now, setNow] = useState(() => Date.now());
  const [editOpen, setEditOpen] = useState(false);
  const [securityOpen, setSecurityOpen] = useState(false);
  const [securityTab, setSecurityTab] = useState<"password" | "email">("password");

  // Abre el modal de seguridad si se llega con ?security=password (desde el banner).
  useEffect(() => {
    if (params.get("security") === "password") {
      setSecurityTab("password");
      setSecurityOpen(true);
    }
  }, [params]);

  useEffect(() => {
    apiClient.get<{ coupon: MyCoupon | null }>("/user/coupon").then((r) => setCoupon(r.data.coupon)).catch(() => {});
  }, []);

  // Countdown: actualiza cada minuto.
  useEffect(() => {
    const id = setInterval(() => setNow(Date.now()), 60_000);
    return () => clearInterval(id);
  }, []);

  const memberSince = useMemo(
    () => (user?.created_at ? formatDate(user.created_at) : ""),
    [user?.created_at]
  );

  // Sin datos del usuario: se intenta recuperarlos una vez. Antes acá se devolvía
  // null y la página quedaba en blanco, sin siquiera un botón para cerrar sesión.
  useEffect(() => {
    if (user || intentoDeRecuperacion) return;
    refreshUser().finally(() => setIntentoDeRecuperacion(true));
  }, [user, intentoDeRecuperacion, refreshUser]);

  if (!user) {
    if (!intentoDeRecuperacion) {
      return (
        <div className="flex min-h-[40vh] items-center justify-center">
          <Spinner size={28} />
        </div>
      );
    }

    return (
      <div className="mx-auto max-w-md rounded-2xl border border-white/10 bg-brand-card p-6 text-center">
        <h2 className="text-lg font-bold text-brand-white">No pudimos cargar tu perfil</h2>
        <p className="mt-2 text-sm text-brand-muted">
          Tu sesión pudo haber expirado. Cerrá sesión y volvé a entrar para continuar.
        </p>
        <Button
          variant="primary"
          className="mt-5 w-full"
          icon={<LogOut size={16} />}
          loading={logout.isPending}
          onClick={() => logout.mutate()}
        >
          Cerrar sesión
        </Button>
      </div>
    );
  }

  const couponActive = coupon && !coupon.is_expired && coupon.expires_at && !coupon.used;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-extrabold text-brand-white">Mi perfil</h1>
        <Button variant="glass" size="sm" icon={<LogOut size={16} />} onClick={() => logout.mutate()}>
          Cerrar sesión
        </Button>
      </div>

      {/* Cupón de bienvenida con countdown */}
      {coupon && (
        <div
          className={cn(
            "flex items-center justify-between rounded-2xl border p-5",
            couponActive ? "border-brand-gold/30 bg-brand-gold/10" : "border-white/10 bg-white/[0.03]"
          )}
        >
          <div className="flex items-center gap-3">
            <Tag className={couponActive ? "text-brand-gold" : "text-brand-muted"} />
            <div>
              {couponActive ? (
                <>
                  <p className="font-bold text-brand-white">
                    Cupón de bienvenida: {coupon.code} · Descuento ${coupon.value}
                  </p>
                  <p className="text-sm text-brand-gold">
                    ⏰ Vence en: {coupon.expires_at ? formatRemaining(coupon.expires_at, now) : "—"}
                  </p>
                </>
              ) : (
                <p className="text-sm text-brand-muted">
                  Cupón de bienvenida:{" "}
                  {coupon.used
                    ? "ya utilizado"
                    : coupon.expires_at
                      ? formatRemaining(coupon.expires_at, now)
                      : "no disponible"}
                </p>
              )}
            </div>
          </div>
          {couponActive ? <Badge variant="gold">Disponible</Badge> : <Badge variant="gray">No disponible</Badge>}
        </div>
      )}

      {/* Datos (solo lectura) */}
      <div className="rounded-2xl border border-white/10 bg-brand-card p-6">
        <div className="flex items-center gap-4">
          <div className="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-brand-red/15 text-xl font-bold text-brand-red">
            {user.avatar ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={user.avatar} alt="" className="h-full w-full object-cover" />
            ) : (
              (user.first_name?.[0] ?? user.email[0]).toUpperCase()
            )}
          </div>
          <div>
            <p className="text-lg font-bold text-brand-white">
              {user.full_name?.trim() || "Sin nombre"}
            </p>
            <p className="text-sm text-brand-muted">Miembro desde: {memberSince}</p>
          </div>
        </div>

        <dl className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div className="flex items-center gap-2 text-sm">
            <Mail size={16} className="text-brand-muted" />
            <span className="text-brand-white">{maskEmail(user.email)}</span>
          </div>
          {user.phone && (
            <div className="flex items-center gap-2 text-sm">
              <Phone size={16} className="text-brand-muted" />
              <span className="text-brand-white">{user.phone}</span>
            </div>
          )}
          {user.birth_date && (
            <div className="flex items-center gap-2 text-sm">
              <CalendarDays size={16} className="text-brand-muted" />
              <span className="text-brand-white">{formatDate(user.birth_date)}</span>
            </div>
          )}
        </dl>

        <button
          onClick={() => setEditOpen(true)}
          className="mt-6 inline-flex items-center gap-1.5 text-sm font-medium text-brand-muted transition hover:text-brand-red"
        >
          <Pencil size={14} /> Editar información personal
        </button>
      </div>

      {/* Acceso discreto a seguridad */}
      <button
        onClick={() => {
          setSecurityTab("password");
          setSecurityOpen(true);
        }}
        className="inline-flex items-center gap-1.5 text-xs text-brand-muted transition hover:text-brand-white"
      >
        <ShieldCheck size={14} /> Configuración de seguridad
      </button>

      <EditModal
        open={editOpen}
        onClose={() => setEditOpen(false)}
        user={user}
        onSaved={(u) => {
          setUser(u);
          setEditOpen(false);
        }}
      />

      <SecurityModal
        open={securityOpen}
        tab={securityTab}
        setTab={setSecurityTab}
        onClose={() => setSecurityOpen(false)}
        onEmailChanged={(u) => setUser(u)}
        onDone={() => refreshUser()}
      />
    </div>
  );
}

// ─────────────────────────── Modal de edición ───────────────────────────

function EditModal({
  open,
  onClose,
  user,
  onSaved,
}: {
  open: boolean;
  onClose: () => void;
  user: User;
  onSaved: (u: User) => void;
}) {
  const [form, setForm] = useState({
    first_name: user.first_name ?? "",
    last_name: user.last_name ?? "",
    phone: user.phone ?? "",
    birth_date: user.birth_date ?? "",
  });
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    setForm({
      first_name: user.first_name ?? "",
      last_name: user.last_name ?? "",
      phone: user.phone ?? "",
      birth_date: user.birth_date ?? "",
    });
  }, [user]);

  const save = async () => {
    setSaving(true);
    try {
      const { data } = await apiClient.put<{ data: User }>("/user/profile", {
        first_name: form.first_name || null,
        last_name: form.last_name || null,
        phone: form.phone || null,
        birth_date: form.birth_date || null,
      });
      toast.success("Perfil actualizado.");
      onSaved(data.data);
    } catch {
      toast.error("No se pudo guardar.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal open={open} onClose={onClose} title="Editar información personal">
      <div className="space-y-4">
        <div className="grid grid-cols-2 gap-3">
          <Input label="Nombre" value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} placeholder="Opcional" />
          <Input label="Apellido" value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} placeholder="Opcional" />
        </div>
        <Input label="Teléfono" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="Opcional" />
        <Input label="Fecha de nacimiento" type="date" value={form.birth_date ?? ""} onChange={(e) => setForm({ ...form, birth_date: e.target.value })} />
        <div className="flex justify-end gap-2 pt-2">
          <Button variant="glass" onClick={onClose}>Cancelar</Button>
          <Button variant="primary" loading={saving} onClick={save}>Guardar cambios</Button>
        </div>
      </div>
    </Modal>
  );
}

// ─────────────────────────── Modal de seguridad ───────────────────────────

function SecurityModal({
  open,
  tab,
  setTab,
  onClose,
  onEmailChanged,
  onDone,
}: {
  open: boolean;
  tab: "password" | "email";
  setTab: (t: "password" | "email") => void;
  onClose: () => void;
  onEmailChanged: (u: User) => void;
  onDone: () => void;
}) {
  const [pwd, setPwd] = useState({ current_password: "", password: "", password_confirmation: "" });
  const [eml, setEml] = useState({ current_password: "", email: "" });
  const [loading, setLoading] = useState(false);

  const submitPassword = async () => {
    setLoading(true);
    try {
      await apiClient.put("/user/password", pwd);
      toast.success("Contraseña actualizada.");
      setPwd({ current_password: "", password: "", password_confirmation: "" });
      onDone();
      onClose();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } }).response?.data?.message;
      toast.error(msg ?? "No se pudo actualizar la contraseña.");
    } finally {
      setLoading(false);
    }
  };

  const submitEmail = async () => {
    setLoading(true);
    try {
      const { data } = await apiClient.put<{ user: User }>("/user/email", eml);
      toast.success("Correo actualizado. Revisa tu nueva dirección.");
      setEml({ current_password: "", email: "" });
      if (data.user) onEmailChanged(data.user);
      onDone();
      onClose();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } }).response?.data?.message;
      toast.error(msg ?? "No se pudo actualizar el correo.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal open={open} onClose={onClose} title="Configuración de seguridad">
      <div className="mb-4 flex gap-1 border-b border-white/10">
        {(["password", "email"] as const).map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={cn(
              "px-4 py-2 text-sm font-semibold transition",
              tab === t ? "border-b-2 border-brand-red text-brand-white" : "text-brand-muted hover:text-brand-white"
            )}
          >
            {t === "password" ? "Cambiar contraseña" : "Cambiar correo"}
          </button>
        ))}
      </div>

      {tab === "password" ? (
        <div className="space-y-3">
          <Input label="Contraseña actual" type="password" value={pwd.current_password} onChange={(e) => setPwd({ ...pwd, current_password: e.target.value })} />
          <Input label="Nueva contraseña" type="password" value={pwd.password} onChange={(e) => setPwd({ ...pwd, password: e.target.value })} hint="Mínimo 8 caracteres" />
          <Input label="Confirmar nueva contraseña" type="password" value={pwd.password_confirmation} onChange={(e) => setPwd({ ...pwd, password_confirmation: e.target.value })} />
          <Button variant="primary" className="w-full" loading={loading} onClick={submitPassword}>
            Actualizar contraseña
          </Button>
        </div>
      ) : (
        <div className="space-y-3">
          <Input label="Contraseña actual" type="password" value={eml.current_password} onChange={(e) => setEml({ ...eml, current_password: e.target.value })} />
          <Input label="Nuevo correo electrónico" type="email" value={eml.email} onChange={(e) => setEml({ ...eml, email: e.target.value })} />
          <p className="text-xs text-brand-muted">Recibirás un email de confirmación en tu nueva dirección.</p>
          <Button variant="primary" className="w-full" loading={loading} onClick={submitEmail}>
            Enviar código de verificación
          </Button>
        </div>
      )}
    </Modal>
  );
}
