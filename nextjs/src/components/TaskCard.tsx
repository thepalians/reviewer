import { formatCurrency, formatDate, statusColor } from "@/lib/utils";
import Badge from "@/components/ui/Badge";
import type { Task } from "@/types";

interface TaskCardProps {
  task: Task;
  onClick?: () => void;
}

export default function TaskCard({ task, onClick }: TaskCardProps) {
  return (
    <div
      onClick={onClick}
      className="bg-white rounded-xl border border-gray-100 shadow-sm p-4 hover:shadow-md transition-shadow duration-200 cursor-pointer"
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex-1 min-w-0">
          <p className="font-medium text-gray-900 truncate">
            {task.productName || `Task #${task.id}`}
          </p>
          {task.platform && (
            <p className="text-sm text-gray-500 mt-0.5">📱 {task.platform}</p>
          )}
          {task.orderId && (
            <p className="text-xs text-gray-400 mt-0.5">Order: {task.orderId}</p>
          )}
        </div>
        <Badge label={task.status} status={task.status} />
      </div>

      <div className="mt-3 flex items-center justify-between text-sm">
        {task.commission != null && (
          <span className="font-semibold text-green-600">
            💰 {formatCurrency(task.commission)}
          </span>
        )}
        <span className="text-gray-400 text-xs ml-auto">
          {formatDate(task.createdAt)}
        </span>
      </div>

      {task.deadline && (
        <div className="mt-2 text-xs text-orange-600">
          ⏰ Deadline: {formatDate(task.deadline)}
        </div>
      )}
    </div>
  );
}
