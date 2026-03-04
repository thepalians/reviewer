"use client";

import { useState, useEffect, useCallback } from "react";
import DataTable from "@/components/ui/DataTable";
import Badge from "@/components/ui/Badge";
import Button from "@/components/ui/Button";
import Modal from "@/components/ui/Modal";
import { formatDate } from "@/lib/utils";

interface KycDocument {
  id: number;
  documentType: string;
  documentPath: string;
  status: string;
  notes: string | null;
  createdAt: string;
  user: { id: number; name: string; email: string };
}

export default function AdminKycPage() {
  const [documents, setDocuments] = useState<KycDocument[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState("");
  const [isLoading, setIsLoading] = useState(true);
  const [selected, setSelected] = useState<KycDocument | null>(null);
  const [action, setAction] = useState<"approved" | "rejected" | null>(null);
  const [notes, setNotes] = useState("");
  const [isUpdating, setIsUpdating] = useState(false);

  const fetchDocuments = useCallback(async () => {
    setIsLoading(true);
    const params = new URLSearchParams({ page: String(page), limit: "20" });
    if (statusFilter) params.set("status", statusFilter);
    const res = await fetch(`/api/admin/kyc?${params}`);
    const data = await res.json();
    if (data.success) {
      setDocuments(data.data);
      setTotal(data.total);
    }
    setIsLoading(false);
  }, [page, statusFilter]);

  useEffect(() => {
    fetchDocuments();
  }, [fetchDocuments]);

  const handleReview = async () => {
    if (!selected || !action) return;
    setIsUpdating(true);
    const res = await fetch(`/api/admin/kyc/${selected.id}`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ status: action, notes }),
    });
    if (res.ok) {
      setSelected(null);
      setAction(null);
      setNotes("");
      await fetchDocuments();
    }
    setIsUpdating(false);
  };

  const getStatusBadge = (status: string) => status;

  const columns = [
    { key: "id", label: "ID", sortable: true },
    {
      key: "user",
      label: "User",
      render: (row: KycDocument) => (
        <div>
          <p className="font-medium">{row.user.name}</p>
          <p className="text-xs text-gray-500">{row.user.email}</p>
        </div>
      ),
    },
    { key: "documentType", label: "Document Type", sortable: true },
    {
      key: "documentPath",
      label: "Document",
      render: (row: KycDocument) => (
        <a
          href={row.documentPath}
          target="_blank"
          rel="noopener noreferrer"
          className="text-[#667eea] hover:underline text-sm"
        >
          View Document
        </a>
      ),
    },
    {
      key: "status",
      label: "Status",
      render: (row: KycDocument) => (
        <Badge label={row.status} status={getStatusBadge(row.status)} />
      ),
    },
    {
      key: "createdAt",
      label: "Submitted",
      render: (row: KycDocument) => formatDate(row.createdAt),
    },
    {
      key: "actions",
      label: "Actions",
      render: (row: KycDocument) =>
        row.status === "pending" ? (
          <div className="flex gap-2">
            <Button
              variant="primary"
              size="sm"
              onClick={() => {
                setSelected(row);
                setAction("approved");
              }}
            >
              Approve
            </Button>
            <Button
              variant="danger"
              size="sm"
              onClick={() => {
                setSelected(row);
                setAction("rejected");
              }}
            >
              Reject
            </Button>
          </div>
        ) : (
          <span className="text-xs text-gray-400">Reviewed</span>
        ),
    },
  ];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">🔐 KYC Management</h1>
        <p className="text-white/80 mt-1">Review and approve KYC submissions</p>
      </div>

      {/* Filter */}
      <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
        <div className="flex gap-3">
          {["", "pending", "approved", "rejected"].map((s) => (
            <button
              key={s}
              onClick={() => {
                setStatusFilter(s);
                setPage(1);
              }}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                statusFilter === s
                  ? "bg-[#667eea] text-white"
                  : "bg-gray-100 text-gray-600 hover:bg-gray-200"
              }`}
            >
              {s === "" ? "All" : s.charAt(0).toUpperCase() + s.slice(1)}
            </button>
          ))}
        </div>
      </div>

      <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-100">
          <h2 className="font-semibold text-gray-900">KYC Submissions ({total})</h2>
        </div>
        {isLoading ? (
          <div className="p-12 text-center text-gray-500">Loading...</div>
        ) : (
          <DataTable columns={columns} data={documents} emptyMessage="No KYC submissions found" />
        )}
        {Math.ceil(total / 20) > 1 && (
          <div className="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
            <span className="text-sm text-gray-500">
              Page {page} of {Math.ceil(total / 20)}
            </span>
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
                onClick={() => setPage((p) => Math.min(Math.ceil(total / 20), p + 1))}
                disabled={page === Math.ceil(total / 20)}
              >
                Next
              </Button>
            </div>
          </div>
        )}
      </div>

      {/* Review Modal */}
      <Modal
        isOpen={!!selected}
        onClose={() => {
          setSelected(null);
          setAction(null);
          setNotes("");
        }}
        title={`${action === "approved" ? "Approve" : "Reject"} KYC Document`}
      >
        {selected && (
          <div className="space-y-4">
            <div>
              <p className="text-sm text-gray-600">
                <span className="font-medium">User:</span> {selected.user.name}
              </p>
              <p className="text-sm text-gray-600">
                <span className="font-medium">Document:</span> {selected.documentType}
              </p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Notes (optional)
              </label>
              <textarea
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                rows={3}
                placeholder="Add any notes for the user..."
                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#667eea]/30"
              />
            </div>
            <div className="flex gap-3 justify-end">
              <Button
                variant="secondary"
                onClick={() => {
                  setSelected(null);
                  setAction(null);
                  setNotes("");
                }}
              >
                Cancel
              </Button>
              <Button
                variant={action === "approved" ? "primary" : "danger"}
                isLoading={isUpdating}
                onClick={handleReview}
              >
                {action === "approved" ? "Approve" : "Reject"}
              </Button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}
