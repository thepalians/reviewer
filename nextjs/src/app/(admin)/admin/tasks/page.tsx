"use client";

import { useState, useEffect, useCallback } from "react";
import DataTable from "@/components/ui/DataTable";
import Badge from "@/components/ui/Badge";
import Button from "@/components/ui/Button";
import Modal from "@/components/ui/Modal";
import { formatDate, formatCurrency } from "@/lib/utils";

interface TaskStep {
  id: number;
  stepNumber: number;
  stepStatus: string;
}

interface Task {
  id: number;
  productName: string | null;
  platform: string | null;
  status: string;
  commission: string | null;
  deadline: string | null;
  createdAt: string;
  user: { id: number; name: string; email: string };
  steps: TaskStep[];
}

export default function AdminTasksPage() {
  const [tasks, setTasks] = useState<Task[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState("");
  const [isLoading, setIsLoading] = useState(true);
  const [selectedTask, setSelectedTask] = useState<Task | null>(null);
  const [isUpdating, setIsUpdating] = useState(false);

  const fetchTasks = useCallback(async () => {
    setIsLoading(true);
    const params = new URLSearchParams({ page: String(page), limit: "20" });
    if (statusFilter) params.set("status", statusFilter);

    const res = await fetch(`/api/admin/tasks?${params}`);
    const data = await res.json();
    if (data.success) {
      setTasks(data.data);
      setTotal(data.total);
    }
    setIsLoading(false);
  }, [page, statusFilter]);

  useEffect(() => { fetchTasks(); }, [fetchTasks]);

  const handleApproveStep = async (taskId: number, stepNumber: number) => {
    setIsUpdating(true);
    const res = await fetch(`/api/admin/tasks/${taskId}/approve-step`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ stepNumber }),
    });
    if (res.ok) {
      await fetchTasks();
      setSelectedTask(null);
    }
    setIsUpdating(false);
  };

  const handleRejectStep = async (taskId: number, stepNumber: number) => {
    setIsUpdating(true);
    const res = await fetch(`/api/admin/tasks/${taskId}/reject-step`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ stepNumber }),
    });
    if (res.ok) {
      await fetchTasks();
      setSelectedTask(null);
    }
    setIsUpdating(false);
  };

  const columns = [
    { key: "id", label: "ID", sortable: true },
    {
      key: "user",
      label: "User",
      render: (row: Task) => row.user?.name || "-",
    },
    {
      key: "productName",
      label: "Product",
      render: (row: Task) => row.productName || "-",
    },
    { key: "platform", label: "Platform", render: (row: Task) => row.platform || "-" },
    {
      key: "status",
      label: "Status",
      render: (row: Task) => <Badge label={row.status} status={row.status} />,
    },
    {
      key: "commission",
      label: "Commission",
      render: (row: Task) => row.commission ? formatCurrency(row.commission) : "-",
    },
    {
      key: "deadline",
      label: "Deadline",
      render: (row: Task) => row.deadline ? formatDate(row.deadline) : "-",
    },
    {
      key: "actions",
      label: "Actions",
      render: (row: Task) => (
        <Button variant="secondary" size="sm" onClick={() => setSelectedTask(row)}>
          View
        </Button>
      ),
    },
  ];

  const totalPages = Math.ceil(total / 20);

  return (
    <div className="space-y-6">
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">📋 Task Management</h1>
        <p className="text-white/80 mt-1">Review and manage all tasks</p>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <select
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
          className="h-10 px-3 border border-gray-200 rounded-lg text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#667eea]/20"
        >
          <option value="">All Statuses</option>
          <option value="assigned">Assigned</option>
          <option value="in_progress">In Progress</option>
          <option value="completed">Completed</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-100">
          <h2 className="font-semibold text-gray-900">Tasks ({total})</h2>
        </div>
        {isLoading ? (
          <div className="p-12 text-center text-gray-500">Loading...</div>
        ) : (
          <DataTable columns={columns} data={tasks} emptyMessage="No tasks found" />
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

      {/* Task Detail Modal */}
      {selectedTask && (
        <Modal
          isOpen={!!selectedTask}
          onClose={() => setSelectedTask(null)}
          title={`Task #${selectedTask.id}: ${selectedTask.productName || "N/A"}`}
          className="max-w-lg"
        >
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div><span className="text-gray-500">User:</span> <span className="font-medium">{selectedTask.user?.name}</span></div>
              <div><span className="text-gray-500">Platform:</span> <span className="font-medium">{selectedTask.platform || "-"}</span></div>
              <div><span className="text-gray-500">Commission:</span> <span className="font-medium">{selectedTask.commission ? formatCurrency(selectedTask.commission) : "-"}</span></div>
              <div><span className="text-gray-500">Status:</span> <Badge label={selectedTask.status} status={selectedTask.status} /></div>
            </div>

            {selectedTask.steps.length > 0 && (
              <div className="border-t pt-4">
                <p className="text-sm font-medium text-gray-700 mb-3">Steps:</p>
                <div className="space-y-2">
                  {selectedTask.steps.map((step) => (
                    <div key={step.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-medium">Step {step.stepNumber}</span>
                        <Badge label={step.stepStatus} status={step.stepStatus} />
                      </div>
                      {step.stepStatus === "pending" && (
                        <div className="flex gap-2">
                          <Button
                            variant="primary"
                            size="sm"
                            onClick={() => handleApproveStep(selectedTask.id, step.stepNumber)}
                            isLoading={isUpdating}
                          >
                            ✅ Approve
                          </Button>
                          <Button
                            variant="danger"
                            size="sm"
                            onClick={() => handleRejectStep(selectedTask.id, step.stepNumber)}
                            isLoading={isUpdating}
                          >
                            ❌ Reject
                          </Button>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        </Modal>
      )}
    </div>
  );
}
