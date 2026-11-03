import { NextResponse } from "next/server";
import { aiEnabled, respondWithAI } from "@/lib/assistant/ai";
import { respondGuided } from "@/lib/assistant/guided";
import type { AssistantLocale, ChatMessage } from "@/lib/assistant/types";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

const MAX_MESSAGES = 10;
const MAX_CHARS = 500;
const LOCALES: AssistantLocale[] = ["es", "pt", "en"];

// Límite por IP en memoria: frena abusos básicos y gasto accidental. En Vercel cada instancia
// tiene su propio contador; para un límite global usar Vercel WAF / Upstash (ver README).
const WINDOW_MS = 10 * 60 * 1000;
const MAX_REQUESTS = 30;
const hits = new Map<string, number[]>();

function limited(ip: string): boolean {
  const now = Date.now();
  const recent = (hits.get(ip) ?? []).filter((t) => now - t < WINDOW_MS);
  recent.push(now);
  hits.set(ip, recent);
  if (hits.size > 5000) for (const [k, v] of hits) if (v.every((t) => now - t >= WINDOW_MS)) hits.delete(k);
  return recent.length > MAX_REQUESTS;
}

function parse(body: unknown): { messages: ChatMessage[]; locale: AssistantLocale } | null {
  if (!body || typeof body !== "object") return null;
  const { messages, locale } = body as { messages?: unknown; locale?: unknown };
  if (!Array.isArray(messages) || messages.length === 0) return null;

  const clean: ChatMessage[] = [];
  for (const m of messages.slice(-MAX_MESSAGES)) {
    if (!m || typeof m !== "object") return null;
    const { role, content } = m as { role?: unknown; content?: unknown };
    if ((role !== "user" && role !== "assistant") || typeof content !== "string") return null;
    const text = content.trim().slice(0, MAX_CHARS);
    if (text) clean.push({ role, content: text });
  }
  // La conversación debe empezar y terminar en un mensaje del cliente.
  while (clean.length && clean[0].role !== "user") clean.shift();
  if (clean.length === 0 || clean[clean.length - 1].role !== "user") return null;

  return { messages: clean, locale: LOCALES.includes(locale as AssistantLocale) ? (locale as AssistantLocale) : "es" };
}

export async function POST(request: Request) {
  const ip = request.headers.get("x-forwarded-for")?.split(",")[0]?.trim() || "unknown";
  if (limited(ip)) {
    return NextResponse.json({ error: "rate_limited" }, { status: 429, headers: { "Retry-After": "60" } });
  }

  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ error: "invalid_json" }, { status: 400 });
  }
  const input = parse(body);
  if (!input) return NextResponse.json({ error: "invalid_input" }, { status: 400 });

  const aiReply = aiEnabled() ? await respondWithAI(input.messages, input.locale) : null;
  const reply = aiReply ?? (await respondGuided(input.messages, input.locale));

  return NextResponse.json(reply, { headers: { "Cache-Control": "no-store" } });
}
