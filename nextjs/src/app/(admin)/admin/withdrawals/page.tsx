"use client";

import { useState, useEffect, useCallback } from "react";
import DataTable from "@/components/ui/DataTable";
import Badge from "@/components/ui/Badge";
import Button from "@/components/ui/Button";
import Modal from "@/components/ui/Modal";
import { formatDate, formatCurrency } from "@/lib/utils";

interface Withdrawal {
  id: number;
  amount: string;
  type: string;
  description: string | null;
  createdAt: string;
  user: {
    id: number;
    name: string;
    email: string;
    upiId: string | null;
    accountNumber: string | null;
    bankName: string | null;
  };
}

function getWithdrawalStatus(type: string) {
  if (type === "withdrawal_pending") return "pending";
  if (type === "withdrawal_approved") return "approved";
  if (type === "withdrawal_rejected") return "rejected";
  return type;
}

export default function AdminWithdrawalsPage() {
  const [withdrawals, setWithdrawals] = useState<Withdrawal[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState("");
  const [isLoading, setIsLoading] = useState(true);
  const [selectedItem, setSelectedItem] = useState<Withdrawal | null>(null);
  const [isUpdating, setIsUpdating] = useState(false);

  const fetchWithdrawals = useCallback(async () => {
    setIsLoading(true);
    const params = new URLSearchParams({ page: String(page), limit: "20" });
    if (statusFilter) params.set("status", statusFilter);

    const res = await fetch(`/api/admin/withdrawals?${params}`);
    const data = await res.json();
    if (data.success) {
      setWithdrawals(data.data);
      setTotal(data.total);
    }
    setIsLoading(false);
  }, [page, statusFilter]);

  useEffect(() => { fetchWithdrawals(); }, [fetchWithdrawals]);

  const handleAction = async (id: number, action: "approve" | "reject") => {
    setIsUpdating(true);
    const res = await fetch(`/api/admin/withdrawals/${id}`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action }),
    });
    if (res.ok) {
      await fetchWithdrawals();
      setSelectedItem(null);
    }
    setIsUpdating(false);
  };

  const columns = [
    { key: "id", label: "ID", sortable: true },
    { key: "user", label: "User", render: (row: Withdrawal) => row.user?.name || "-" },
    { key: "amount", label: "Amount", render: (row: Withdrawal) => formatCurrency(row.amount) },
    {
      key: "type",
      label: "Status",
      render: (row: Withdrawal) => {
        const s = getWithdrawalStatus(row.type);
        return <Badge label={s} status={s} />;
      },
    },
    {
      key: "createdAt",
      label: "Date",
      render: (row: Withdrawal) => formatDate(row.createdAt),
    },
    {
      key: "actions",
      label: "Actions",
      render: (row: Withdrawal) => (
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
        <h1 className="text-2xl font-bold">💸 Withdrawal Management</h1>
        <p className="text-white/80 mt-1">Approve or reject withdrawal requests</p>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <select
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
          className="h-10 px-3 border border-gray-200 rounded-lg text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#667eea]/20"
        >
          <option value="">All</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-100">
          <h2 className="font-semibold text-gray-900">Withdrawals ({total})</h2>
        </div>
        {isLoading ? (
          <div className="p-12 text-center text-gray-500">Loading...</div>
        ) : (
          <DataTable columns={columns} data={withdrawals} emptyMessage="No withdrawals found" />
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
          title={`Withdrawal #${selectedItem.id}`}
        >
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div><span className="text-gray-500">User:</span> <span className="font-medium">{selectedItem.user?.name}</span></div>
              <div><span className="text-gray-500">Email:</span> <span className="font-medium">{selectedItem.user?.email}</span></div>
              <div><span className="text-gray-500">Amount:</span> <span className="font-medium">{formatCurrency(selectedItem.amount)}</span></div>
              <div>
                <span className="text-gray-500">Status:</span>{" "}
                <Badge label={getWithdrawalStatus(selectedItem.type)} status={getWithdrawalStatus(selectedItem.type)} />
              </div>
              {selectedItem.user?.upiId && (
                <div className="col-span-2"><span className="text-gray-500">UPI ID:</span> <span className="font-medium">{selectedItem.user.upiId}</span></div>
              )}
              {selectedItem.user?.bankName && (
                <div className="col-span-2"><span className="text-gray-500">Bank:</span> <span className="font-medium">{selectedItem.user.bankName} - {selectedItem.user.accountNumber}</span></div>
              )}
              <div className="col-span-2"><span className="text-gray-500">Date:</span> <span className="font-medium">{formatDate(selectedItem.createdAt)}</span></div>
            </div>

            {getWithdrawalStatus(selectedItem.type) === "pending" && (
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
