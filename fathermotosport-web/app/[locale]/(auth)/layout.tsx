export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen items-center justify-center px-4 pt-20">
      <div className="w-full max-w-md rounded-2xl border border-white/10 bg-brand-card p-8 shadow-card">
        {children}
      </div>
    </div>
  );
}
