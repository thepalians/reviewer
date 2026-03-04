"use client";

import { useState, useEffect, useCallback } from "react";
import DataTable from "@/components/ui/DataTable";
import Badge from "@/components/ui/Badge";
import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";
import Modal from "@/components/ui/Modal";
import { formatDate, formatCurrency } from "@/lib/utils";

interface User {
  id: number;
  name: string;
  email: string;
  mobile: string;
  status: string;
  walletBalance: string;
  createdAt: string;
  _count: { tasks: number };
}

export default function AdminUsersPage() {
  const [users, setUsers] = useState<User[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [isLoading, setIsLoading] = useState(true);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);
  const [isUpdating, setIsUpdating] = useState(false);

  const fetchUsers = useCallback(async () => {
    setIsLoading(true);
    const params = new URLSearchParams({ page: String(page), limit: "20" });
    if (search) params.set("search", search);
    if (statusFilter) params.set("status", statusFilter);

    const res = await fetch(`/api/admin/users?${params}`);
    const data = await res.json();
    if (data.success) {
      setUsers(data.data);
      setTotal(data.total);
    }
    setIsLoading(false);
  }, [page, search, statusFilter]);

  useEffect(() => { fetchUsers(); }, [fetchUsers]);

  const handleStatusUpdate = async (userId: number, newStatus: string) => {
    setIsUpdating(true);
    const res = await fetch(`/api/admin/users/${userId}`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ status: newStatus }),
    });
    if (res.ok) {
      await fetchUsers();
      setSelectedUser(null);
    }
    setIsUpdating(false);
  };

  const columns = [
    { key: "id", label: "ID", sortable: true },
    { key: "name", label: "Name", sortable: true },
    { key: "email", label: "Email" },
    { key: "mobile", label: "Mobile" },
    {
      key: "status",
      label: "Status",
      render: (row: User) => <Badge label={row.status} status={row.status} />,
    },
    {
      key: "walletBalance",
      label: "Wallet",
      render: (row: User) => formatCurrency(row.walletBalance),
    },
    {
      key: "tasks",
      label: "Tasks",
      render: (row: User) => row._count.tasks,
    },
    {
      key: "createdAt",
      label: "Joined",
      render: (row: User) => formatDate(row.createdAt),
    },
    {
      key: "actions",
      label: "Actions",
      render: (row: User) => (
        <Button
          variant="secondary"
          size="sm"
          onClick={() => setSelectedUser(row)}
        >
          Manage
        </Button>
      ),
    },
  ];

  const totalPages = Math.ceil(total / 20);

  return (
    <div className="space-y-6">
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">👥 User Management</h1>
        <p className="text-white/80 mt-1">Manage all registered users</p>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <div className="flex flex-wrap gap-3">
          <div className="flex-1 min-w-[200px]">
            <Input
              label=""
              placeholder="Search by name, email, mobile..."
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1); }}
            />
          </div>
          <div>
            <select
              value={statusFilter}
              onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
              className="h-10 px-3 border border-gray-200 rounded-lg text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#667eea]/20"
            >
              <option value="">All Statuses</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="banned">Banned</option>
            </select>
          </div>
        </div>
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 className="font-semibold text-gray-900">
            Users ({total})
          </h2>
        </div>
        {isLoading ? (
          <div className="p-12 text-center text-gray-500">Loading...</div>
        ) : (
          <DataTable columns={columns} data={users} emptyMessage="No users found" />
        )}
        {/* Pagination */}
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

      {/* User Detail Modal */}
      {selectedUser && (
        <Modal
          isOpen={!!selectedUser}
          onClose={() => setSelectedUser(null)}
          title={`Manage User: ${selectedUser.name}`}
        >
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div><span className="text-gray-500">Email:</span> <span className="font-medium">{selectedUser.email}</span></div>
              <div><span className="text-gray-500">Mobile:</span> <span className="font-medium">{selectedUser.mobile}</span></div>
              <div><span className="text-gray-500">Wallet:</span> <span className="font-medium">{formatCurrency(selectedUser.walletBalance)}</span></div>
              <div><span className="text-gray-500">Tasks:</span> <span className="font-medium">{selectedUser._count.tasks}</span></div>
              <div><span className="text-gray-500">Joined:</span> <span className="font-medium">{formatDate(selectedUser.createdAt)}</span></div>
              <div><span className="text-gray-500">Status:</span> <Badge label={selectedUser.status} status={selectedUser.status} /></div>
            </div>
            <div className="border-t pt-4">
              <p className="text-sm font-medium text-gray-700 mb-2">Change Status:</p>
              <div className="flex gap-2 flex-wrap">
                <Button
                  variant="primary"
                  size="sm"
                  onClick={() => handleStatusUpdate(selectedUser.id, "active")}
                  isLoading={isUpdating}
                  disabled={selectedUser.status === "active"}
                >
                  ✅ Activate
                </Button>
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => handleStatusUpdate(selectedUser.id, "inactive")}
                  isLoading={isUpdating}
                  disabled={selectedUser.status === "inactive"}
                >
                  ⏸️ Deactivate
                </Button>
                <Button
                  variant="danger"
                  size="sm"
                  onClick={() => handleStatusUpdate(selectedUser.id, "banned")}
                  isLoading={isUpdating}
                  disabled={selectedUser.status === "banned"}
                >
                  🚫 Ban
                </Button>
              </div>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}
