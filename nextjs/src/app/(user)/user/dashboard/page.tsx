import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import StatsCard from "@/components/StatsCard";
import TaskCard from "@/components/TaskCard";
import { formatCurrency } from "@/lib/utils";
import type { Task } from "@/types";
import type { Metadata } from "next";

export const metadata: Metadata = { title: "Dashboard" };

export default async function UserDashboardPage() {
  const session = await auth();
  if (!session || session.user.userType !== "user") redirect("/login");

  const userId = parseInt(session.user.id);

  const [user, taskCounts, recentTasks, activeCampaigns] = await Promise.all([
    prisma.user.findUnique({
      where: { id: userId },
      select: { name: true, walletBalance: true },
    }),
    prisma.task.groupBy({
      by: ["status"],
      where: { userId },
      _count: true,
    }),
    prisma.task.findMany({
      where: { userId },
      orderBy: { createdAt: "desc" },
      take: 5,
    }),
    prisma.socialCampaign.count({
      where: { status: "active", adminApproved: true },
    }),
  ]);

  const totalTasks = taskCounts.reduce((sum, g) => sum + g._count, 0);
  const completedTasks =
    taskCounts.find((g) => g.status === "completed")?._count ?? 0;
  const pendingTasks =
    taskCounts
      .filter((g) => ["pending", "assigned", "in_progress"].includes(g.status))
      .reduce((sum, g) => sum + g._count, 0);

  return (
    <div className="space-y-6">
      {/* Welcome Banner */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">
          Welcome back, {user?.name ?? "User"}! 👋
        </h1>
        <p className="text-white/80 mt-1">
          Here&apos;s an overview of your activity
        </p>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <StatsCard
          title="Total Tasks"
          value={totalTasks}
          icon="📋"
          gradient="from-[#667eea] to-[#764ba2]"
        />
        <StatsCard
          title="Completed"
          value={completedTasks}
          icon="✅"
          gradient="from-[#11998e] to-[#38ef7d]"
        />
        <StatsCard
          title="Pending"
          value={pendingTasks}
          icon="⏳"
          gradient="from-[#f093fb] to-[#f5576c]"
        />
        <StatsCard
          title="Wallet Balance"
          value={formatCurrency(Number(user?.walletBalance ?? 0))}
          icon="💰"
          gradient="from-[#f6d365] to-[#fda085]"
        />
      </div>

      {/* Social Hub Banner */}
      <div className="bg-gradient-to-r from-[#11998e] to-[#38ef7d] rounded-2xl p-6 text-white">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-xl font-bold">📱 Social Hub</h2>
            <p className="text-white/80 mt-1">
              {activeCampaigns} active campaign{activeCampaigns !== 1 ? "s" : ""} available — watch & earn!
            </p>
          </div>
          <a
            href="/user/social-hub"
            className="bg-white text-[#11998e] font-semibold px-4 py-2 rounded-lg text-sm hover:bg-white/90 transition-colors"
          >
            Explore →
          </a>
        </div>
      </div>

      {/* Quick Actions */}
      <div>
        <h2 className="text-lg font-semibold text-gray-900 mb-3">Quick Actions</h2>
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
          {[
            { href: "/user/tasks", label: "My Tasks", emoji: "📋" },
            { href: "/user/social-hub", label: "Social Hub", emoji: "📱" },
            { href: "/user/wallet", label: "Wallet", emoji: "💰" },
            { href: "/user/submit-proof", label: "Submit Proof", emoji: "📸" },
            { href: "/user/rewards", label: "Rewards", emoji: "🎮" },
            { href: "/user/spin-wheel", label: "Spin Wheel", emoji: "🎰" },
            { href: "/user/referrals", label: "Referrals", emoji: "🔗" },
            { href: "/user/kyc", label: "KYC", emoji: "🆔" },
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

      {/* Recent Tasks */}
      <div>
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-lg font-semibold text-gray-900">Recent Tasks</h2>
          <a
            href="/user/tasks"
            className="text-sm text-[#667eea] font-medium hover:underline"
          >
            View all →
          </a>
        </div>
        {recentTasks.length === 0 ? (
          <div className="bg-white rounded-xl border border-gray-100 p-8 text-center text-gray-500">
            <p className="text-4xl mb-2">📋</p>
            <p>No tasks yet. Check back later!</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {recentTasks.map((task) => (
              <TaskCard key={task.id} task={task as unknown as Task} />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
