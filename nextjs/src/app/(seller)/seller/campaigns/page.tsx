"use client";

import { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import DataTable from "@/components/ui/DataTable";
import Badge from "@/components/ui/Badge";
import Button from "@/components/ui/Button";
import { formatCurrency, formatDate } from "@/lib/utils";

interface Campaign {
  id: number;
  title: string;
  status: string;
  rewardAmount: string;
  requiredTime: number | null;
  adminApproved: boolean;
  createdAt: string;
  platform: { id: number; name: string };
  _count: { completions: number };
}

interface Platform {
  id: number;
  name: string;
}

export default function SellerCampaignsPage() {
  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [platforms, setPlatforms] = useState<Platform[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState("");
  const [platformFilter, setPlatformFilter] = useState("");
  const [isLoading, setIsLoading] = useState(true);

  const fetchCampaigns = useCallback(async () => {
    setIsLoading(true);
    const params = new URLSearchParams({ page: String(page), limit: "20" });
    if (statusFilter) params.set("status", statusFilter);
    if (platformFilter) params.set("platformId", platformFilter);

    const res = await fetch(`/api/seller/campaigns?${params}`);
    const data = await res.json();
    if (data.success) {
      setCampaigns(data.data);
      setTotal(data.total);
    }
    setIsLoading(false);
  }, [page, statusFilter, platformFilter]);

  useEffect(() => {
    fetch("/api/seller/platforms")
      .then((r) => r.json())
      .then((d) => { if (d.success) setPlatforms(d.data); })
      .catch(() => {});
  }, []);

  useEffect(() => { fetchCampaigns(); }, [fetchCampaigns]);

  const columns = [
    { key: "id", label: "ID", sortable: true },
    { key: "title", label: "Title" },
    {
      key: "platform",
      label: "Platform",
      render: (row: Campaign) => row.platform?.name || "-",
    },
    {
      key: "rewardAmount",
      label: "Reward",
      render: (row: Campaign) => formatCurrency(row.rewardAmount),
    },
    {
      key: "status",
      label: "Status",
      render: (row: Campaign) => <Badge label={row.status} status={row.status} />,
    },
    {
      key: "adminApproved",
      label: "Approved",
      render: (row: Campaign) => (
        <Badge
          label={row.adminApproved ? "Yes" : "No"}
          status={row.adminApproved ? "approved" : "pending"}
        />
      ),
    },
    {
      key: "completions",
      label: "Completions",
      render: (row: Campaign) => row._count.completions,
    },
    {
      key: "createdAt",
      label: "Created",
      render: (row: Campaign) => formatDate(row.createdAt),
    },
    {
      key: "actions",
      label: "Actions",
      render: (row: Campaign) => (
        <Link href={`/seller/campaigns/${row.id}`}>
          <Button variant="secondary" size="sm">View</Button>
        </Link>
      ),
    },
  ];

  const totalPages = Math.ceil(total / 20);

  return (
    <div className="space-y-6">
      <div className="bg-gradient-to-r from-[#11998e] to-[#38ef7d] rounded-2xl p-6 text-white flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">📢 My Campaigns</h1>
          <p className="text-white/80 mt-1">Manage your social campaigns</p>
        </div>
        <Link href="/seller/campaigns/create">
          <Button variant="primary" size="md" className="bg-white text-[#11998e] hover:bg-white/90">
            ➕ Create Campaign
          </Button>
        </Link>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex flex-wrap gap-3">
        <select
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
          className="h-10 px-3 border border-gray-200 rounded-lg text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#11998e]/20"
        >
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="active">Active</option>
          <option value="completed">Completed</option>
          <option value="rejected">Rejected</option>
        </select>
        <select
          value={platformFilter}
          onChange={(e) => { setPlatformFilter(e.target.value); setPage(1); }}
          className="h-10 px-3 border border-gray-200 rounded-lg text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#11998e]/20"
        >
          <option value="">All Platforms</option>
          {platforms.map((p) => (
            <option key={p.id} value={p.id}>{p.name}</option>
          ))}
        </select>
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-100">
          <h2 className="font-semibold text-gray-900">Campaigns ({total})</h2>
        </div>
        {isLoading ? (
          <div className="p-12 text-center text-gray-500">Loading...</div>
        ) : (
          <DataTable columns={columns} data={campaigns} emptyMessage="No campaigns found" />
        )}
        {totalPages > 1 && (
          <div className="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
            <span className="text-sm text-gray-500">Page {page} of {totalPages}</span>
            <div className="flex gap-2">
              <Button
                variant="secondary"
                size="sm"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page === 1}
              >
                Prev
              </Button>
              <Button
                variant="secondary"
                size="sm"
                onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                disabled={page === totalPages}
              >
                Next
              </Button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
