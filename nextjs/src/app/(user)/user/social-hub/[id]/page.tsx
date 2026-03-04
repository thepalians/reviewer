import { auth } from "@/lib/auth";
import { redirect, notFound } from "next/navigation";
import { prisma } from "@/lib/db";
import Link from "next/link";
import CampaignTaskClient from "./CampaignTaskClient";
import type { Metadata } from "next";

export const metadata: Metadata = { title: "Social Task" };

export default async function CampaignTaskPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const session = await auth();
  if (!session || session.user.userType !== "user") redirect("/login");

  const { id } = await params;
  const campaignId = parseInt(id);
  if (isNaN(campaignId)) notFound();

  const userId = parseInt(session.user.id);

  const [campaign, completion] = await Promise.all([
    prisma.socialCampaign.findFirst({
      where: { id: campaignId, status: "active", adminApproved: true },
      include: { platform: true },
    }),
    prisma.socialTaskCompletion.findUnique({
      where: { userId_campaignId: { userId, campaignId } },
    }),
  ]);

  if (!campaign) notFound();

  const serialized = {
    ...campaign,
    rewardAmount: Number(campaign.rewardAmount),
    createdAt: campaign.createdAt.toISOString(),
    updatedAt: campaign.updatedAt.toISOString(),
  };

  return (
    <div className="space-y-4">
      <Link
        href="/user/social-hub"
        className="inline-flex items-center gap-1 text-sm text-[#667eea] hover:underline"
      >
        ← Back to Social Hub
      </Link>

      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <div className="flex items-center gap-2 mb-1">
          {campaign.platform.icon && <span className="text-2xl">{campaign.platform.icon}</span>}
          <span className="text-white/80 text-sm">{campaign.platform.name}</span>
        </div>
        <h1 className="text-xl font-bold">{campaign.title}</h1>
        {campaign.description && (
          <p className="text-white/80 text-sm mt-1">{campaign.description}</p>
        )}
      </div>

      <CampaignTaskClient
        campaign={serialized}
        alreadyCompleted={!!completion}
      />
    </div>
  );
}
