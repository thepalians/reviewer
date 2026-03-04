"use client";

import { useState, useEffect, use } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import Badge from "@/components/ui/Badge";
import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";
import { formatCurrency, formatDate, formatDateTime } from "@/lib/utils";

interface Completion {
  id: number;
  completedAt: string;
  reward: string | null;
  user: { id: number; name: string; email: string };
}

interface Session {
  id: number;
  startedAt: string;
  endedAt: string | null;
  duration: number | null;
  user: { id: number; name: string };
}

interface Campaign {
  id: number;
  title: string;
  description: string | null;
  url: string | null;
  rewardAmount: string;
  requiredTime: number | null;
  status: string;
  adminApproved: boolean;
  createdAt: string;
  updatedAt: string;
  platform: { id: number; name: string };
  completions: Completion[];
  sessions: Session[];
  _count: { completions: number; sessions: number };
  totalSpent: number;
  avgWatchTime: number;
}

export default function CampaignDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const [campaign, setCampaign] = useState<Campaign | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isEditing, setIsEditing] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [editForm, setEditForm] = useState({ title: "", description: "", url: "", rewardAmount: "", requiredTime: "" });
  const [error, setError] = useState("");

  useEffect(() => {
    fetch(`/api/seller/campaigns/${id}`)
      .then((r) => r.json())
      .then((d) => {
        if (d.success) {
          setCampaign(d.data);
          setEditForm({
            title: d.data.title,
            description: d.data.description || "",
            url: d.data.url || "",
            rewardAmount: String(d.data.rewardAmount),
            requiredTime: d.data.requiredTime ? String(d.data.requiredTime) : "",
          });
        }
        setIsLoading(false);
      })
      .catch(() => setIsLoading(false));
  }, [id]);

  const handleEditChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>
  ) => {
    setEditForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleSave = async () => {
    setIsSaving(true);
    setError("");
    try {
      const res = await fetch(`/api/seller/campaigns/${id}`, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(editForm),
      });
      const data = await res.json();
      if (data.success) {
        setCampaign((prev) => prev ? { ...prev, ...data.data } : prev);
        setIsEditing(false);
      } else {
        setError(data.error || "Failed to update campaign");
      }
    } catch {
      setError("Something went wrong.");
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-gray-500">Loading...</div>
      </div>
    );
  }

  if (!campaign) {
    return (
      <div className="text-center py-12">
        <p className="text-gray-500">Campaign not found.</p>
        <Link href="/seller/campaigns" className="text-[#11998e] underline mt-2 inline-block">Back to campaigns</Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#11998e] to-[#38ef7d] rounded-2xl p-6 text-white">
        <div className="flex items-center gap-3 mb-1">
          <button onClick={() => router.back()} className="text-white/80 hover:text-white text-sm">← Back</button>
        </div>
        <h1 className="text-2xl font-bold">{campaign.title}</h1>
        <p className="text-white/80 mt-1">{campaign.platform.name}</p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        {[
          { label: "Total Completions", value: campaign._count.completions, icon: "✅" },
          { label: "Total Spent", value: formatCurrency(campaign.totalSpent), icon: "💰" },
          { label: "Avg Watch Time", value: `${campaign.avgWatchTime}s`, icon: "⏱️" },
          { label: "Watch Sessions", value: campaign._count.sessions, icon: "👁️" },
        ].map((stat) => (
          <div key={stat.label} className="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
            <div className="text-2xl mb-1">{stat.icon}</div>
            <div className="text-xl font-bold text-gray-900">{stat.value}</div>
            <div className="text-xs text-gray-500 mt-0.5">{stat.label}</div>
          </div>
        ))}
      </div>

      {/* Campaign Info */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="font-semibold text-gray-900">Campaign Info</h2>
          {campaign.status === "pending" && !isEditing && (
            <Button variant="secondary" size="sm" onClick={() => setIsEditing(true)}>
              ✏️ Edit
            </Button>
          )}
        </div>

        {isEditing ? (
          <div className="space-y-4">
            <Input label="Title" name="title" value={editForm.title} onChange={handleEditChange} />
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea
                name="description"
                value={editForm.description}
                onChange={handleEditChange}
                rows={3}
                className="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#11998e]/20 focus:border-[#11998e] resize-none"
              />
            </div>
            <Input label="URL" name="url" value={editForm.url} onChange={handleEditChange} />
            <div className="grid grid-cols-2 gap-4">
              <Input label="Reward Amount (₹)" name="rewardAmount" type="number" value={editForm.rewardAmount} onChange={handleEditChange} />
              <Input label="Required Time (s)" name="requiredTime" type="number" value={editForm.requiredTime} onChange={handleEditChange} />
            </div>
            {error && <p className="text-sm text-red-600">❌ {error}</p>}
            <div className="flex gap-3">
              <Button variant="secondary" size="sm" onClick={() => setIsEditing(false)} className="flex-1">Cancel</Button>
              <Button variant="primary" size="sm" onClick={handleSave} isLoading={isSaving} className="flex-1">Save Changes</Button>
            </div>
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div><span className="text-gray-500">Status:</span> <Badge label={campaign.status} status={campaign.status} /></div>
            <div><span className="text-gray-500">Approved:</span> <Badge label={campaign.adminApproved ? "Yes" : "No"} status={campaign.adminApproved ? "approved" : "pending"} /></div>
            <div><span className="text-gray-500">Reward:</span> <span className="font-medium">{formatCurrency(campaign.rewardAmount)}</span></div>
            <div><span className="text-gray-500">Required Time:</span> <span className="font-medium">{campaign.requiredTime ? `${campaign.requiredTime}s` : "—"}</span></div>
            {campaign.url && (
              <div className="col-span-2"><span className="text-gray-500">URL:</span>{" "}
                <a href={campaign.url} target="_blank" rel="noopener noreferrer" className="text-[#11998e] underline break-all">{campaign.url}</a>
              </div>
            )}
            {campaign.description && (
              <div className="col-span-2"><span className="text-gray-500">Description:</span> <span className="font-medium">{campaign.description}</span></div>
            )}
            <div><span className="text-gray-500">Created:</span> <span className="font-medium">{formatDate(campaign.createdAt)}</span></div>
          </div>
        )}
      </div>

      {/* Completions */}
      {campaign.completions.length > 0 && (
        <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-100">
            <h2 className="font-semibold text-gray-900">Recent Completions ({campaign._count.completions})</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  {["User", "Email", "Reward", "Completed At"].map((h) => (
                    <th key={h} className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {campaign.completions.map((c) => (
                  <tr key={c.id} className="hover:bg-gray-50">
                    <td className="px-6 py-3 text-sm font-medium text-gray-900">{c.user.name}</td>
                    <td className="px-6 py-3 text-sm text-gray-600">{c.user.email}</td>
                    <td className="px-6 py-3 text-sm text-gray-900">{c.reward ? formatCurrency(c.reward) : "—"}</td>
                    <td className="px-6 py-3 text-sm text-gray-600">{formatDateTime(c.completedAt)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Watch Sessions */}
      {campaign.sessions.length > 0 && (
        <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-100">
            <h2 className="font-semibold text-gray-900">Watch Sessions ({campaign._count.sessions})</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  {["User", "Started At", "Duration"].map((h) => (
                    <th key={h} className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {campaign.sessions.map((s) => (
                  <tr key={s.id} className="hover:bg-gray-50">
                    <td className="px-6 py-3 text-sm font-medium text-gray-900">{s.user.name}</td>
                    <td className="px-6 py-3 text-sm text-gray-600">{formatDateTime(s.startedAt)}</td>
                    <td className="px-6 py-3 text-sm text-gray-900">{s.duration ? `${s.duration}s` : "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
