import type { NextAuthConfig } from "next-auth";

/**
 * Auth config that is safe for the Edge Runtime (middleware).
 * Does NOT import bcryptjs or Prisma (Node.js-only modules).
 */
export const authConfig: NextAuthConfig = {
  pages: {
    signIn: "/login",
    error: "/login",
  },
  callbacks: {
    authorized({ auth, request: { nextUrl } }) {
      const isLoggedIn = !!auth?.user;
      const userType = auth?.user?.userType;
      const pathname = nextUrl.pathname;

      if (pathname.startsWith("/user")) {
        return isLoggedIn && userType === "user";
      }
      if (pathname.startsWith("/admin")) {
        return isLoggedIn && userType === "admin";
      }
      if (pathname.startsWith("/seller")) {
        return isLoggedIn && userType === "seller";
      }
      return true;
    },
    async jwt({ token, user }) {
      if (user) {
        token.id = user.id;
        token.userType = (user as { userType?: string }).userType;
      }
      return token;
    },
    async session({ session, token }) {
      if (token) {
        session.user.id = token.id as string;
        session.user.userType = token.userType as string;
      }
      return session;
    },
  },
  providers: [],
  session: { strategy: "jwt" },
};
