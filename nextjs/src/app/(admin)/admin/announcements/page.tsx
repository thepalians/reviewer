"use client";

import { useState, useEffect, useCallback } from "react";
import Badge from "@/components/ui/Badge";
import Button from "@/components/ui/Button";
import Modal from "@/components/ui/Modal";
import Input from "@/components/ui/Input";
import { formatDate } from "@/lib/utils";

interface Announcement {
  id: number;
  title: string;
  content: string;
  targetAudience: string;
  isActive: boolean;
  startDate: string | null;
  endDate: string | null;
  createdAt: string;
  viewCount: number;
}

interface AnnouncementForm {
  title: string;
  content: string;
  targetAudience: string;
  isActive: boolean;
  startDate: string;
  endDate: string;
}

const emptyForm: AnnouncementForm = {
  title: "",
  content: "",
  targetAudience: "all",
  isActive: true,
  startDate: "",
  endDate: "",
};

export default function AdminAnnouncementsPage() {
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<AnnouncementForm>(emptyForm);
  const [isSaving, setIsSaving] = useState(false);

  const fetchAnnouncements = useCallback(async () => {
    setIsLoading(true);
    const res = await fetch("/api/admin/announcements");
    const data = await res.json();
    if (data.success) setAnnouncements(data.data);
    setIsLoading(false);
  }, []);

  useEffect(() => {
    fetchAnnouncements();
  }, [fetchAnnouncements]);

  const openCreate = () => {
    setForm(emptyForm);
    setEditingId(null);
    setIsModalOpen(true);
  };

  const openEdit = (a: Announcement) => {
    setForm({
      title: a.title,
      content: a.content,
      targetAudience: a.targetAudience,
      isActive: a.isActive,
      startDate: a.startDate ? a.startDate.split("T")[0] : "",
      endDate: a.endDate ? a.endDate.split("T")[0] : "",
    });
    setEditingId(a.id);
    setIsModalOpen(true);
  };

  const handleSave = async () => {
    if (!form.title || !form.content) return;
    setIsSaving(true);
    const url = editingId ? `/api/admin/announcements/${editingId}` : "/api/admin/announcements";
    const method = editingId ? "PUT" : "POST";
    const res = await fetch(url, {
      method,
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        ...form,
        startDate: form.startDate || null,
        endDate: form.endDate || null,
      }),
    });
    if (res.ok) {
      setIsModalOpen(false);
      await fetchAnnouncements();
    }
    setIsSaving(false);
  };

  const toggleActive = async (a: Announcement) => {
    await fetch(`/api/admin/announcements/${a.id}`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ isActive: !a.isActive }),
    });
    await fetchAnnouncements();
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between gap-4">
        <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white flex-1">
          <h1 className="text-2xl font-bold">📢 Announcements</h1>
          <p className="text-white/80 mt-1">Create and manage announcements</p>
        </div>
        <Button variant="primary" onClick={openCreate}>
          + New
        </Button>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#667eea]" />
        </div>
      ) : !announcements.length ? (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
          <p className="text-4xl mb-2">📭</p>
          <p className="text-gray-500">No announcements yet.</p>
        </div>
      ) : (
        <div className="space-y-4">
          {announcements.map((a) => (
            <div
              key={a.id}
              className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6"
            >
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1">
                  <div className="flex items-center gap-2 mb-1">
                    <h3 className="font-semibold text-gray-900">{a.title}</h3>
                    <Badge
                      label={a.isActive ? "Active" : "Inactive"}
                      status={a.isActive ? "active" : "inactive"}
                    />
                    <span className="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                      {a.targetAudience}
                    </span>
                  </div>
                  <p className="text-sm text-gray-600 line-clamp-2">{a.content}</p>
                  <div className="flex items-center gap-4 mt-2 text-xs text-gray-400">
                    <span>Created {formatDate(a.createdAt)}</span>
                    <span>👁 {a.viewCount} views</span>
                    {a.startDate && <span>From {formatDate(a.startDate)}</span>}
                    {a.endDate && <span>Until {formatDate(a.endDate)}</span>}
                  </div>
                </div>
                <div className="flex gap-2 shrink-0">
                  <Button variant="secondary" size="sm" onClick={() => openEdit(a)}>
                    Edit
                  </Button>
                  <Button
                    variant={a.isActive ? "danger" : "primary"}
                    size="sm"
                    onClick={() => toggleActive(a)}
                  >
                    {a.isActive ? "Deactivate" : "Activate"}
                  </Button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Create/Edit Modal */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        title={editingId ? "Edit Announcement" : "Create Announcement"}
      >
        <div className="space-y-4">
          <Input
            label="Title *"
            value={form.title}
            onChange={(e) => setForm((prev) => ({ ...prev, title: e.target.value }))}
            placeholder="Announcement title"
          />
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Content *</label>
            <textarea
              value={form.content}
              onChange={(e) => setForm((prev) => ({ ...prev, content: e.target.value }))}
              rows={4}
              placeholder="Announcement content..."
              className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#667eea]/30"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Target Audience</label>
            <select
              value={form.targetAudience}
              onChange={(e) => setForm((prev) => ({ ...prev, targetAudience: e.target.value }))}
              className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#667eea]/30"
            >
              <option value="all">All</option>
              <option value="users">Users Only</option>
              <option value="sellers">Sellers Only</option>
            </select>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Start Date"
              type="date"
              value={form.startDate}
              onChange={(e) => setForm((prev) => ({ ...prev, startDate: e.target.value }))}
            />
            <Input
              label="End Date"
              type="date"
              value={form.endDate}
              onChange={(e) => setForm((prev) => ({ ...prev, endDate: e.target.value }))}
            />
          </div>
          <div className="flex items-center gap-2">
            <input
              type="checkbox"
              id="isActive"
              checked={form.isActive}
              onChange={(e) => setForm((prev) => ({ ...prev, isActive: e.target.checked }))}
              className="w-4 h-4 text-[#667eea] rounded"
            />
            <label htmlFor="isActive" className="text-sm font-medium text-gray-700">
              Active
            </label>
          </div>
          <div className="flex gap-3 justify-end">
            <Button variant="secondary" onClick={() => setIsModalOpen(false)}>
              Cancel
            </Button>
            <Button variant="primary" isLoading={isSaving} onClick={handleSave}>
              {editingId ? "Update" : "Create"}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
