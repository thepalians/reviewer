"use client";

import { useState, useRef } from "react";
import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";
import type { TaskStep } from "@/types";

interface StepFormProps {
  taskId: number;
  stepNumber: number;
  existingStep?: TaskStep;
  onSuccess: () => void;
}

export default function StepForm({
  taskId,
  stepNumber,
  existingStep,
  onSuccess,
}: StepFormProps) {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  // Step 1
  const [orderId, setOrderId] = useState("");
  const [orderFile, setOrderFile] = useState<File | null>(null);

  // Step 2
  const [deliveryFile, setDeliveryFile] = useState<File | null>(null);

  // Step 3
  const [reviewText, setReviewText] = useState("");
  const [reviewRating, setReviewRating] = useState(5);
  const [reviewFile, setReviewFile] = useState<File | null>(null);
  const [reviewLiveFile, setReviewLiveFile] = useState<File | null>(null);

  // Step 4
  const [feedback, setFeedback] = useState("");
  const [rating, setRating] = useState(5);
  const [qrFile, setQrFile] = useState<File | null>(null);

  const orderFileRef = useRef<HTMLInputElement>(null);
  const deliveryFileRef = useRef<HTMLInputElement>(null);
  const reviewFileRef = useRef<HTMLInputElement>(null);
  const reviewLiveFileRef = useRef<HTMLInputElement>(null);
  const qrFileRef = useRef<HTMLInputElement>(null);

  const isResubmit =
    existingStep?.stepStatus === "rejected" ||
    existingStep?.stepStatus === "submitted";

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    setSuccess("");
    setIsSubmitting(true);

    const fd = new FormData();
    fd.append("stepNumber", String(stepNumber));

    if (stepNumber === 1) {
      if (orderId) fd.append("orderId", orderId);
      if (orderFile) fd.append("orderScreenshot", orderFile);
    } else if (stepNumber === 2) {
      if (deliveryFile) fd.append("deliveryScreenshot", deliveryFile);
    } else if (stepNumber === 3) {
      fd.append("reviewText", reviewText);
      fd.append("reviewRating", String(reviewRating));
      if (reviewFile) fd.append("reviewScreenshot", reviewFile);
      if (reviewLiveFile) fd.append("reviewLiveScreenshot", reviewLiveFile);
    } else if (stepNumber === 4) {
      fd.append("feedback", feedback);
      fd.append("rating", String(rating));
      if (qrFile) fd.append("paymentQrCode", qrFile);
    }

    try {
      const res = await fetch(`/api/user/tasks/${taskId}/submit-step`, {
        method: "POST",
        body: fd,
      });
      const json = await res.json();
      if (json.success) {
        setSuccess(json.message || "Step submitted successfully!");
        onSuccess();
      } else {
        setError(json.error || "Submission failed. Please try again.");
      }
    } catch {
      setError("Network error. Please try again.");
    } finally {
      setIsSubmitting(false);
    }
  }

  if (existingStep?.stepStatus === "approved") {
    return (
      <div className="p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm">
        ✅ This step has been <strong>approved</strong> by the admin.
        {existingStep.completedAt && (
          <span className="block text-xs text-green-500 mt-1">
            Completed on {new Date(existingStep.completedAt).toLocaleDateString("en-IN")}
          </span>
        )}
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {isResubmit && (
        <div className="p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-700">
          ⚠️ {existingStep?.stepStatus === "rejected"
            ? "This step was rejected. Please resubmit with correct information."
            : "This step is pending review. You can update your submission."}
        </div>
      )}

      {stepNumber === 1 && (
        <>
          <Input
            label="Order ID"
            placeholder="Enter your order ID"
            value={orderId}
            onChange={(e) => setOrderId(e.target.value)}
          />
          <FileUploadField
            label="Order Screenshot *"
            accept="image/*"
            ref={orderFileRef}
            onChange={setOrderFile}
            currentFile={orderFile}
            existingPath={existingStep?.orderScreenshot}
          />
        </>
      )}

      {stepNumber === 2 && (
        <FileUploadField
          label="Delivery Screenshot *"
          accept="image/*"
          ref={deliveryFileRef}
          onChange={setDeliveryFile}
          currentFile={deliveryFile}
          existingPath={existingStep?.deliveryScreenshot}
        />
      )}

      {stepNumber === 3 && (
        <>
          <StarRating
            label="Rating"
            value={reviewRating}
            onChange={setReviewRating}
          />
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Review Text *
            </label>
            <textarea
              className="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-[#667eea] focus:border-transparent resize-none"
              rows={4}
              placeholder="Write your review here..."
              value={reviewText}
              onChange={(e) => setReviewText(e.target.value)}
              required
            />
          </div>
          <FileUploadField
            label="Review Screenshot *"
            accept="image/*"
            ref={reviewFileRef}
            onChange={setReviewFile}
            currentFile={reviewFile}
            existingPath={existingStep?.reviewScreenshot}
          />
          <FileUploadField
            label="Review Live Screenshot (optional)"
            accept="image/*"
            ref={reviewLiveFileRef}
            onChange={setReviewLiveFile}
            currentFile={reviewLiveFile}
            existingPath={existingStep?.reviewLiveScreenshot}
          />
        </>
      )}

      {stepNumber === 4 && (
        <>
          <FileUploadField
            label="Payment QR Code *"
            accept="image/*"
            ref={qrFileRef}
            onChange={setQrFile}
            currentFile={qrFile}
            existingPath={existingStep?.paymentQrCode}
          />
          <StarRating
            label="Product Rating"
            value={rating}
            onChange={setRating}
          />
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Product Feedback *
            </label>
            <textarea
              className="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-[#667eea] focus:border-transparent resize-none"
              rows={4}
              placeholder="Share your feedback about the product..."
              value={feedback}
              onChange={(e) => setFeedback(e.target.value)}
              required
            />
          </div>
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
        {isResubmit ? "Resubmit Step" : `Submit Step ${stepNumber}`}
      </Button>
    </form>
  );
}

// ---- Helper sub-components ----

interface FileUploadFieldProps {
  label: string;
  accept?: string;
  onChange: (file: File | null) => void;
  currentFile: File | null;
  existingPath?: string;
  ref?: React.RefObject<HTMLInputElement | null>;
}

function FileUploadField({
  label,
  accept,
  onChange,
  currentFile,
  existingPath,
  ref,
}: FileUploadFieldProps) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      {existingPath && !currentFile && (
        <p className="text-xs text-gray-500 mb-1">
          Current: <span className="font-mono">{existingPath.split("/").pop()}</span>
        </p>
      )}
      <input
        ref={ref}
        type="file"
        accept={accept}
        onChange={(e) => onChange(e.target.files?.[0] ?? null)}
        className="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#667eea]/10 file:text-[#667eea] hover:file:bg-[#667eea]/20 transition-all"
      />
      {currentFile && (
        <p className="text-xs text-gray-500 mt-1">Selected: {currentFile.name}</p>
      )}
    </div>
  );
}

interface StarRatingProps {
  label: string;
  value: number;
  onChange: (v: number) => void;
}

function StarRating({ label, value, onChange }: StarRatingProps) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      <div className="flex gap-1">
        {[1, 2, 3, 4, 5].map((star) => (
          <button
            key={star}
            type="button"
            onClick={() => onChange(star)}
            className={`text-2xl transition-transform hover:scale-110 ${
              star <= value ? "text-yellow-400" : "text-gray-300"
            }`}
          >
            ★
          </button>
        ))}
      </div>
    </div>
  );
}
