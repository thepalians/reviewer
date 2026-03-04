"use client";

import { useState, useEffect, useCallback } from "react";
import Button from "@/components/ui/Button";

interface CampaignTimerProps {
  requiredTime: number;
  onComplete: () => void;
  isSubmitting?: boolean;
}

export default function CampaignTimer({
  requiredTime,
  onComplete,
  isSubmitting = false,
}: CampaignTimerProps) {
  const [secondsLeft, setSecondsLeft] = useState(requiredTime);
  const [started, setStarted] = useState(false);
  const [done, setDone] = useState(false);

  const start = useCallback(() => {
    setStarted(true);
  }, []);

  useEffect(() => {
    if (!started || done) return;
    if (secondsLeft <= 0) {
      setDone(true);
      return;
    }
    const timer = setTimeout(() => setSecondsLeft((s) => s - 1), 1000);
    return () => clearTimeout(timer);
  }, [started, done, secondsLeft]);

  const minutes = Math.floor(secondsLeft / 60);
  const seconds = secondsLeft % 60;
  const progress = ((requiredTime - secondsLeft) / requiredTime) * 100;

  return (
    <div className="space-y-4">
      {/* Progress bar */}
      <div className="w-full bg-gray-200 rounded-full h-3">
        <div
          className="bg-gradient-to-r from-[#667eea] to-[#764ba2] h-3 rounded-full transition-all duration-1000"
          style={{ width: `${progress}%` }}
        />
      </div>

      {/* Timer display */}
      <div className="text-center">
        {!started ? (
          <p className="text-gray-500 text-sm mb-3">
            You must watch/stay for {requiredTime} seconds to earn the reward.
          </p>
        ) : done ? (
          <p className="text-green-600 font-semibold mb-3">✅ Time completed! You can now claim your reward.</p>
        ) : (
          <p className="text-2xl font-mono font-bold text-gray-900 mb-3">
            {String(minutes).padStart(2, "0")}:{String(seconds).padStart(2, "0")}
          </p>
        )}
      </div>

      {/* Action buttons */}
      <div className="flex gap-3">
        {!started && (
          <Button variant="secondary" size="md" className="flex-1" onClick={start}>
            ▶ Start Timer
          </Button>
        )}
        <Button
          variant="primary"
          size="md"
          className="flex-1"
          disabled={!done}
          isLoading={isSubmitting}
          onClick={onComplete}
        >
          🎉 Claim Reward
        </Button>
      </div>
    </div>
  );
}
