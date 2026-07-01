"use client";

import { useEffect, useState } from "react";
import { LogOut, Tag } from "lucide-react";
import { useAuthStore } from "@/store/authStore";
import { useLogout } from "@/lib/hooks/useAuth";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import apiClient from "@/lib/api/client";
import toast from "react-hot-toast";

interface MyCoupon {
  loyalty_discount_used: boolean;
  coupon: { code: string; value: string; used: boolean; expires_at: string } | null;
}

export default function ProfilePage() {
  const user = useAuthStore((s) => s.user);
  const setUser = useAuthStore((s) => s.setUser);
  const logout = useLogout();

  const [form, setForm] = useState({ first_name: "", last_name: "", phone: "" });
  const [coupon, setCoupon] = useState<MyCoupon | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (user) setForm({ first_name: user.first_name, last_name: user.last_name, phone: user.phone ?? "" });
  }, [user]);

  useEffect(() => {
    apiClient
      .get<MyCoupon>("/user/coupon")
      .then((r) => setCoupon(r.data))
      .catch(() => {});
  }, []);

  const save = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      const { data } = await apiClient.put<{ data: typeof user }>("/user/profile", form);
      if (data.data) setUser(data.data);
      toast.success("Perfil actualizado.");
    } catch {
      toast.error("No se pudo guardar.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-8">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-extrabold text-brand-white">Mi perfil</h1>
        <Button variant="glass" size="sm" icon={<LogOut size={16} />} onClick={() => logout.mutate()}>
          Cerrar sesión
        </Button>
      </div>

      {coupon?.coupon && (
        <div className="flex items-center justify-between rounded-2xl border border-brand-gold/30 bg-brand-gold/10 p-5">
          <div className="flex items-center gap-3">
            <Tag className="text-brand-gold" />
            <div>
              <p className="font-bold text-brand-white">Cupón de bienvenida ${coupon.coupon.value}</p>
              <p className="text-sm text-brand-muted">Código: {coupon.coupon.code}</p>
            </div>
          </div>
          {coupon.loyalty_discount_used ? (
            <Badge variant="gray">Usado</Badge>
          ) : (
            <Badge variant="gold">Disponible</Badge>
          )}
        </div>
      )}

      <form onSubmit={save} className="space-y-4 rounded-2xl border border-white/10 bg-brand-card p-6">
        <div className="grid grid-cols-2 gap-3">
          <Input label="Nombre" value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} />
          <Input label="Apellido" value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} />
        </div>
        <Input label="Email" value={user?.email ?? ""} disabled />
        <Input label="Teléfono" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
        <Button type="submit" variant="primary" loading={saving}>
          Guardar cambios
        </Button>
      </form>
    </div>
  );
}
