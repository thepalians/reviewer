import { formatCurrency } from "@/lib/utils";

interface WalletCardProps {
  balance: number;
  totalEarned: number;
  totalWithdrawn: number;
}

export default function WalletCard({
  balance,
  totalEarned,
  totalWithdrawn,
}: WalletCardProps) {
  return (
    <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
      <p className="text-white/70 text-sm font-medium uppercase tracking-wider">
        Wallet Balance
      </p>
      <p className="text-4xl font-bold mt-1">{formatCurrency(balance)}</p>

      <div className="mt-6 grid grid-cols-2 gap-4">
        <div className="bg-white/10 rounded-xl p-3">
          <p className="text-white/70 text-xs uppercase tracking-wider">Total Earned</p>
          <p className="text-lg font-semibold mt-0.5">{formatCurrency(totalEarned)}</p>
        </div>
        <div className="bg-white/10 rounded-xl p-3">
          <p className="text-white/70 text-xs uppercase tracking-wider">Total Withdrawn</p>
          <p className="text-lg font-semibold mt-0.5">{formatCurrency(totalWithdrawn)}</p>
        </div>
      </div>
    </div>
  );
}
