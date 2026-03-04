import { formatDateTime } from "@/lib/utils";
import { cn } from "@/lib/utils";

interface PointEntry {
  id: number;
  points: number;
  type: string;
  description: string | null;
  createdAt: string;
}

interface PointsHistoryProps {
  history: PointEntry[];
}

const TYPE_LABELS: Record<string, string> = {
  task_completion: "Task Completed",
  social_task: "Social Task",
  referral: "Referral Bonus",
  daily_login: "Daily Login",
  spin_wheel: "Spin Wheel",
  admin_bonus: "Admin Bonus",
};

export default function PointsHistory({ history }: PointsHistoryProps) {
  if (history.length === 0) {
    return (
      <div className="bg-white rounded-xl border border-gray-100 p-8 text-center text-gray-500">
        <p className="text-4xl mb-2">🎮</p>
        <p>No points earned yet. Complete tasks to start earning!</p>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-xl border border-gray-100 overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-gray-50 border-b border-gray-100">
              <th className="px-4 py-3 text-left font-semibold text-gray-600">
                Action
              </th>
              <th className="px-4 py-3 text-left font-semibold text-gray-600">
                Description
              </th>
              <th className="px-4 py-3 text-right font-semibold text-gray-600">
                Points
              </th>
              <th className="px-4 py-3 text-right font-semibold text-gray-600">
                Date
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-50">
            {history.map((entry) => (
              <tr key={entry.id} className="hover:bg-gray-50 transition-colors">
                <td className="px-4 py-3 text-gray-700">
                  {TYPE_LABELS[entry.type] ?? entry.type}
                </td>
                <td className="px-4 py-3 text-gray-500">
                  {entry.description ?? "—"}
                </td>
                <td
                  className={cn(
                    "px-4 py-3 text-right font-semibold",
                    entry.points >= 0 ? "text-green-600" : "text-red-600"
                  )}
                >
                  {entry.points >= 0 ? "+" : ""}
                  {entry.points}
                </td>
                <td className="px-4 py-3 text-right text-gray-400 whitespace-nowrap">
                  {formatDateTime(entry.createdAt)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
