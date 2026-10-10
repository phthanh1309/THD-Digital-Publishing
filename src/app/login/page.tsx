import Link from "next/link";
import { signIn } from "@/lib/auth-nextauth";
import "../tailwind.css";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
  CardFooter,
} from "@/components/ui/card";

export default function LoginPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string }>;
}) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/40 px-4">
      <Card className="w-full max-w-sm">
        <CardHeader className="text-center">
          <p className="text-sm text-muted-foreground">THPT A Trần Hưng Đạo</p>
          <CardTitle className="text-xl">Thư viện Ấn phẩm số</CardTitle>
          <CardDescription>Đăng nhập</CardDescription>
        </CardHeader>

        <CardContent className="space-y-4">
          <LoginError searchParams={searchParams} />

          <form
            className="space-y-4"
            action={async (formData) => {
              "use server";
              await signIn("credentials", {
                username: formData.get("username"),
                password: formData.get("password"),
                redirectTo: "/",
              });
            }}
          >
            <div className="space-y-1.5">
              <Label htmlFor="username">Tên đăng nhập</Label>
              <Input id="username" name="username" type="text" required autoFocus />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="password">Mật khẩu</Label>
              <Input id="password" name="password" type="password" required />
            </div>

            <Button type="submit" className="w-full">
              Đăng nhập
            </Button>
          </form>

          <div className="relative py-2 text-center text-xs text-muted-foreground">
            <span className="bg-card relative z-10 px-2">hoặc</span>
            <div className="absolute inset-x-0 top-1/2 h-px bg-border" />
          </div>

          <form
            action={async () => {
              "use server";
              const { signIn } = await import("@/lib/auth-nextauth");
              await signIn("google", { redirectTo: "/" });
            }}
          >
            <Button type="submit" variant="outline" className="w-full">
              Đăng nhập bằng Google
            </Button>
          </form>

          <form
            action={async () => {
              "use server";
              const { signIn } = await import("@/lib/auth-nextauth");
              await signIn("facebook", { redirectTo: "/" });
            }}
          >
            <Button type="submit" variant="outline" className="w-full">
              Đăng nhập bằng Facebook
            </Button>
          </form>
        </CardContent>

        <CardFooter className="flex justify-between text-sm">
          <Link href="/" className="text-muted-foreground hover:underline">
            ← Quay lại thư viện
          </Link>
          <Link href="/register" className="hover:underline">
            Chưa có tài khoản?
          </Link>
        </CardFooter>
      </Card>
    </div>
  );
}

async function LoginError({
  searchParams,
}: {
  searchParams: Promise<{ error?: string }>;
}) {
  const sp = await searchParams;
  if (!sp.error) return null;
  return (
    <div className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
      Tên đăng nhập hoặc mật khẩu không đúng.
    </div>
  );
}