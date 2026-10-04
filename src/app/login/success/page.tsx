import { auth } from "@/lib/auth-nextauth";

export default async function LoginSuccessPage() {
  const session = await auth();
  return <pre>{JSON.stringify(session, null, 2)}</pre>;
}