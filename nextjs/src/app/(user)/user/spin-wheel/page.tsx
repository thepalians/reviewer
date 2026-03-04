import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { queryOne } from "@/lib/db";
import type { Metadata } from "next";
import SpinWheelPageClient from "./SpinWheelPageClient";
import type { RowDataPacket } from "mysql2";

export const metadata: Metadata = { title: "Daily Spin Wheel" };

interface PointSpinRow extends RowDataPacket {
  id: number;
}

interface WalletSpinRow extends RowDataPacket {
  id: number;
}

export default async function SpinWheelPage() {
  const session = await auth();
  if (!session || session.user.userType !== "user") redirect("/login");

  const userId = parseInt(session.user.id);

  const todayStart = new Date();
  todayStart.setHours(0, 0, 0, 0);
  const todayStartStr = todayStart.toISOString().slice(0, 19).replace("T", " ");

  const [lastSpin, lastCashSpin] = await Promise.all([
    queryOne<PointSpinRow>(
      "SELECT id FROM user_points WHERE user_id = ? AND type = 'spin_wheel' AND created_at >= ? LIMIT 1",
      [userId, todayStartStr]
    ),
    queryOne<WalletSpinRow>(
      "SELECT id FROM wallet_transactions WHERE user_id = ? AND reference_type = 'spin_wheel' AND created_at >= ? LIMIT 1",
      [userId, todayStartStr]
    ),
  ]);

  const alreadySpunToday = !!(lastSpin || lastCashSpin);

  const SEGMENTS = [
    { label: "₹5", color: "#667eea" },
    { label: "5 Points", color: "#f093fb" },
    { label: "₹10", color: "#11998e" },
    { label: "Better Luck", color: "#cbd5e1" },
    { label: "₹20", color: "#764ba2" },
    { label: "10 Points", color: "#f5576c" },
    { label: "₹50", color: "#fda085" },
    { label: "₹100", color: "#f6d365" },
  ];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">🎰 Daily Spin Wheel</h1>
        <p className="text-white/80 mt-1">
          {alreadySpunToday
            ? "You've already spun today. Come back tomorrow!"
            : "1 free spin per day — try your luck!"}
        </p>
      </div>

      {/* Wheel */}
      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex flex-col items-center gap-6">
        <SpinWheelPageClient alreadySpunToday={alreadySpunToday} />
      </div>

      {/* Possible rewards */}
      <div className="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Possible Rewards</h2>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {SEGMENTS.map((seg) => (
            <div
              key={seg.label}
              className="rounded-xl p-3 text-center text-white text-sm font-semibold"
              style={{ backgroundColor: seg.color }}
            >
              {seg.label}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
