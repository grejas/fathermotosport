import apiClient from "./client";

export interface CouponValidation {
  valid: boolean;
  message: string;
  discount: number;
  coupon: { code: string; type: "percentage" | "fixed"; value: string } | null;
}

export async function validateCoupon(code: string, amount: number): Promise<CouponValidation> {
  try {
    const { data } = await apiClient.post<CouponValidation>("/coupons/validate", { code, amount });
    return data;
  } catch (error: unknown) {
    // El backend devuelve 422 con el mismo formato cuando no es válido.
    const resp = (error as { response?: { data?: CouponValidation } }).response?.data;
    if (resp) return resp;
    return { valid: false, message: "No se pudo validar el cupón.", discount: 0, coupon: null };
  }
}
