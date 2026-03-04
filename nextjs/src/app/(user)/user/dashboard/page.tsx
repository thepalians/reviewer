import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { query, queryOne } from "@/lib/db";
import StatsCard from "@/components/StatsCard";
import TaskCard from "@/components/TaskCard";
import { formatCurrency } from "@/lib/utils";
import type { Task } from "@/types";
import type { Metadata } from "next";
import type { RowDataPacket } from "mysql2";

export const metadata: Metadata = { title: "Dashboard" };

interface UserRow extends RowDataPacket {
  name: string;
  wallet_balance: number;
}

interface TaskStatusCountRow extends RowDataPacket {
  status: string;
  cnt: number;
}

interface TaskRow extends RowDataPacket {
  id: number;
  user_id: number;
  seller_id: number | null;
  order_id: string | null;
  product_name: string | null;
  product_link: string | null;
  platform: string | null;
  commission: string | null;
  status: string;
  deadline: Date | null;
  created_at: Date;
  updated_at: Date;
}

interface CampaignCountRow extends RowDataPacket {
  cnt: number;
}

export default async function UserDashboardPage() {
  const session = await auth();
  if (!session || session.user.userType !== "user") redirect("/login");

  const userId = parseInt(session.user.id);

  const [user, taskStatusCounts, recentTasks, campaignCountRows] = await Promise.all([
    queryOne<UserRow>(
      "SELECT name, wallet_balance FROM users WHERE id = ? LIMIT 1",
      [userId]
    ),
    query<TaskStatusCountRow>(
      "SELECT status, COUNT(*) AS cnt FROM tasks WHERE user_id = ? GROUP BY status",
      [userId]
    ),
    query<TaskRow>(
      "SELECT * FROM tasks WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
      [userId]
    ),
    query<CampaignCountRow>(
      "SELECT COUNT(*) AS cnt FROM social_campaigns WHERE status = 'active' AND admin_approved = 1",
      []
    ),
  ]);

  const activeCampaigns = Number(campaignCountRows[0]?.cnt ?? 0);

  const totalTasks = taskStatusCounts.reduce((sum, g) => sum + Number(g.cnt), 0);
  const completedTasks =
    Number(taskStatusCounts.find((g) => g.status === "completed")?.cnt ?? 0);
  const pendingTasks = taskStatusCounts
    .filter((g) => ["pending", "assigned", "in_progress"].includes(g.status))
    .reduce((sum, g) => sum + Number(g.cnt), 0);

  const mappedTasks: Task[] = recentTasks.map((t) => ({
    id: t.id,
    userId: t.user_id,
    orderId: t.order_id ?? undefined,
    productName: t.product_name ?? undefined,
    productLink: t.product_link ?? undefined,
    platform: t.platform ?? undefined,
    status: t.status as Task["status"],
    commission: t.commission ? Number(t.commission) : undefined,
    deadline: t.deadline ? new Date(t.deadline).toISOString() : undefined,
    refundRequested: false,
    createdAt: new Date(t.created_at).toISOString(),
    updatedAt: new Date(t.updated_at).toISOString(),
  }));

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
          value={formatCurrency(Number(user?.wallet_balance ?? 0))}
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
        {mappedTasks.length === 0 ? (
          <div className="bg-white rounded-xl border border-gray-100 p-8 text-center text-gray-500">
            <p className="text-4xl mb-2">📋</p>
            <p>No tasks yet. Check back later!</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {mappedTasks.map((task) => (
              <TaskCard key={task.id} task={task} />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
