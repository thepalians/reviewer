"use client";

import { useState, useEffect } from "react";
import Input from "@/components/ui/Input";
import Button from "@/components/ui/Button";

interface SellerProfile {
  id: number;
  name: string;
  email: string;
  createdAt: string;
}

export default function SellerSettingsPage() {
  const [profile, setProfile] = useState<SellerProfile | null>(null);
  const [profileForm, setProfileForm] = useState({ name: "", email: "" });
  const [passwordForm, setPasswordForm] = useState({ currentPassword: "", newPassword: "", confirmPassword: "" });
  const [profileLoading, setProfileLoading] = useState(false);
  const [passwordLoading, setPasswordLoading] = useState(false);
  const [profileMsg, setProfileMsg] = useState<{ type: "success" | "error"; text: string } | null>(null);
  const [passwordMsg, setPasswordMsg] = useState<{ type: "success" | "error"; text: string } | null>(null);

  useEffect(() => {
    fetch("/api/seller/settings")
      .then((r) => r.json())
      .then((d) => {
        if (d.success) {
          setProfile(d.data);
          setProfileForm({ name: d.data.name, email: d.data.email });
        }
      })
      .catch(() => {});
  }, []);

  const handleProfileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setProfileForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handlePasswordChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setPasswordForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleProfileSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setProfileMsg(null);
    setProfileLoading(true);
    try {
      const res = await fetch("/api/seller/settings", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(profileForm),
      });
      const data = await res.json();
      if (data.success) {
        setProfile((prev) => prev ? { ...prev, ...data.data } : prev);
        setProfileMsg({ type: "success", text: "Profile updated successfully!" });
      } else {
        setProfileMsg({ type: "error", text: data.error || "Failed to update profile" });
      }
    } catch {
      setProfileMsg({ type: "error", text: "Something went wrong." });
    } finally {
      setProfileLoading(false);
    }
  };

  const handlePasswordSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setPasswordMsg(null);
    if (passwordForm.newPassword !== passwordForm.confirmPassword) {
      setPasswordMsg({ type: "error", text: "New passwords do not match" });
      return;
    }
    setPasswordLoading(true);
    try {
      const res = await fetch("/api/seller/settings/password", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          currentPassword: passwordForm.currentPassword,
          newPassword: passwordForm.newPassword,
        }),
      });
      const data = await res.json();
      if (data.success) {
        setPasswordForm({ currentPassword: "", newPassword: "", confirmPassword: "" });
        setPasswordMsg({ type: "success", text: "Password updated successfully!" });
      } else {
        setPasswordMsg({ type: "error", text: data.error || "Failed to update password" });
      }
    } catch {
      setPasswordMsg({ type: "error", text: "Something went wrong." });
    } finally {
      setPasswordLoading(false);
    }
  };

  return (
    <div className="space-y-6 max-w-2xl">
      <div className="bg-gradient-to-r from-[#11998e] to-[#38ef7d] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">⚙️ Settings</h1>
        <p className="text-white/80 mt-1">Manage your account settings</p>
      </div>

      {/* Account Info */}
      {profile && (
        <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
          <h2 className="font-semibold text-gray-900 mb-1">Account Information</h2>
          <p className="text-sm text-gray-500">Seller ID: #{profile.id}</p>
          <p className="text-sm text-gray-500">
            Member since: {new Date(profile.createdAt).toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" })}
          </p>
        </div>
      )}

      {/* Profile Edit */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h2 className="font-semibold text-gray-900 mb-4">Edit Profile</h2>
        <form onSubmit={handleProfileSubmit} className="space-y-4">
          <Input
            label="Full Name"
            name="name"
            value={profileForm.name}
            onChange={handleProfileChange}
            required
          />
          <Input
            label="Email Address"
            name="email"
            type="email"
            value={profileForm.email}
            onChange={handleProfileChange}
            required
          />
          {profileMsg && (
            <div className={`p-3 rounded-lg text-sm ${profileMsg.type === "success" ? "bg-green-50 border border-green-200 text-green-700" : "bg-red-50 border border-red-200 text-red-600"}`}>
              {profileMsg.type === "success" ? "✅" : "❌"} {profileMsg.text}
            </div>
          )}
          <Button type="submit" variant="primary" size="md" isLoading={profileLoading}>
            Save Profile
          </Button>
        </form>
      </div>

      {/* Password Change */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h2 className="font-semibold text-gray-900 mb-4">Change Password</h2>
        <form onSubmit={handlePasswordSubmit} className="space-y-4">
          <Input
            label="Current Password"
            name="currentPassword"
            type="password"
            value={passwordForm.currentPassword}
            onChange={handlePasswordChange}
            required
            autoComplete="current-password"
          />
          <Input
            label="New Password"
            name="newPassword"
            type="password"
            value={passwordForm.newPassword}
            onChange={handlePasswordChange}
            required
            autoComplete="new-password"
          />
          <Input
            label="Confirm New Password"
            name="confirmPassword"
            type="password"
            value={passwordForm.confirmPassword}
            onChange={handlePasswordChange}
            required
            autoComplete="new-password"
          />
          {passwordMsg && (
            <div className={`p-3 rounded-lg text-sm ${passwordMsg.type === "success" ? "bg-green-50 border border-green-200 text-green-700" : "bg-red-50 border border-red-200 text-red-600"}`}>
              {passwordMsg.type === "success" ? "✅" : "❌"} {passwordMsg.text}
            </div>
          )}
          <Button type="submit" variant="primary" size="md" isLoading={passwordLoading}>
            Update Password
          </Button>
        </form>
      </div>
    </div>
  );
}
