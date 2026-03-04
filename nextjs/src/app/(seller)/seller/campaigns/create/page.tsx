"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import Input from "@/components/ui/Input";
import Button from "@/components/ui/Button";

interface Platform {
  id: number;
  name: string;
}

export default function CreateCampaignPage() {
  const router = useRouter();
  const [platforms, setPlatforms] = useState<Platform[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState("");
  const [form, setForm] = useState({
    title: "",
    description: "",
    platformId: "",
    url: "",
    rewardAmount: "",
    requiredTime: "",
  });

  useEffect(() => {
    fetch("/api/seller/platforms")
      .then((r) => r.json())
      .then((d) => { if (d.success) setPlatforms(d.data); })
      .catch(() => {});
  }, []);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>
  ) => {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setIsLoading(true);

    try {
      const res = await fetch("/api/seller/campaigns", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(form),
      });

      const data = await res.json();
      if (data.success) {
        router.push("/seller/campaigns");
        router.refresh();
      } else {
        setError(data.error || "Failed to create campaign");
      }
    } catch {
      setError("Something went wrong. Please try again.");
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="space-y-6 max-w-2xl">
      <div className="bg-gradient-to-r from-[#11998e] to-[#38ef7d] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">➕ Create Campaign</h1>
        <p className="text-white/80 mt-1">Submit a new social campaign for approval</p>
      </div>

      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <form onSubmit={handleSubmit} className="space-y-5">
          <Input
            label="Campaign Title *"
            name="title"
            placeholder="e.g. Follow us on Instagram"
            value={form.title}
            onChange={handleChange}
            required
          />

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Description
            </label>
            <textarea
              name="description"
              value={form.description}
              onChange={handleChange}
              rows={3}
              placeholder="Describe what users need to do..."
              className="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#11998e]/20 focus:border-[#11998e] transition-colors resize-none"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Platform *
            </label>
            <select
              name="platformId"
              value={form.platformId}
              onChange={handleChange}
              required
              className="w-full h-11 px-4 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#11998e]/20 focus:border-[#11998e] transition-colors"
            >
              <option value="">Select a platform</option>
              {platforms.map((p) => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </select>
          </div>

          <Input
            label="Campaign URL"
            name="url"
            type="url"
            placeholder="https://..."
            value={form.url}
            onChange={handleChange}
          />

          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Reward Amount (₹) *"
              name="rewardAmount"
              type="number"
              min="0"
              step="0.01"
              placeholder="0.00"
              value={form.rewardAmount}
              onChange={handleChange}
              required
            />
            <Input
              label="Required Time (seconds)"
              name="requiredTime"
              type="number"
              min="0"
              placeholder="e.g. 30"
              value={form.requiredTime}
              onChange={handleChange}
            />
          </div>

          {error && (
            <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-600">
              ❌ {error}
            </div>
          )}

          <div className="flex gap-3 pt-2">
            <Button
              type="button"
              variant="secondary"
              size="lg"
              onClick={() => router.back()}
              className="flex-1"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="primary"
              size="lg"
              isLoading={isLoading}
              className="flex-1"
            >
              Submit for Approval
            </Button>
          </div>
        </form>
      </div>

      <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
        <strong>📋 Note:</strong> New campaigns require admin approval before going live.
        You will be able to see your campaign status in the campaigns list.
      </div>
    </div>
  );
}
