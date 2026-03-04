"use client";

import { useState, useCallback } from "react";
import { useRouter } from "next/navigation";
import TaskStepper from "@/components/TaskStepper";
import StepForm from "@/components/StepForm";
import Badge from "@/components/ui/Badge";
import { formatCurrency, formatDate } from "@/lib/utils";
import type { Task, TaskStep } from "@/types";

interface TaskDetailClientProps {
  task: Task;
}

export default function TaskDetailClient({ task: initialTask }: TaskDetailClientProps) {
  const [task, setTask] = useState<Task>(initialTask);
  const [activeStep, setActiveStep] = useState<number>(() => {
    // Default to the first non-approved or last step
    const steps = initialTask.steps ?? [];
    const firstPending = steps.find(
      (s) => s.stepStatus !== "approved"
    );
    return firstPending?.stepNumber ?? 1;
  });
  const router = useRouter();

  const refreshTask = useCallback(async () => {
    const res = await fetch(`/api/user/tasks/${task.id}`);
    const json = await res.json();
    if (json.success) {
      setTask(json.data);
    }
    router.refresh();
  }, [task.id, router]);

  const steps: TaskStep[] = task.steps ?? [];
  const activeStepData = steps.find((s) => s.stepNumber === activeStep);

  const isStepUnlocked = (stepNumber: number) => {
    if (stepNumber === 1) return true;
    const prev = steps.find((s) => s.stepNumber === stepNumber - 1);
    return prev?.stepStatus === "approved";
  };

  return (
    <div className="space-y-6 max-w-2xl mx-auto">
      {/* Task info card */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-5 space-y-4">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-xl font-bold text-gray-900">
              {task.productName ?? `Task #${task.id}`}
            </h2>
            {task.platform && (
              <p className="text-sm text-gray-500 mt-0.5">📱 {task.platform}</p>
            )}
          </div>
          <Badge label={task.status.replace(/_/g, " ")} status={task.status} />
        </div>

        <div className="grid grid-cols-2 gap-3 text-sm">
          {task.commission != null && (
            <div className="bg-green-50 rounded-lg p-3">
              <p className="text-xs text-gray-500 uppercase tracking-wider">Commission</p>
              <p className="font-semibold text-green-600 mt-0.5">
                {formatCurrency(task.commission)}
              </p>
            </div>
          )}
          {task.deadline && (
            <div className="bg-orange-50 rounded-lg p-3">
              <p className="text-xs text-gray-500 uppercase tracking-wider">Deadline</p>
              <p className="font-semibold text-orange-600 mt-0.5">
                {formatDate(task.deadline)}
              </p>
            </div>
          )}
          {task.orderId && (
            <div className="bg-blue-50 rounded-lg p-3">
              <p className="text-xs text-gray-500 uppercase tracking-wider">Order ID</p>
              <p className="font-semibold text-blue-600 mt-0.5 break-all">{task.orderId}</p>
            </div>
          )}
        </div>

        {task.productLink && (
          <a
            href={task.productLink}
            target="_blank"
            rel="noopener noreferrer"
            className="block text-sm text-[#667eea] hover:underline break-all"
          >
            🔗 {task.productLink}
          </a>
        )}

        {task.instructions && (
          <div className="bg-gray-50 rounded-lg p-3">
            <p className="text-xs text-gray-500 uppercase tracking-wider mb-1">Instructions</p>
            <p className="text-sm text-gray-700 whitespace-pre-wrap">{task.instructions}</p>
          </div>
        )}
      </div>

      {/* Stepper */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h3 className="text-base font-semibold text-gray-900 mb-4">Task Progress</h3>
        <TaskStepper
          steps={steps}
          currentStep={activeStep}
          onStepClick={setActiveStep}
        />
      </div>

      {/* Step form */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h3 className="text-base font-semibold text-gray-900 mb-4">
          Step {activeStep}:{" "}
          {
            ["Order & Screenshot", "Delivery Confirmation", "Review Submission", "Refund & Feedback"][
              activeStep - 1
            ]
          }
        </h3>

        {!isStepUnlocked(activeStep) ? (
          <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-xl text-yellow-700 text-sm">
            🔒 Complete and get approval for the previous step first.
          </div>
        ) : (
          <StepForm
            taskId={task.id}
            stepNumber={activeStep}
            existingStep={activeStepData}
            onSuccess={refreshTask}
          />
        )}
      </div>
    </div>
  );
}
