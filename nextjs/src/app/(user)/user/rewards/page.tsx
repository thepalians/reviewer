import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { query } from "@/lib/db";
import type { Metadata } from "next";
import PointsHistory from "@/components/PointsHistory";
import TierBadge, { TierProgress } from "@/components/TierBadge";
import type { RowDataPacket } from "mysql2";

export const metadata: Metadata = { title: "Rewards & Points" };

interface PointRow extends RowDataPacket {
  id: number;
  user_id: number;
  points: number;
  type: string;
  description: string | null;
  created_at: Date;
}

export default async function RewardsPage() {
  const session = await auth();
  if (!session || session.user.userType !== "user") redirect("/login");

  const userId = parseInt(session.user.id);

  const points = await query<PointRow>(
    "SELECT * FROM user_points WHERE user_id = ? ORDER BY created_at DESC LIMIT 100",
    [userId]
  );

  const totalPoints = points.reduce((sum, p) => sum + Number(p.points), 0);

  const breakdown = points.reduce<Record<string, number>>((acc, p) => {
    acc[p.type] = (acc[p.type] ?? 0) + Number(p.points);
    return acc;
  }, {});

  const BREAKDOWN_LABELS: Record<string, string> = {
    task_completion: "📋 Task Completion",
    social_task: "📱 Social Tasks",
    referral: "🔗 Referrals",
    daily_login: "📅 Daily Login",
    spin_wheel: "🎰 Spin Wheel",
    admin_bonus: "⭐ Admin Bonus",
  };

  const serializedHistory = points.map((p) => ({
    id: p.id,
    points: Number(p.points),
    type: p.type,
    description: p.description,
    createdAt: new Date(p.created_at).toISOString(),
  }));

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">🎮 Rewards & Points</h1>
        <p className="text-white/80 mt-1">Track your points and tier progress</p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
          <p className="text-sm text-gray-500">Total Points</p>
          <p className="text-3xl font-bold text-gray-900 mt-1">{totalPoints}</p>
          <div className="mt-2">
            <TierBadge points={totalPoints} size="sm" />
          </div>
        </div>
        <div className="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
          <p className="text-sm text-gray-500">Points This Month</p>
          <p className="text-3xl font-bold text-gray-900 mt-1">
            {points
              .filter((p) => {
                const d = new Date(p.created_at);
                const now = new Date();
                return (
                  d.getMonth() === now.getMonth() &&
                  d.getFullYear() === now.getFullYear()
                );
              })
              .reduce((sum, p) => sum + Number(p.points), 0)}
          </p>
        </div>
        <div className="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
          <p className="text-sm text-gray-500">Total Transactions</p>
          <p className="text-3xl font-bold text-gray-900 mt-1">{points.length}</p>
        </div>
      </div>

      {/* Tier Progress */}
      <div className="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Tier Progress</h2>
        <TierProgress points={totalPoints} />
        <div className="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs text-center text-gray-500">
          <div className="rounded-lg bg-amber-50 border border-amber-200 p-2">
            🥉 <strong>Bronze</strong>
            <br />0–500 pts
          </div>
          <div className="rounded-lg bg-slate-50 border border-slate-200 p-2">
            🥈 <strong>Silver</strong>
            <br />501–2000 pts
          </div>
          <div className="rounded-lg bg-yellow-50 border border-yellow-200 p-2">
            🥇 <strong>Gold</strong>
            <br />2001–5000 pts
          </div>
          <div className="rounded-lg bg-purple-50 border border-purple-200 p-2">
            💎 <strong>Platinum</strong>
            <br />5001+ pts
          </div>
        </div>
      </div>

      {/* Breakdown */}
      {Object.keys(breakdown).length > 0 && (
        <div className="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Points Breakdown</h2>
          <div className="space-y-3">
            {Object.entries(breakdown).map(([type, pts]) => (
              <div key={type} className="flex items-center justify-between">
                <span className="text-sm text-gray-700">
                  {BREAKDOWN_LABELS[type] ?? type}
                </span>
                <span className="text-sm font-semibold text-gray-900">
                  {pts} pts
                </span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* History */}
      <div>
        <h2 className="text-lg font-semibold text-gray-900 mb-3">Points History</h2>
        <PointsHistory history={serializedHistory} />
      </div>
    </div>
  );
}
