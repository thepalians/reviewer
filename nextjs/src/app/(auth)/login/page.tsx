"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import Input from "@/components/ui/Input";
import Button from "@/components/ui/Button";

export default function LoginPage() {
  const router = useRouter();
  const [userType, setUserType] = useState<"user" | "admin" | "seller">("user");
  const [loginMode, setLoginMode] = useState<"email" | "mobile">("email");
  const [login, setLogin] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setIsLoading(true);

    try {
      // Get CSRF token first using correct basePath
      const csrfRes = await fetch("/reviewer/nextjs/api/auth/csrf");
      const { csrfToken } = await csrfRes.json();

      // Call credentials callback directly with correct basePath
      const res = await fetch("/reviewer/nextjs/api/auth/callback/credentials", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          csrfToken,
          login,
          password,
          userType,
          json: "true",
        }),
        redirect: "follow",
      });

      const data = await res.json().catch((err) => { console.debug("Login response parse error:", err); return null; });

      if (res.ok && !data?.error) {
        if (userType === "admin") router.push("/admin/dashboard");
        else if (userType === "seller") router.push("/seller/dashboard");
        else router.push("/user/dashboard");
        router.refresh();
      } else {
        setError("Invalid credentials. Please check your login and password.");
      }
    } catch {
      setError("Something went wrong. Please try again.");
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#667eea] to-[#764ba2] p-4">
      <div className="w-full max-w-md">
        {/* Logo */}
        <div className="text-center mb-8">
          <h1 className="text-4xl font-bold text-white">🏠 ReviewFlow</h1>
          <p className="text-white/80 mt-2">Sign in to your account</p>
        </div>

        <div className="bg-white rounded-2xl shadow-2xl p-8">
          {/* User Type Tabs */}
          <div className="flex rounded-lg bg-gray-100 p-1 mb-6">
            {(["user", "seller", "admin"] as const).map((type) => (
              <button
                key={type}
                onClick={() => setUserType(type)}
                className={`flex-1 py-2 rounded-md text-sm font-medium capitalize transition-all duration-200 ${
                  userType === type
                    ? "bg-white shadow text-gray-900"
                    : "text-gray-500 hover:text-gray-700"
                }`}
              >
                {type === "user" ? "👤 User" : type === "seller" ? "🏪 Seller" : "⚙️ Admin"}
              </button>
            ))}
          </div>

          {/* Login Mode Toggle (user only) */}
          {userType === "user" && (
            <div className="flex gap-2 mb-4">
              <button
                onClick={() => setLoginMode("email")}
                className={`flex-1 py-1.5 rounded-lg text-xs font-medium border transition-colors ${
                  loginMode === "email"
                    ? "border-[#667eea] bg-[#667eea]/5 text-[#667eea]"
                    : "border-gray-200 text-gray-500"
                }`}
              >
                📧 Email
              </button>
              <button
                onClick={() => setLoginMode("mobile")}
                className={`flex-1 py-1.5 rounded-lg text-xs font-medium border transition-colors ${
                  loginMode === "mobile"
                    ? "border-[#667eea] bg-[#667eea]/5 text-[#667eea]"
                    : "border-gray-200 text-gray-500"
                }`}
              >
                📱 Mobile
              </button>
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
            <Input
              label={loginMode === "email" || userType !== "user" ? "Email Address" : "Mobile Number"}
              type={loginMode === "email" || userType !== "user" ? "email" : "tel"}
              placeholder={
                loginMode === "email" || userType !== "user"
                  ? "you@example.com"
                  : "10-digit mobile number"
              }
              value={login}
              onChange={(e) => setLogin(e.target.value)}
              required
              autoComplete="username"
            />

            <Input
              label="Password"
              type="password"
              placeholder="••••••••"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              autoComplete="current-password"
            />

            {error && (
              <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-600">
                ❌ {error}
              </div>
            )}

            <Button
              type="submit"
              variant="primary"
              size="lg"
              isLoading={isLoading}
              className="w-full"
            >
              Sign In
            </Button>
          </form>

          {userType === "user" && (
            <p className="mt-4 text-center text-sm text-gray-500">
              Don't have an account?{" "}
              <Link
                href="/register"
                className="text-[#667eea] font-medium hover:underline"
              >
                Register here
              </Link>
            </p>
          )}
        </div>
      </div>
    </div>
  );
}
