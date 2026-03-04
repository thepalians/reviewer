import { formatCurrency, formatDateTime } from "@/lib/utils";
import type { WalletTransaction } from "@/types";

interface TransactionHistoryProps {
  transactions: WalletTransaction[];
}

export default function TransactionHistory({
  transactions,
}: TransactionHistoryProps) {
  if (transactions.length === 0) {
    return (
      <div className="bg-white rounded-xl border border-gray-100 p-8 text-center text-gray-500">
        <p className="text-3xl mb-2">💳</p>
        <p>No transactions yet.</p>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-xl border border-gray-100 overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-gray-50 border-b border-gray-100">
              <th className="text-left px-4 py-3 font-medium text-gray-600">Type</th>
              <th className="text-left px-4 py-3 font-medium text-gray-600">Description</th>
              <th className="text-right px-4 py-3 font-medium text-gray-600">Amount</th>
              <th className="text-right px-4 py-3 font-medium text-gray-600">Date</th>
            </tr>
          </thead>
          <tbody>
            {transactions.map((tx) => (
              <tr
                key={tx.id}
                className="border-b border-gray-50 hover:bg-gray-50/50 transition-colors"
              >
                <td className="px-4 py-3">
                  <span
                    className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize ${
                      tx.type === "credit"
                        ? "bg-green-100 text-green-700"
                        : "bg-red-100 text-red-700"
                    }`}
                  >
                    {tx.type === "credit" ? "↑" : "↓"} {tx.type}
                  </span>
                </td>
                <td className="px-4 py-3 text-gray-700 max-w-[200px] truncate">
                  {tx.description ?? "—"}
                </td>
                <td
                  className={`px-4 py-3 text-right font-semibold ${
                    tx.type === "credit" ? "text-green-600" : "text-red-600"
                  }`}
                >
                  {tx.type === "credit" ? "+" : "-"}
                  {formatCurrency(tx.amount)}
                </td>
                <td className="px-4 py-3 text-right text-gray-400 whitespace-nowrap">
                  {formatDateTime(tx.createdAt)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
