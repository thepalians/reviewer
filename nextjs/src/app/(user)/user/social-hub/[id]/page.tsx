import { auth } from "@/lib/auth";
import { redirect, notFound } from "next/navigation";
import { queryOne } from "@/lib/db";
import Link from "next/link";
import CampaignTaskClient from "./CampaignTaskClient";
import type { Metadata } from "next";
import type { RowDataPacket } from "mysql2";

export const metadata: Metadata = { title: "Social Task" };

interface CampaignRow extends RowDataPacket {
  id: number;
  seller_id: number;
  platform_id: number;
  title: string;
  description: string | null;
  url: string | null;
  reward_amount: string;
  required_time: number | null;
  status: string;
  admin_approved: number;
  created_at: Date;
  updated_at: Date;
  platform_name: string;
  platform_icon: string | null;
  platform_slug: string | null;
}

interface CompletionRow extends RowDataPacket {
  id: number;
}

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
    queryOne<CampaignRow>(
      `SELECT sc.*, sp.name AS platform_name, sp.icon AS platform_icon, sp.slug AS platform_slug
       FROM social_campaigns sc
       JOIN social_platforms sp ON sc.platform_id = sp.id
       WHERE sc.id = ? AND sc.status = 'active' AND sc.admin_approved = 1
       LIMIT 1`,
      [campaignId]
    ),
    queryOne<CompletionRow>(
      "SELECT id FROM social_task_completions WHERE user_id = ? AND campaign_id = ? LIMIT 1",
      [userId, campaignId]
    ),
  ]);

  if (!campaign) notFound();

  const serialized = {
    id: campaign.id,
    sellerId: campaign.seller_id,
    platformId: campaign.platform_id,
    title: campaign.title,
    description: campaign.description ?? undefined,
    url: campaign.url ?? undefined,
    rewardAmount: Number(campaign.reward_amount),
    requiredTime: campaign.required_time ?? undefined,
    status: campaign.status,
    adminApproved: Boolean(campaign.admin_approved),
    createdAt: new Date(campaign.created_at).toISOString(),
    updatedAt: new Date(campaign.updated_at).toISOString(),
    platform: {
      id: campaign.platform_id,
      name: campaign.platform_name,
      icon: campaign.platform_icon ?? undefined,
      slug: campaign.platform_slug ?? "",
      isActive: true,
      createdAt: "",
    },
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
          {campaign.platform_icon && <span className="text-2xl">{campaign.platform_icon}</span>}
          <span className="text-white/80 text-sm">{campaign.platform_name}</span>
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
