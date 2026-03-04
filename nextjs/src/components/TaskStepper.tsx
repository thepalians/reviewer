"use client";

import { cn } from "@/lib/utils";
import type { TaskStep } from "@/types";

const STEP_LABELS = [
  { number: 1, label: "Order & Screenshot", icon: "🛒" },
  { number: 2, label: "Delivery Confirmation", icon: "📦" },
  { number: 3, label: "Review Submission", icon: "⭐" },
  { number: 4, label: "Refund & Feedback", icon: "💸" },
];

interface TaskStepperProps {
  steps: TaskStep[];
  currentStep: number;
  onStepClick: (stepNumber: number) => void;
}

export default function TaskStepper({
  steps,
  currentStep,
  onStepClick,
}: TaskStepperProps) {
  function getStepStatus(stepNumber: number) {
    const step = steps.find((s) => s.stepNumber === stepNumber);
    return step?.stepStatus ?? "pending";
  }

  function isUnlocked(stepNumber: number) {
    if (stepNumber === 1) return true;
    const prev = steps.find((s) => s.stepNumber === stepNumber - 1);
    return prev?.stepStatus === "approved";
  }

  return (
    <div className="w-full">
      {/* Desktop stepper */}
      <div className="hidden sm:flex items-center">
        {STEP_LABELS.map((step, idx) => {
          const status = getStepStatus(step.number);
          const unlocked = isUnlocked(step.number);
          const isActive = currentStep === step.number;

          return (
            <div key={step.number} className="flex items-center flex-1">
              <button
                onClick={() => unlocked && onStepClick(step.number)}
                disabled={!unlocked}
                className={cn(
                  "flex flex-col items-center gap-1 flex-1 transition-all duration-200",
                  !unlocked && "opacity-40 cursor-not-allowed",
                  unlocked && !isActive && "cursor-pointer hover:opacity-80"
                )}
              >
                <div
                  className={cn(
                    "w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold border-2 transition-all duration-200",
                    status === "approved"
                      ? "bg-green-500 border-green-500 text-white"
                      : status === "rejected"
                      ? "bg-red-500 border-red-500 text-white"
                      : status === "submitted"
                      ? "bg-yellow-400 border-yellow-400 text-white"
                      : isActive
                      ? "bg-gradient-to-r from-[#667eea] to-[#764ba2] border-transparent text-white"
                      : "bg-white border-gray-300 text-gray-400"
                  )}
                >
                  {status === "approved"
                    ? "✓"
                    : status === "rejected"
                    ? "✗"
                    : step.icon}
                </div>
                <div className="text-center">
                  <p
                    className={cn(
                      "text-xs font-medium",
                      isActive ? "text-[#667eea]" : "text-gray-500"
                    )}
                  >
                    {step.label}
                  </p>
                  <p className="text-[10px] capitalize text-gray-400">{status}</p>
                </div>
              </button>
              {idx < STEP_LABELS.length - 1 && (
                <div
                  className={cn(
                    "h-0.5 flex-1 mx-1 transition-colors duration-200",
                    getStepStatus(step.number) === "approved"
                      ? "bg-green-400"
                      : "bg-gray-200"
                  )}
                />
              )}
            </div>
          );
        })}
      </div>

      {/* Mobile stepper (vertical list) */}
      <div className="sm:hidden space-y-2">
        {STEP_LABELS.map((step) => {
          const status = getStepStatus(step.number);
          const unlocked = isUnlocked(step.number);
          const isActive = currentStep === step.number;

          return (
            <button
              key={step.number}
              onClick={() => unlocked && onStepClick(step.number)}
              disabled={!unlocked}
              className={cn(
                "w-full flex items-center gap-3 p-3 rounded-xl border text-left transition-all duration-200",
                isActive
                  ? "border-[#667eea] bg-[#667eea]/5"
                  : status === "approved"
                  ? "border-green-200 bg-green-50"
                  : status === "rejected"
                  ? "border-red-200 bg-red-50"
                  : "border-gray-200 bg-white",
                !unlocked && "opacity-40 cursor-not-allowed"
              )}
            >
              <div
                className={cn(
                  "w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0",
                  status === "approved"
                    ? "bg-green-500 text-white"
                    : status === "rejected"
                    ? "bg-red-500 text-white"
                    : status === "submitted"
                    ? "bg-yellow-400 text-white"
                    : isActive
                    ? "bg-gradient-to-r from-[#667eea] to-[#764ba2] text-white"
                    : "bg-gray-100 text-gray-400"
                )}
              >
                {status === "approved" ? "✓" : status === "rejected" ? "✗" : step.number}
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-gray-900">{step.label}</p>
                <p className="text-xs capitalize text-gray-500">{status}</p>
              </div>
            </button>
          );
        })}
      </div>
    </div>
  );
}
