import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import SocialHubClient from "./SocialHubClient";
import type { Metadata } from "next";

export const metadata: Metadata = { title: "Social Hub" };

export default async function SocialHubPage() {
  const session = await auth();
  if (!session || session.user.userType !== "user") redirect("/login");

  const [campaigns, platforms] = await Promise.all([
    prisma.socialCampaign.findMany({
      where: { status: "active", adminApproved: true },
      include: { platform: true },
      orderBy: { createdAt: "desc" },
    }),
    prisma.socialPlatform.findMany({
      where: { isActive: true },
      orderBy: { name: "asc" },
    }),
  ]);

  const serialized = campaigns.map((c) => ({
    ...c,
    rewardAmount: Number(c.rewardAmount),
    createdAt: c.createdAt.toISOString(),
    updatedAt: c.updatedAt.toISOString(),
  }));

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">📱 Social Hub</h1>
        <p className="text-white/80 mt-1">Watch &amp; Earn — complete social tasks to earn rewards</p>
      </div>

      <SocialHubClient campaigns={serialized} platforms={platforms} />
    </div>
  );
}
