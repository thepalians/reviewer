"use client";

import { useRef, useState, useCallback } from "react";
import { cn } from "@/lib/utils";

interface Segment {
  label: string;
  color: string;
}

const SEGMENTS: Segment[] = [
  { label: "₹5", color: "#667eea" },
  { label: "5 Points", color: "#f093fb" },
  { label: "₹10", color: "#11998e" },
  { label: "Better Luck", color: "#cbd5e1" },
  { label: "₹20", color: "#764ba2" },
  { label: "10 Points", color: "#f5576c" },
  { label: "₹50", color: "#fda085" },
  { label: "₹100", color: "#f6d365" },
];

const TOTAL = SEGMENTS.length;
const SLICE_DEG = 360 / TOTAL;

interface SpinWheelProps {
  onSpin: () => Promise<{ segmentIndex: number; reward: { label: string; type: string; value: number } }>;
  canSpin: boolean;
  alreadySpun: boolean;
}

export default function SpinWheel({ onSpin, canSpin, alreadySpun }: SpinWheelProps) {
  const [spinning, setSpinning] = useState(false);
  const [rotation, setRotation] = useState(0);
  const [result, setResult] = useState<{ label: string; type: string; value: number } | null>(null);
  const [showResult, setShowResult] = useState(false);
  const [error, setError] = useState("");
  const totalRotationRef = useRef(0);

  const handleSpin = useCallback(async () => {
    if (spinning || !canSpin) return;
    setError("");
    setResult(null);
    setShowResult(false);
    setSpinning(true);

    try {
      const data = await onSpin();
      const { segmentIndex, reward } = data;

      // The wheel pointer is at the top (270deg in canvas terms).
      // We want segmentIndex to land at top.
      // Each segment occupies SLICE_DEG degrees starting at index 0 = 0deg.
      // To land segmentIndex at top, we need the center of that segment at 270deg (or -90deg).
      const segmentCenter = segmentIndex * SLICE_DEG + SLICE_DEG / 2;
      const targetAngle = (270 - segmentCenter + 360) % 360;

      // Add multiple full spins + target offset
      const spins = 5 * 360;
      const finalRotation = totalRotationRef.current + spins + targetAngle;
      totalRotationRef.current = finalRotation;

      setRotation(finalRotation);
      setResult(reward);

      // Show result after animation (~4s)
      setTimeout(() => {
        setSpinning(false);
        setShowResult(true);
      }, 4200);
    } catch (err: unknown) {
      setSpinning(false);
      const message = err instanceof Error ? err.message : "Failed to spin. Try again.";
      setError(message);
    }
  }, [spinning, canSpin, onSpin]);

  const size = 300;
  const cx = size / 2;
  const cy = size / 2;
  const r = size / 2 - 4;

  const segments = SEGMENTS.map((seg, i) => {
    const startAngle = (i * SLICE_DEG - 90) * (Math.PI / 180);
    const endAngle = ((i + 1) * SLICE_DEG - 90) * (Math.PI / 180);
    const x1 = cx + r * Math.cos(startAngle);
    const y1 = cy + r * Math.sin(startAngle);
    const x2 = cx + r * Math.cos(endAngle);
    const y2 = cy + r * Math.sin(endAngle);
    const textAngle = (startAngle + endAngle) / 2;
    const textR = r * 0.68;
    const tx = cx + textR * Math.cos(textAngle);
    const ty = cy + textR * Math.sin(textAngle);
    const textRotation = ((i * SLICE_DEG + SLICE_DEG / 2 - 90) + 90);

    return { seg, x1, y1, x2, y2, tx, ty, textRotation };
  });

  return (
    <div className="flex flex-col items-center gap-6">
      {/* Wheel */}
      <div className="relative" style={{ width: size, height: size }}>
        {/* Pointer */}
        <div
          className="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1 z-10"
          style={{ filter: "drop-shadow(0 2px 4px rgba(0,0,0,0.3))" }}
        >
          <svg width="24" height="32" viewBox="0 0 24 32">
            <polygon points="12,2 22,30 12,24 2,30" fill="#1e293b" />
          </svg>
        </div>

        <svg
          width={size}
          height={size}
          style={{
            transform: `rotate(${rotation}deg)`,
            transition: spinning
              ? "transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99)"
              : "none",
            borderRadius: "50%",
            boxShadow: "0 8px 32px rgba(102,126,234,0.3)",
          }}
        >
          {segments.map(({ seg, x1, y1, x2, y2, tx, ty, textRotation }, i) => (
            <g key={i}>
              <path
                d={`M ${cx} ${cy} L ${x1} ${y1} A ${r} ${r} 0 0 1 ${x2} ${y2} Z`}
                fill={seg.color}
                stroke="white"
                strokeWidth="2"
              />
              <text
                x={tx}
                y={ty}
                textAnchor="middle"
                dominantBaseline="middle"
                fontSize="11"
                fontWeight="bold"
                fill="white"
                transform={`rotate(${textRotation}, ${tx}, ${ty})`}
                style={{ textShadow: "0 1px 2px rgba(0,0,0,0.5)" }}
              >
                {seg.label}
              </text>
            </g>
          ))}
          {/* Center circle */}
          <circle cx={cx} cy={cy} r={20} fill="white" stroke="#e2e8f0" strokeWidth="3" />
          <text x={cx} y={cy} textAnchor="middle" dominantBaseline="middle" fontSize="14">
            🎰
          </text>
        </svg>
      </div>

      {/* Spin Button */}
      {!showResult && (
        <button
          onClick={handleSpin}
          disabled={spinning || !canSpin || alreadySpun}
          className={cn(
            "px-8 py-3 rounded-xl font-bold text-white text-lg shadow-lg transition-all duration-200",
            spinning || !canSpin || alreadySpun
              ? "bg-gray-300 cursor-not-allowed"
              : "bg-gradient-to-r from-[#667eea] to-[#764ba2] hover:opacity-90 hover:-translate-y-0.5 active:translate-y-0"
          )}
        >
          {spinning ? "Spinning..." : alreadySpun ? "Come back tomorrow!" : "🎰 Spin!"}
        </button>
      )}

      {error && (
        <p className="text-red-500 text-sm text-center max-w-xs">{error}</p>
      )}

      {/* Result Modal */}
      {showResult && result && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-2xl p-8 text-center shadow-2xl max-w-sm w-full animate-bounce-in">
            {/* Confetti emojis */}
            <div className="text-4xl mb-2">
              {result.type === "none" ? "😔" : "🎉"}
            </div>
            <h2 className="text-2xl font-bold text-gray-900 mb-2">
              {result.type === "none" ? "Better Luck Next Time!" : "You Won!"}
            </h2>
            {result.type !== "none" && (
              <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] text-white rounded-xl px-6 py-4 my-4">
                <p className="text-3xl font-bold">{result.label}</p>
                <p className="text-sm opacity-80 mt-1">
                  {result.type === "cash"
                    ? "Added to your wallet"
                    : "Added to your points"}
                </p>
              </div>
            )}
            <button
              onClick={() => setShowResult(false)}
              className="mt-4 w-full py-2.5 bg-gradient-to-r from-[#667eea] to-[#764ba2] text-white rounded-xl font-semibold hover:opacity-90 transition-opacity"
            >
              Awesome! 🎊
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
