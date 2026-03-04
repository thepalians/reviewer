import NextAuth from "next-auth";
import CredentialsProvider from "next-auth/providers/credentials";
import bcrypt from "bcryptjs";
import { prisma } from "./db";
import { authConfig } from "./auth.config";

export const { handlers, auth, signIn, signOut } = NextAuth({
  ...authConfig,
  providers: [
    CredentialsProvider({
      name: "credentials",
      credentials: {
        login: { label: "Email or Mobile", type: "text" },
        password: { label: "Password", type: "password" },
        userType: { label: "User Type", type: "text" },
      },
      async authorize(credentials) {
        if (!credentials?.login || !credentials?.password) {
          return null;
        }

        const login = credentials.login as string;
        const password = credentials.password as string;
        const userType = (credentials.userType as string) || "user";

        try {
          if (userType === "seller") {
            const seller = await prisma.seller.findFirst({
              where: {
                email: login,
                status: "active",
              },
              select: { id: true, name: true, email: true, password: true, status: true },
            });

            if (!seller) return null;

            const fixedHash = seller.password.replace(/^\$2y\$/, "$2a$");
            const passwordMatch = await bcrypt.compare(password, fixedHash);
            if (!passwordMatch) return null;

            return {
              id: String(seller.id),
              name: seller.name,
              email: seller.email,
              userType: "seller",
            };
          }

          const isEmail = login.includes("@");
          const user = await prisma.user.findFirst({
            where: isEmail
              ? { email: login, status: "active" }
              : { mobile: login, status: "active" },
            select: { id: true, name: true, email: true, password: true, userType: true, status: true },
          });

          if (!user) return null;

          const fixedHash = user.password.replace(/^\$2y\$/, "$2a$");
          const passwordMatch = await bcrypt.compare(password, fixedHash);
          if (!passwordMatch) return null;

          if (userType === "admin" && user.userType !== "admin") return null;
          if (userType === "user" && user.userType !== "user") return null;

          return {
            id: String(user.id),
            name: user.name,
            email: user.email,
            userType: user.userType,
          };
        } catch {
          return null;
        }
      },
    }),
  ],
});
