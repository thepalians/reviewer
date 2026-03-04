import NextAuth from "next-auth";
import CredentialsProvider from "next-auth/providers/credentials";
import bcrypt from "bcryptjs";
import { queryOne } from "./db";
import { authConfig } from "./auth.config";
import type { RowDataPacket } from "mysql2";

interface UserRow extends RowDataPacket {
  id: number;
  name: string;
  email: string;
  password: string;
  user_type: string;
  status: string;
}

interface SellerRow extends RowDataPacket {
  id: number;
  name: string;
  email: string;
  password: string;
  status: string;
}

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
        if (!credentials?.login || !credentials?.password) return null;

        const login = credentials.login as string;
        const password = credentials.password as string;
        const userType = (credentials.userType as string) || "user";

        try {
          if (userType === "seller") {
            const seller = await queryOne<SellerRow>(
              "SELECT id, name, email, password, status FROM sellers WHERE email = ? AND status = 'active' LIMIT 1",
              [login]
            );
            if (!seller) return null;

            const fixedHash = seller.password.replace(/^\$2y\$/, "$2a$");
            const ok = await bcrypt.compare(password, fixedHash);
            if (!ok) return null;

            return { id: String(seller.id), name: seller.name, email: seller.email, userType: "seller" };
          }

          const isEmail = login.includes("@");
          const user = await queryOne<UserRow>(
            isEmail
              ? "SELECT id, name, email, password, user_type, status FROM users WHERE email = ? AND status = 'active' LIMIT 1"
              : "SELECT id, name, email, password, user_type, status FROM users WHERE mobile = ? AND status = 'active' LIMIT 1",
            [login]
          );
          if (!user) return null;

          const fixedHash = user.password.replace(/^\$2y\$/, "$2a$");
          const ok = await bcrypt.compare(password, fixedHash);
          if (!ok) return null;

          if (userType === "admin" && user.user_type !== "admin") return null;
          if (userType === "user" && user.user_type !== "user") return null;

          return { id: String(user.id), name: user.name, email: user.email, userType: user.user_type };
        } catch {
          return null;
        }
      },
    }),
  ],
});
