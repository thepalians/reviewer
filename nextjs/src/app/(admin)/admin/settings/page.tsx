"use client";

import { useState, useEffect } from "react";

interface PlatformSettings {
  siteName: string;
  commissionRate: number;
  minWithdrawal: number;
  maintenanceMode: boolean;
  emailNotifications: boolean;
  smsNotifications: boolean;
}

interface Stats {
  totalUsers: number;
  totalSellers: number;
}

export default function AdminSettingsPage() {
  const [settings, setSettings] = useState<PlatformSettings>({
    siteName: "ReviewFlow",
    commissionRate: 10,
    minWithdrawal: 100,
    maintenanceMode: false,
    emailNotifications: true,
    smsNotifications: false,
  });
  const [stats, setStats] = useState<Stats>({ totalUsers: 0, totalSellers: 0 });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);
  const [activeTab, setActiveTab] = useState<"platform" | "notifications" | "maintenance">("platform");

  useEffect(() => {
    fetch("/api/admin/settings")
      .then((r) => r.json())
      .then((data) => {
        if (data.settings) setSettings(data.settings);
        if (data.stats) setStats(data.stats);
      })
      .finally(() => setLoading(false));
  }, []);

  const handleSave = async () => {
    setSaving(true);
    setMessage(null);
    try {
      const res = await fetch("/api/admin/settings", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(settings),
      });
      const data = await res.json();
      if (data.success) {
        setMessage({ type: "success", text: "Settings saved successfully!" });
      } else {
        setMessage({ type: "error", text: data.error ?? "Failed to save settings" });
      }
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="p-6">
        <div className="animate-pulse space-y-4">
          <div className="h-8 bg-gray-200 rounded w-48" />
          <div className="h-64 bg-gray-200 rounded" />
        </div>
      </div>
    );
  }

  const tabs = [
    { id: "platform" as const, label: "Platform", emoji: "⚙️" },
    { id: "notifications" as const, label: "Notifications", emoji: "🔔" },
    { id: "maintenance" as const, label: "Maintenance", emoji: "🔧" },
  ];

  return (
    <div className="p-6 max-w-3xl mx-auto space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">⚙️ Admin Settings</h1>
        <p className="text-sm text-gray-500 mt-0.5">Manage platform configuration</p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 gap-4">
        <div className="bg-gradient-to-br from-[#667eea] to-[#764ba2] rounded-xl p-4 text-white">
          <p className="text-white/70 text-sm">Total Users</p>
          <p className="text-3xl font-bold">{stats.totalUsers.toLocaleString()}</p>
        </div>
        <div className="bg-gradient-to-br from-[#11998e] to-[#38ef7d] rounded-xl p-4 text-white">
          <p className="text-white/70 text-sm">Total Sellers</p>
          <p className="text-3xl font-bold">{stats.totalSellers.toLocaleString()}</p>
        </div>
      </div>

      {/* Settings card */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100">
        {/* Tabs */}
        <div className="flex border-b border-gray-100">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              onClick={() => { setActiveTab(tab.id); setMessage(null); }}
              className={`flex-1 py-3 text-sm font-medium transition-colors ${
                activeTab === tab.id
                  ? "text-[#667eea] border-b-2 border-[#667eea]"
                  : "text-gray-500 hover:text-gray-700"
              }`}
            >
              {tab.emoji} {tab.label}
            </button>
          ))}
        </div>

        <div className="p-6 space-y-4">
          {message && (
            <div
              className={`p-3 rounded-lg text-sm ${
                message.type === "success"
                  ? "bg-green-50 text-green-700 border border-green-200"
                  : "bg-red-50 text-red-700 border border-red-200"
              }`}
            >
              {message.text}
            </div>
          )}

          {activeTab === "platform" && (
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Site Name</label>
                <input
                  type="text"
                  value={settings.siteName}
                  onChange={(e) => setSettings({ ...settings, siteName: e.target.value })}
                  className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#667eea]/30"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Commission Rate (%)
                </label>
                <input
                  type="number"
                  min={0}
                  max={100}
                  value={settings.commissionRate}
                  onChange={(e) => setSettings({ ...settings, commissionRate: Number(e.target.value) })}
                  className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#667eea]/30"
                />
                <p className="text-xs text-gray-400 mt-1">
                  Percentage taken from each task reward
                </p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Minimum Withdrawal (₹)
                </label>
                <input
                  type="number"
                  min={0}
                  value={settings.minWithdrawal}
                  onChange={(e) => setSettings({ ...settings, minWithdrawal: Number(e.target.value) })}
                  className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#667eea]/30"
                />
              </div>
            </div>
          )}

          {activeTab === "notifications" && (
            <div className="space-y-4">
              <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div>
                  <p className="text-sm font-medium text-gray-900">📧 Email Notifications</p>
                  <p className="text-xs text-gray-500 mt-0.5">Send email alerts to users</p>
                </div>
                <button
                  onClick={() => setSettings({ ...settings, emailNotifications: !settings.emailNotifications })}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                    settings.emailNotifications ? "bg-[#667eea]" : "bg-gray-300"
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      settings.emailNotifications ? "translate-x-6" : "translate-x-1"
                    }`}
                  />
                </button>
              </div>
              <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div>
                  <p className="text-sm font-medium text-gray-900">📱 SMS Notifications</p>
                  <p className="text-xs text-gray-500 mt-0.5">Send SMS alerts to users</p>
                </div>
                <button
                  onClick={() => setSettings({ ...settings, smsNotifications: !settings.smsNotifications })}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                    settings.smsNotifications ? "bg-[#667eea]" : "bg-gray-300"
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      settings.smsNotifications ? "translate-x-6" : "translate-x-1"
                    }`}
                  />
                </button>
              </div>
            </div>
          )}

          {activeTab === "maintenance" && (
            <div className="space-y-4">
              <div className={`p-4 rounded-lg border-2 ${settings.maintenanceMode ? "border-red-300 bg-red-50" : "border-gray-200 bg-gray-50"}`}>
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-900">🔧 Maintenance Mode</p>
                    <p className="text-xs text-gray-500 mt-0.5">
                      {settings.maintenanceMode
                        ? "⚠️ Site is currently in maintenance mode — users cannot log in"
                        : "Site is live and accessible to all users"}
                    </p>
                  </div>
                  <button
                    onClick={() => setSettings({ ...settings, maintenanceMode: !settings.maintenanceMode })}
                    className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                      settings.maintenanceMode ? "bg-red-500" : "bg-gray-300"
                    }`}
                  >
                    <span
                      className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                        settings.maintenanceMode ? "translate-x-6" : "translate-x-1"
                      }`}
                    />
                  </button>
                </div>
              </div>
            </div>
          )}

          <div className="flex justify-end pt-2">
            <button
              onClick={handleSave}
              disabled={saving}
              className="px-6 py-2 bg-gradient-to-r from-[#667eea] to-[#764ba2] text-white rounded-lg text-sm font-medium hover:opacity-90 disabled:opacity-50 transition-opacity"
            >
              {saving ? "Saving..." : "Save Settings"}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
