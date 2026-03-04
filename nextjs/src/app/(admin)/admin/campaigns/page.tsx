"use client";

import { useState, useEffect, useCallback } from "react";
import DataTable from "@/components/ui/DataTable";
import Badge from "@/components/ui/Badge";
import Button from "@/components/ui/Button";
import Modal from "@/components/ui/Modal";
import { formatDate, formatCurrency } from "@/lib/utils";

interface Campaign {
  id: number;
  title: string;
  rewardAmount: string;
  status: string;
  adminApproved: boolean;
  createdAt: string;
  seller: { id: number; name: string; email: string };
  platform: { id: number; name: string };
  _count: { completions: number };
}

export default function AdminCampaignsPage() {
  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState("");
  const [isLoading, setIsLoading] = useState(true);
  const [selectedItem, setSelectedItem] = useState<Campaign | null>(null);
  const [isUpdating, setIsUpdating] = useState(false);

  const fetchCampaigns = useCallback(async () => {
    setIsLoading(true);
    const params = new URLSearchParams({ page: String(page), limit: "20" });
    if (statusFilter) params.set("status", statusFilter);

    const res = await fetch(`/api/admin/campaigns?${params}`);
    const data = await res.json();
    if (data.success) {
      setCampaigns(data.data);
      setTotal(data.total);
    }
    setIsLoading(false);
  }, [page, statusFilter]);

  useEffect(() => { fetchCampaigns(); }, [fetchCampaigns]);

  const handleAction = async (id: number, action: "approve" | "reject") => {
    setIsUpdating(true);
    const res = await fetch(`/api/admin/campaigns/${id}`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action }),
    });
    if (res.ok) {
      await fetchCampaigns();
      setSelectedItem(null);
    }
    setIsUpdating(false);
  };

  const columns = [
    { key: "id", label: "ID", sortable: true },
    { key: "title", label: "Title" },
    { key: "seller", label: "Seller", render: (row: Campaign) => row.seller?.name || "-" },
    { key: "platform", label: "Platform", render: (row: Campaign) => row.platform?.name || "-" },
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
        <Badge label={row.adminApproved ? "Yes" : "No"} status={row.adminApproved ? "approved" : "pending"} />
      ),
    },
    {
      key: "completions",
      label: "Completions",
      render: (row: Campaign) => row._count.completions,
    },
    {
      key: "actions",
      label: "Actions",
      render: (row: Campaign) => (
        <Button variant="secondary" size="sm" onClick={() => setSelectedItem(row)}>
          View
        </Button>
      ),
    },
  ];

  const totalPages = Math.ceil(total / 20);

  return (
    <div className="space-y-6">
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">📱 Campaign Management</h1>
        <p className="text-white/80 mt-1">Approve and manage social campaigns</p>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <select
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
          className="h-10 px-3 border border-gray-200 rounded-lg text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#667eea]/20"
        >
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="active">Active</option>
          <option value="rejected">Rejected</option>
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
              <Button variant="secondary" size="sm" onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1}>Prev</Button>
              <Button variant="secondary" size="sm" onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={page === totalPages}>Next</Button>
            </div>
          </div>
        )}
      </div>

      {/* Detail Modal */}
      {selectedItem && (
        <Modal
          isOpen={!!selectedItem}
          onClose={() => setSelectedItem(null)}
          title={`Campaign: ${selectedItem.title}`}
        >
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div><span className="text-gray-500">Seller:</span> <span className="font-medium">{selectedItem.seller?.name}</span></div>
              <div><span className="text-gray-500">Platform:</span> <span className="font-medium">{selectedItem.platform?.name}</span></div>
              <div><span className="text-gray-500">Reward:</span> <span className="font-medium">{formatCurrency(selectedItem.rewardAmount)}</span></div>
              <div><span className="text-gray-500">Completions:</span> <span className="font-medium">{selectedItem._count.completions}</span></div>
              <div><span className="text-gray-500">Status:</span> <Badge label={selectedItem.status} status={selectedItem.status} /></div>
              <div><span className="text-gray-500">Approved:</span> <Badge label={selectedItem.adminApproved ? "Yes" : "No"} status={selectedItem.adminApproved ? "approved" : "pending"} /></div>
              <div className="col-span-2"><span className="text-gray-500">Created:</span> <span className="font-medium">{formatDate(selectedItem.createdAt)}</span></div>
            </div>

            {!selectedItem.adminApproved && selectedItem.status !== "rejected" && (
              <div className="border-t pt-4 flex gap-3">
                <Button
                  variant="primary"
                  size="sm"
                  onClick={() => handleAction(selectedItem.id, "approve")}
                  isLoading={isUpdating}
                  className="flex-1"
                >
                  ✅ Approve
                </Button>
                <Button
                  variant="danger"
                  size="sm"
                  onClick={() => handleAction(selectedItem.id, "reject")}
                  isLoading={isUpdating}
                  className="flex-1"
                >
                  ❌ Reject
                </Button>
              </div>
            )}
          </div>
        </Modal>
      )}
    </div>
  );
}
