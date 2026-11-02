import NextAuth from "next-auth";
import Credentials from "next-auth/providers/credentials";
import Google from "next-auth/providers/google";
import Facebook from "next-auth/providers/facebook";
import { buildAuthAdapter } from "./auth-adapter";
import bcrypt from "bcryptjs";
import { prisma } from "./prisma";
import { isFeatureEnabled } from "./features/service";
import {
  evaluateSignInAccess,
  type SignInAccessDependencies,
} from "./features/sign-in-access";

/**
 * Trusted lookups for the login feature gate.
 *
 * Roles always come from the database: Credentials by the authenticated user's
 * id, OAuth by the existing Account row. A new OAuth identity has no Account
 * row and therefore cannot be recognised as ADMIN.
 */
const signInAccessDependencies: SignInAccessDependencies = {
  isLoginEnabled: () => isFeatureEnabled("login"),

  async findUserRoleById(userId) {
    const user = await prisma.user.findUnique({
      where: { id: userId },
      select: { role: true },
    });
    return user?.role ?? null;
  },

  async findOAuthUserRole(provider, providerAccountId) {
    const account = await prisma.account.findUnique({
      where: {
        provider_providerAccountId: { provider, providerAccountId },
      },
      select: { user: { select: { role: true } } },
    });
    return account?.user.role ?? null;
  },
};

export const { handlers, auth, signIn, signOut } = NextAuth({
  adapter: buildAuthAdapter(),
  session: { strategy: "jwt" },
  pages: { signIn: "/login" },
  providers: [
    Credentials({
      credentials: {
        username: {},
        password: {},
      },
      async authorize(credentials) {
        const username = String(credentials?.username ?? "");
        const password = String(credentials?.password ?? "");
        if (!username || !password) return null;

        const user = await prisma.user.findUnique({ where: { username } });
        if (!user) return null;

        const valid = await bcrypt.compare(password, user.passwordHash);
        if (!valid) return null;

        if (!user.emailVerified) return null; // chưa xác thực email thì không cho đăng nhập

        return {
          id: user.id,
          name: user.name ?? user.username,
          email: user.email ?? undefined,
          role: user.role,
        };
      },
    }),
    Google({
      clientId: process.env.AUTH_GOOGLE_ID,
      clientSecret: process.env.AUTH_GOOGLE_SECRET,
    }),
    Facebook({
      clientId: process.env.AUTH_FACEBOOK_ID,
      clientSecret: process.env.AUTH_FACEBOOK_SECRET,
    }),
  ],
  callbacks: {
    async signIn({ user, account }) {
      if (account?.type === "credentials") {
        if (!user?.id) return false;

        return evaluateSignInAccess(
          { kind: "credentials", userId: user.id },
          signInAccessDependencies
        );
      }

      // OAuth (oauth/oidc). Auth.js invokes this before creating a user, so an
      // identity without an existing Account row is denied while login is off.
      if (!account?.provider || !account.providerAccountId) return false;

      return evaluateSignInAccess(
        {
          kind: "oauth",
          provider: account.provider,
          providerAccountId: account.providerAccountId,
        },
        signInAccessDependencies
      );
    },
    async jwt({ token, user }) {
      if (user) {
        token.role = (user as { role: string }).role ?? "USER";
        token.id = user.id;
      }
      return token;
    },
    async session({ session, token }) {
      if (session.user) {
        (session.user as { role?: string }).role = token.role as string;
        (session.user as { id?: string }).id = token.id as string;
      }
      return session;
    },
  },
});