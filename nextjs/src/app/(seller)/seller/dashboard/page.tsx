import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import StatsCard from "@/components/StatsCard";
import { formatCurrency } from "@/lib/utils";
import type { Metadata } from "next";

export const metadata: Metadata = { title: "Seller Dashboard" };

export default async function SellerDashboardPage() {
  const session = await auth();
  if (!session || session.user.userType !== "seller") redirect("/login");

  const sellerId = parseInt(session.user.id);

  const [seller, campaigns, wallet] = await Promise.all([
    prisma.seller.findUnique({
      where: { id: sellerId },
      select: { name: true },
    }),
    prisma.socialCampaign.groupBy({
      by: ["status"],
      where: { sellerId },
      _count: true,
    }),
    prisma.sellerWallet.findUnique({
      where: { sellerId },
      select: { balance: true },
    }),
  ]);

  const totalCampaigns = campaigns.reduce((sum, g) => sum + g._count, 0);
  const activeCampaigns = campaigns.find((g) => g.status === "active")?._count ?? 0;
  const pendingCampaigns = campaigns.find((g) => g.status === "pending")?._count ?? 0;

  return (
    <div className="space-y-6">
      {/* Welcome Banner */}
      <div className="bg-gradient-to-r from-[#11998e] to-[#38ef7d] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">
          Welcome back, {seller?.name ?? "Seller"}! 👋
        </h1>
        <p className="text-white/80 mt-1">Manage your campaigns and earnings</p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <StatsCard
          title="Total Campaigns"
          value={totalCampaigns}
          icon="📢"
          gradient="from-[#667eea] to-[#764ba2]"
        />
        <StatsCard
          title="Active Campaigns"
          value={activeCampaigns}
          icon="✅"
          gradient="from-[#11998e] to-[#38ef7d]"
        />
        <StatsCard
          title="Pending Approval"
          value={pendingCampaigns}
          icon="⏳"
          gradient="from-[#f093fb] to-[#f5576c]"
        />
        <StatsCard
          title="Wallet Balance"
          value={formatCurrency(Number(wallet?.balance ?? 0))}
          icon="💰"
          gradient="from-[#f6d365] to-[#fda085]"
        />
      </div>

      {/* Quick Actions */}
      <div>
        <h2 className="text-lg font-semibold text-gray-900 mb-3">Quick Actions</h2>
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
          {[
            { href: "/seller/campaigns/create", label: "Create Campaign", emoji: "➕" },
            { href: "/seller/campaigns", label: "My Campaigns", emoji: "📢" },
            { href: "/seller/wallet", label: "Wallet", emoji: "💰" },
            { href: "/seller/transactions", label: "Transactions", emoji: "💳" },
            { href: "/seller/profile", label: "Profile", emoji: "👤" },
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
