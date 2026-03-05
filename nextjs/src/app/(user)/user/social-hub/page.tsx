import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { query } from "@/lib/db";
import SocialHubClient from "./SocialHubClient";
import type { Metadata } from "next";
import type { RowDataPacket } from "mysql2";

export const metadata: Metadata = { title: "Social Hub" };

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

interface PlatformRow extends RowDataPacket {
  id: number;
  name: string;
  icon: string | null;
  slug: string | null;
  is_active: number;
  created_at: Date;
}

export default async function SocialHubPage() {
  const session = await auth();
  if (!session || session.user.userType !== "user") redirect("/login");

  const [campaignRows, platformRows] = await Promise.all([
    query<CampaignRow>(
      `SELECT sc.*, sp.name AS platform_name, sp.icon AS platform_icon, sp.slug AS platform_slug
       FROM social_campaigns sc
       JOIN social_platforms sp ON sc.platform_id = sp.id
       WHERE sc.status = 'active' AND sc.admin_approved = 1
       ORDER BY sc.created_at DESC`,
      []
    ),
    query<PlatformRow>(
      "SELECT * FROM social_platforms ORDER BY name ASC",
      []
    ),
  ]);

  const campaigns = campaignRows.map((c) => ({
    id: c.id,
    sellerId: c.seller_id,
    platformId: c.platform_id,
    title: c.title,
    description: c.description ?? null,
    url: c.url ?? null,
    rewardAmount: Number(c.reward_amount),
    requiredTime: c.required_time ?? null,
    status: c.status,
    adminApproved: Boolean(c.admin_approved),
    createdAt: new Date(c.created_at).toISOString(),
    updatedAt: new Date(c.updated_at).toISOString(),
    platform: {
      id: c.platform_id,
      name: c.platform_name,
      icon: c.platform_icon ?? null,
      slug: c.platform_slug ?? "",
      isActive: true,
      createdAt: "",
    },
  }));

  const platforms = platformRows.map((p) => ({
    id: p.id,
    name: p.name,
    icon: p.icon ?? null,
    slug: p.slug ?? "",
    isActive: Boolean(p.is_active),
    createdAt: new Date(p.created_at).toISOString(),
  }));

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">📱 Social Hub</h1>
        <p className="text-white/80 mt-1">Watch &amp; Earn — complete social tasks to earn rewards</p>
      </div>

      <SocialHubClient campaigns={campaigns} platforms={platforms} />
    </div>
  );
}
