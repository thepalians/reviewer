import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import StatsCard from "@/components/StatsCard";
import type { Metadata } from "next";

export const metadata: Metadata = { title: "Admin Dashboard" };

export default async function AdminDashboardPage() {
  const session = await auth();
  if (!session || session.user.userType !== "admin") redirect("/login");

  const [
    totalUsers,
    totalSellers,
    totalTasks,
    pendingTasks,
    completedTasks,
    pendingWithdrawals,
    pendingKyc,
  ] = await Promise.all([
    prisma.user.count({ where: { userType: "user" } }),
    prisma.seller.count(),
    prisma.task.count(),
    prisma.task.count({ where: { status: { in: ["pending", "assigned", "in_progress"] } } }),
    prisma.task.count({ where: { status: "completed" } }),
    prisma.walletTransaction.count({
      where: { type: "withdrawal_pending" },
    }),
    prisma.kycDocument.count({ where: { status: "pending" } }),
  ]);

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">⚙️ Admin Dashboard</h1>
        <p className="text-white/80 mt-1">Platform overview and management</p>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <StatsCard
          title="Total Users"
          value={totalUsers}
          icon="👥"
          gradient="from-[#667eea] to-[#764ba2]"
        />
        <StatsCard
          title="Total Sellers"
          value={totalSellers}
          icon="🏪"
          gradient="from-[#4facfe] to-[#00f2fe]"
        />
        <StatsCard
          title="Total Tasks"
          value={totalTasks}
          icon="📋"
          gradient="from-[#f093fb] to-[#f5576c]"
        />
        <StatsCard
          title="Completed Tasks"
          value={completedTasks}
          icon="✅"
          gradient="from-[#11998e] to-[#38ef7d]"
        />
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <StatsCard
          title="Pending Tasks"
          value={pendingTasks}
          icon="⏳"
          gradient="from-[#f6d365] to-[#fda085]"
        />
        <StatsCard
          title="Pending Withdrawals"
          value={pendingWithdrawals}
          icon="💸"
          gradient="from-[#f093fb] to-[#f5576c]"
        />
        <StatsCard
          title="Pending KYC"
          value={pendingKyc}
          icon="🔐"
          gradient="from-[#4facfe] to-[#00f2fe]"
        />
      </div>

      {/* Quick Links */}
      <div>
        <h2 className="text-lg font-semibold text-gray-900 mb-3">Quick Actions</h2>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {[
            { href: "/admin/assign-task", label: "Assign Task", emoji: "➕" },
            { href: "/admin/tasks/pending", label: "Pending Tasks", emoji: "⏳" },
            { href: "/admin/kyc-management", label: "KYC Review", emoji: "🔐" },
            { href: "/admin/withdrawals", label: "Withdrawals", emoji: "💸" },
            { href: "/admin/users", label: "Manage Users", emoji: "👥" },
            { href: "/admin/sellers", label: "Manage Sellers", emoji: "🏪" },
            { href: "/admin/social-campaigns", label: "Campaigns", emoji: "📱" },
            { href: "/admin/settings", label: "Settings", emoji: "⚙️" },
          ].map((action) => (
            <a
              key={action.href}
              href={action.href}
              className="flex flex-col items-center justify-center p-4 bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 text-center"
            >
              <span className="text-2xl">{action.emoji}</span>
              <span className="mt-1.5 text-xs font-medium text-gray-700">
                {action.label}
              </span>
            </a>
          ))}
        </div>
      </div>
    </div>
  );
}
