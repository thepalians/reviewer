"use client";

import { useCallback, useState } from "react";
import SpinWheel from "@/components/SpinWheel";

interface SpinReward {
  label: string;
  type: string;
  value: number;
}

interface SpinWheelPageClientProps {
  alreadySpunToday: boolean;
}

export default function SpinWheelPageClient({
  alreadySpunToday,
}: SpinWheelPageClientProps) {
  const [hasSpun, setHasSpun] = useState(alreadySpunToday);

  const handleSpin = useCallback(async (): Promise<{
    segmentIndex: number;
    reward: SpinReward;
  }> => {
    const res = await fetch("/api/user/spin-wheel", { method: "POST" });
    const json = await res.json();

    if (!res.ok) {
      throw new Error(json.error ?? "Spin failed");
    }

    setHasSpun(true);
    return json.data as { segmentIndex: number; reward: SpinReward };
  }, []);

  return (
    <SpinWheel
      onSpin={handleSpin}
      canSpin={!hasSpun}
      alreadySpun={hasSpun}
    />
  );
}
