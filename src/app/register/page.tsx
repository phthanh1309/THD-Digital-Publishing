"use client";

import { useActionState } from "react";
import Link from "next/link";
import { registerAction } from "./actions";
import "../tailwind.css";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Card,
  CardHeader,
  CardTitle,
  CardContent,
  CardFooter,
} from "@/components/ui/card";

export default function RegisterPage() {
  const [message, formAction, pending] = useActionState(registerAction, "");

  if (message === "SUCCESS") {
    return (
      <div className="flex min-h-screen items-center justify-center bg-muted/40 px-4">
        <Card className="w-full max-w-sm">
          <CardHeader>
            <CardTitle>Kiểm tra email của bạn</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground">
              Mình đã gửi liên kết xác thực. Bấm vào liên kết trong email để
              kích hoạt tài khoản.
            </p>
          </CardContent>
          <CardFooter>
            <Link href="/login" className="text-sm hover:underline">
              ← Quay lại đăng nhập
            </Link>
          </CardFooter>
        </Card>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/40 px-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle>Đăng ký tài khoản</CardTitle>
        </CardHeader>

        <CardContent className="space-y-4">
          {message && (
            <div className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
              {message}
            </div>
          )}

          <form className="space-y-4" action={formAction}>
            <div className="space-y-1.5">
              <Label htmlFor="username">Tên đăng nhập</Label>
              <Input id="username" name="username" type="text" required />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="email">Email</Label>
              <Input id="email" name="email" type="email" required />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="password">Mật khẩu</Label>
              <Input id="password" name="password" type="password" required minLength={8} />
            </div>
            <Button type="submit" className="w-full" disabled={pending}>
              {pending ? "Đang xử lý..." : "Đăng ký"}
            </Button>
          </form>
        </CardContent>

        <CardFooter>
          <Link href="/login" className="text-sm hover:underline">
            Đã có tài khoản? Đăng nhập
          </Link>
        </CardFooter>
      </Card>
    </div>
  );
}