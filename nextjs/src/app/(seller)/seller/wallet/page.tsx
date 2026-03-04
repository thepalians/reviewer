"use client";

import { useState, useEffect } from "react";
import { formatCurrency, formatDateTime } from "@/lib/utils";
import Badge from "@/components/ui/Badge";

interface Transaction {
  id: number;
  type: string;
  amount: string;
  description: string | null;
  createdAt: string;
}

interface WalletData {
  balance: number;
  updatedAt: string | null;
  transactions: Transaction[];
}

export default function SellerWalletPage() {
  const [wallet, setWallet] = useState<WalletData | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    fetch("/api/seller/wallet")
      .then((r) => r.json())
      .then((d) => {
        if (d.success) setWallet(d.data);
        setIsLoading(false);
      })
      .catch(() => setIsLoading(false));
  }, []);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-gray-500">Loading...</div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="bg-gradient-to-r from-[#11998e] to-[#38ef7d] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">💰 Wallet</h1>
        <p className="text-white/80 mt-1">Manage your campaign budget</p>
      </div>

      {/* Balance Card */}
      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-center">
        <p className="text-sm text-gray-500 mb-2">Available Balance</p>
        <p className="text-4xl font-bold text-gray-900">
          {formatCurrency(wallet?.balance ?? 0)}
        </p>
        {wallet?.updatedAt && (
          <p className="text-xs text-gray-400 mt-2">
            Updated: {formatDateTime(wallet.updatedAt)}
          </p>
        )}
        <div className="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800 max-w-sm mx-auto">
          💳 To add funds, please contact the platform administrator.
        </div>
      </div>

      {/* Transactions */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-100">
          <h2 className="font-semibold text-gray-900">Transaction History</h2>
        </div>
        {!wallet?.transactions.length ? (
          <div className="p-12 text-center text-gray-500">No transactions yet</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  {["Type", "Amount", "Description", "Date"].map((h) => (
                    <th key={h} className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {wallet.transactions.map((tx) => (
                  <tr key={tx.id} className="hover:bg-gray-50">
                    <td className="px-6 py-3">
                      <Badge label={tx.type} status={tx.type.includes("credit") || tx.type.includes("deposit") ? "active" : "pending"} />
                    </td>
                    <td className="px-6 py-3 text-sm font-medium text-gray-900">
                      {formatCurrency(tx.amount)}
                    </td>
                    <td className="px-6 py-3 text-sm text-gray-600">{tx.description || "—"}</td>
                    <td className="px-6 py-3 text-sm text-gray-600">{formatDateTime(tx.createdAt)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
