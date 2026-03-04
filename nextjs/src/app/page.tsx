import { redirect } from "next/navigation";
import { auth } from "@/lib/auth";

export default async function HomePage() {
  const session = await auth();

  if (!session) {
    redirect("/login");
  }

  const userType = session.user.userType;
  if (userType === "admin") redirect("/admin/dashboard");
  if (userType === "seller") redirect("/seller/dashboard");
  redirect("/user/dashboard");
}
