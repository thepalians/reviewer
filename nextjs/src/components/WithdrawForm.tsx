"use client";

import { useState } from "react";
import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";

interface WithdrawFormProps {
  balance: number;
  userUpiId?: string;
  userAccountNumber?: string;
  userBankName?: string;
  userIfscCode?: string;
  userAccountName?: string;
  onSuccess: () => void;
}

export default function WithdrawForm({
  balance,
  userUpiId,
  userAccountNumber,
  userBankName,
  userIfscCode,
  userAccountName,
  onSuccess,
}: WithdrawFormProps) {
  const [method, setMethod] = useState<"upi" | "bank">("upi");
  const [amount, setAmount] = useState("");
  const [upiId, setUpiId] = useState(userUpiId ?? "");
  const [accountName, setAccountName] = useState(userAccountName ?? "");
  const [accountNumber, setAccountNumber] = useState(userAccountNumber ?? "");
  const [bankName, setBankName] = useState(userBankName ?? "");
  const [ifscCode, setIfscCode] = useState(userIfscCode ?? "");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    setSuccess("");

    const amountNum = parseFloat(amount);
    if (isNaN(amountNum) || amountNum <= 0) {
      setError("Please enter a valid amount.");
      return;
    }
    if (amountNum > balance) {
      setError("Amount exceeds wallet balance.");
      return;
    }

    setIsSubmitting(true);
    try {
      const res = await fetch("/api/user/wallet/withdraw", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          amount: amountNum,
          paymentMethod: method,
          ...(method === "upi" ? { upiId } : { accountName, accountNumber, bankName, ifscCode }),
        }),
      });
      const json = await res.json();
      if (json.success) {
        setSuccess(json.message ?? "Withdrawal request submitted!");
        setAmount("");
        onSuccess();
      } else {
        setError(json.error ?? "Failed to submit request.");
      }
    } catch {
      setError("Network error. Please try again.");
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {/* Payment method toggle */}
      <div className="flex rounded-lg bg-gray-100 p-1">
        {(["upi", "bank"] as const).map((m) => (
          <button
            key={m}
            type="button"
            onClick={() => setMethod(m)}
            className={`flex-1 py-2 rounded-md text-sm font-medium transition-all duration-200 ${
              method === m
                ? "bg-white shadow text-gray-900"
                : "text-gray-500 hover:text-gray-700"
            }`}
          >
            {m === "upi" ? "📱 UPI" : "🏦 Bank Transfer"}
          </button>
        ))}
      </div>

      <Input
        label="Withdrawal Amount (₹) *"
        type="number"
        placeholder="Enter amount"
        min="1"
        step="0.01"
        value={amount}
        onChange={(e) => setAmount(e.target.value)}
        required
        helperText={`Available balance: ₹${balance.toFixed(2)}`}
      />

      {method === "upi" ? (
        <Input
          label="UPI ID *"
          placeholder="yourname@upi"
          value={upiId}
          onChange={(e) => setUpiId(e.target.value)}
          required
        />
      ) : (
        <>
          <Input
            label="Account Holder Name *"
            placeholder="Full name"
            value={accountName}
            onChange={(e) => setAccountName(e.target.value)}
            required
          />
          <Input
            label="Account Number *"
            placeholder="Bank account number"
            value={accountNumber}
            onChange={(e) => setAccountNumber(e.target.value)}
            required
          />
          <Input
            label="Bank Name *"
            placeholder="e.g. State Bank of India"
            value={bankName}
            onChange={(e) => setBankName(e.target.value)}
            required
          />
          <Input
            label="IFSC Code *"
            placeholder="e.g. SBIN0001234"
            value={ifscCode}
            onChange={(e) => setIfscCode(e.target.value)}
            required
          />
        </>
      )}

      {error && (
        <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-600">
          ❌ {error}
        </div>
      )}
      {success && (
        <div className="p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-600">
          ✅ {success}
        </div>
      )}

      <Button
        type="submit"
        variant="primary"
        size="lg"
        isLoading={isSubmitting}
        className="w-full"
      >
        Request Withdrawal
      </Button>
    </form>
  );
}
