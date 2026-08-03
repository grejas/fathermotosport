export async function translateText(text: string, targetLang: string): Promise<string> {
  if (targetLang === "es") return text;
  try {
    const url = `https://translate.googleapis.com/translate_a/single?client=gtx&sl=es&tl=${targetLang}&dt=t&q=${encodeURIComponent(text)}`;
    const res = await fetch(url);
    const data = await res.json();
    return (data[0] as [string, string][]).map((item) => item[0]).join("");
  } catch {
    return text;
  }
}
