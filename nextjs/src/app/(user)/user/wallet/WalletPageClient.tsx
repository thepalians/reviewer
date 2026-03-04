"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import WithdrawForm from "@/components/WithdrawForm";

interface WalletPageClientProps {
  balance: number;
  userUpiId?: string;
  userAccountName?: string;
  userAccountNumber?: string;
  userBankName?: string;
  userIfscCode?: string;
}

export default function WalletPageClient(props: WalletPageClientProps) {
  const [showForm, setShowForm] = useState(false);
  const router = useRouter();

  function handleSuccess() {
    setShowForm(false);
    router.refresh();
  }

  return (
    <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-lg font-semibold text-gray-900">Withdraw Funds</h2>
        <button
          onClick={() => setShowForm((prev) => !prev)}
          className="text-sm text-[#667eea] font-medium hover:underline"
        >
          {showForm ? "Cancel" : "Request Withdrawal →"}
        </button>
      </div>

      {!showForm ? (
        <p className="text-sm text-gray-500">
          Click &quot;Request Withdrawal&quot; to withdraw your earnings via UPI or Bank Transfer.
        </p>
      ) : (
        <WithdrawForm {...props} onSuccess={handleSuccess} />
      )}
    </div>
  );
}
