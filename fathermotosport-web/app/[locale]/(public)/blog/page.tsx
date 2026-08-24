import { Suspense } from "react";
import type { Metadata } from "next";
import { BlogClient } from "./BlogClient";
import { Spinner } from "@/components/ui/Spinner";

export const metadata: Metadata = {
  title: "Blog",
  description:
    "Noticias, guías y novedades de FatherMotoSport: cascos, equipamiento y accesorios para motociclistas.",
};

export default function BlogPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center">
          <Spinner size={32} />
        </div>
      }
    >
      <BlogClient />
    </Suspense>
  );
}
