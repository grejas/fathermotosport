import { timingSafeEqual } from "node:crypto";
import { revalidateTag } from "next/cache";
import { NextResponse, type NextRequest } from "next/server";
import { SHIPPING_RETURNS_TAG } from "@/lib/api/shippingReturns";

/** Solo se pueden invalidar estos tags; cualquier otro responde 400. */
const ALLOWED_TAGS = new Set<string>([SHIPPING_RETURNS_TAG]);

function sameSecret(given: string, expected: string): boolean {
  const a = Buffer.from(given);
  const b = Buffer.from(expected);
  return a.length === b.length && timingSafeEqual(a, b);
}

/**
 * Revalidación on-demand: Laravel la llama al guardar en el panel
 * (POST con header x-revalidate-secret y body {"tag": "..."}).
 */
export async function POST(request: NextRequest) {
  const expected = process.env.REVALIDATE_SECRET;
  const given = request.headers.get("x-revalidate-secret");

  if (!expected || !given || !sameSecret(given, expected)) {
    return NextResponse.json({ revalidated: false, message: "Unauthorized" }, { status: 401 });
  }

  const body = (await request.json().catch(() => null)) as { tag?: unknown } | null;
  const tag = typeof body?.tag === "string" ? body.tag : "";

  if (!ALLOWED_TAGS.has(tag)) {
    return NextResponse.json({ revalidated: false, message: "Unknown tag" }, { status: 400 });
  }

  revalidateTag(tag);

  return NextResponse.json({ revalidated: true, tag });
}
