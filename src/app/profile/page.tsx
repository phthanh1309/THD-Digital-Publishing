import { redirect } from "next/navigation";
import { auth } from "@/lib/auth-nextauth";
import { prisma } from "@/lib/prisma";
import { updateProfile } from "./actions";
import "../tailwind.css";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
} from "@/components/ui/card";
import { Avatar, AvatarImage, AvatarFallback } from "@/components/ui/avatar";

export default async function ProfilePage() {
  const session = await auth();
  if (!session?.user?.id) redirect("/login");

  const user = await prisma.user.findUnique({
    where: { id: session.user.id },
  });
  if (!user) redirect("/login");

  const initial = (user.name ?? user.username).charAt(0).toUpperCase();

  return (
    <div className="mx-auto max-w-lg px-4 py-10">
      <Card>
        <CardHeader>
          <div className="flex items-center gap-4">
            <Avatar className="h-20 w-20">
              {user.image && (
                <AvatarImage src={`/api/files/${user.image}`} alt={user.name ?? user.username} />
              )}
              <AvatarFallback className="text-xl">{initial}</AvatarFallback>
            </Avatar>
            <div>
              <CardTitle>Hồ sơ cá nhân</CardTitle>
              <CardDescription>
                @{user.username} {user.email && `· ${user.email}`}
              </CardDescription>
            </div>
          </div>
        </CardHeader>

        <CardContent>
          <form action={updateProfile} className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="name">Tên hiển thị</Label>
              <Input id="name" name="name" type="text" defaultValue={user.name ?? ""} />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="bio">Giới thiệu</Label>
              <Textarea id="bio" name="bio" rows={3} defaultValue={user.bio ?? ""} />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="avatarFile">Ảnh đại diện</Label>
              <Input
                id="avatarFile"
                name="avatarFile"
                type="file"
                accept="image/jpeg,image/png,image/webp"
              />
            </div>

            <Button type="submit" className="w-full">
              Lưu thay đổi
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}