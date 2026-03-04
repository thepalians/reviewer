"use client";

import { useState } from "react";
import Link from "next/link";
import Badge from "@/components/ui/Badge";
import { formatCurrency, formatDate } from "@/lib/utils";
import type { Task } from "@/types";

const STATUS_TABS = [
  { label: "All", value: "" },
  { label: "Assigned", value: "assigned" },
  { label: "In Progress", value: "in_progress" },
  { label: "Completed", value: "completed" },
  { label: "Rejected", value: "rejected" },
];

interface TaskListProps {
  initialTasks: Task[];
  initialTotal: number;
}

export default function TaskList({ initialTasks, initialTotal }: TaskListProps) {
  const [tasks, setTasks] = useState<Task[]>(initialTasks);
  const [total, setTotal] = useState(initialTotal);
  const [activeTab, setActiveTab] = useState("");
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const limit = 10;
  const totalPages = Math.ceil(total / limit);

  async function fetchTasks(status: string, newPage: number) {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(newPage), limit: String(limit) });
      if (status) params.set("status", status);
      const res = await fetch(`/api/user/tasks?${params}`);
      const json = await res.json();
      if (json.success) {
        setTasks(json.data.items);
        setTotal(json.data.total);
      }
    } finally {
      setLoading(false);
    }
  }

  function handleTabChange(status: string) {
    setActiveTab(status);
    setPage(1);
    fetchTasks(status, 1);
  }

  function handlePageChange(newPage: number) {
    setPage(newPage);
    fetchTasks(activeTab, newPage);
  }

  return (
    <div className="space-y-4">
      {/* Filter tabs */}
      <div className="flex gap-2 flex-wrap">
        {STATUS_TABS.map((tab) => (
          <button
            key={tab.value}
            onClick={() => handleTabChange(tab.value)}
            className={`px-4 py-1.5 rounded-full text-sm font-medium transition-colors duration-150 ${
              activeTab === tab.value
                ? "bg-gradient-to-r from-[#667eea] to-[#764ba2] text-white shadow"
                : "bg-white border border-gray-200 text-gray-600 hover:border-[#667eea] hover:text-[#667eea]"
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Task cards */}
      {loading ? (
        <div className="flex justify-center py-12">
          <div className="animate-spin rounded-full h-10 w-10 border-4 border-[#667eea] border-t-transparent" />
        </div>
      ) : tasks.length === 0 ? (
        <div className="bg-white rounded-xl border border-gray-100 p-12 text-center text-gray-500">
          <p className="text-4xl mb-2">📋</p>
          <p>No tasks found.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {tasks.map((task) => (
            <Link key={task.id} href={`/user/tasks/${task.id}`}>
              <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
                <div className="flex items-start justify-between gap-3">
                  <div className="flex-1 min-w-0">
                    <p className="font-semibold text-gray-900 truncate">
                      {task.productName || `Task #${task.id}`}
                    </p>
                    <div className="flex items-center gap-2 mt-0.5 flex-wrap">
                      {task.platform && (
                        <span className="text-xs text-gray-500">📱 {task.platform}</span>
                      )}
                      <span className="text-xs text-gray-400">ID: #{task.id}</span>
                    </div>
                  </div>
                  <Badge label={task.status.replace(/_/g, " ")} status={task.status} />
                </div>

                <div className="mt-3 flex items-center justify-between text-sm">
                  {task.commission != null && (
                    <span className="font-semibold text-green-600">
                      💰 {formatCurrency(task.commission)}
                    </span>
                  )}
                  <span className="text-gray-400 text-xs ml-auto">
                    {formatDate(task.createdAt)}
                  </span>
                </div>

                {task.deadline && (
                  <div className="mt-2 text-xs text-orange-600">
                    ⏰ Deadline: {formatDate(task.deadline)}
                  </div>
                )}
              </div>
            </Link>
          ))}
        </div>
      )}

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex justify-center gap-2 mt-4">
          <button
            disabled={page === 1}
            onClick={() => handlePageChange(page - 1)}
            className="px-3 py-1.5 rounded-lg border border-gray-200 text-sm disabled:opacity-40 hover:border-[#667eea] transition-colors"
          >
            ← Prev
          </button>
          <span className="px-3 py-1.5 text-sm text-gray-600">
            {page} / {totalPages}
          </span>
          <button
            disabled={page === totalPages}
            onClick={() => handlePageChange(page + 1)}
            className="px-3 py-1.5 rounded-lg border border-gray-200 text-sm disabled:opacity-40 hover:border-[#667eea] transition-colors"
          >
            Next →
          </button>
        </div>
      )}
    </div>
  );
}
