import { Suspense } from "react";
import { ProfileClient } from "./ProfileClient";
import { Spinner } from "@/components/ui/Spinner";

export default function ProfilePage() {
  return (
    <Suspense fallback={<div className="flex min-h-[40vh] items-center justify-center"><Spinner size={28} /></div>}>
      <ProfileClient />
    </Suspense>
  );
}
