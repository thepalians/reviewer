"use client";

import { useState, useEffect, useCallback } from "react";
import Badge from "@/components/ui/Badge";
import Button from "@/components/ui/Button";
import type { Metadata } from "next";

interface KycDocument {
  id: number;
  documentType: string;
  documentPath: string;
  status: string;
  notes: string | null;
  createdAt: string;
  updatedAt: string;
}

const DOCUMENT_TYPES = [
  { key: "aadhaar", label: "Aadhaar Card", emoji: "🪪" },
  { key: "pan", label: "PAN Card", emoji: "📄" },
  { key: "passbook", label: "Bank Passbook", emoji: "🏦" },
];

export default function UserKycPage() {
  const [documents, setDocuments] = useState<KycDocument[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [uploading, setUploading] = useState<string | null>(null);
  const [inputs, setInputs] = useState<Record<string, string>>({});
  const [message, setMessage] = useState("");

  const fetchDocuments = useCallback(async () => {
    setIsLoading(true);
    const res = await fetch("/api/user/kyc");
    const data = await res.json();
    if (data.success) setDocuments(data.data);
    setIsLoading(false);
  }, []);

  useEffect(() => {
    fetchDocuments();
  }, [fetchDocuments]);

  const getDoc = (type: string) => documents.find((d) => d.documentType === type);

  const handleUpload = async (documentType: string) => {
    const documentPath = inputs[documentType];
    if (!documentPath?.trim()) {
      setMessage("Please provide a document path/URL.");
      return;
    }
    setUploading(documentType);
    setMessage("");
    const res = await fetch("/api/user/kyc", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ documentType, documentPath: documentPath.trim() }),
    });
    const data = await res.json();
    if (data.success) {
      setMessage("Document submitted successfully!");
      setInputs((prev) => ({ ...prev, [documentType]: "" }));
      await fetchDocuments();
    } else {
      setMessage(data.error || "Upload failed.");
    }
    setUploading(null);
  };

  const getStatusColor = (status: string) => status;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">🆔 KYC Verification</h1>
        <p className="text-white/80 mt-1">Upload your identity documents for verification</p>
      </div>

      {message && (
        <div className="p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-700">
          {message}
        </div>
      )}

      {isLoading ? (
        <div className="flex justify-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#667eea]" />
        </div>
      ) : (
        <div className="grid gap-6 md:grid-cols-3">
          {DOCUMENT_TYPES.map(({ key, label, emoji }) => {
            const doc = getDoc(key);
            return (
              <div key={key} className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div className="flex items-center justify-between mb-4">
                  <div>
                    <p className="text-2xl">{emoji}</p>
                    <h3 className="font-semibold text-gray-900 mt-1">{label}</h3>
                  </div>
                  {doc && <Badge label={doc.status} status={getStatusColor(doc.status)} />}
                </div>

                {doc ? (
                  <div className="space-y-2">
                    <p className="text-xs text-gray-500 break-all">
                      <span className="font-medium">Path:</span> {doc.documentPath}
                    </p>
                    {doc.notes && (
                      <p className="text-xs text-red-600">
                        <span className="font-medium">Note:</span> {doc.notes}
                      </p>
                    )}
                    {doc.status === "rejected" && (
                      <div className="mt-3 space-y-2">
                        <input
                          type="text"
                          placeholder="New document path/URL"
                          value={inputs[key] || ""}
                          onChange={(e) =>
                            setInputs((prev) => ({ ...prev, [key]: e.target.value }))
                          }
                          className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#667eea]/30"
                        />
                        <Button
                          variant="primary"
                          size="sm"
                          isLoading={uploading === key}
                          onClick={() => handleUpload(key)}
                          className="w-full"
                        >
                          Re-upload
                        </Button>
                      </div>
                    )}
                    {doc.status === "approved" && (
                      <p className="text-xs text-green-600 font-medium">✅ Verified</p>
                    )}
                    {doc.status === "pending" && (
                      <p className="text-xs text-yellow-600 font-medium">⏳ Under review</p>
                    )}
                  </div>
                ) : (
                  <div className="space-y-2">
                    <p className="text-xs text-gray-500">Not uploaded yet</p>
                    <input
                      type="text"
                      placeholder="Document path/URL"
                      value={inputs[key] || ""}
                      onChange={(e) =>
                        setInputs((prev) => ({ ...prev, [key]: e.target.value }))
                      }
                      className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#667eea]/30"
                    />
                    <Button
                      variant="primary"
                      size="sm"
                      isLoading={uploading === key}
                      onClick={() => handleUpload(key)}
                      className="w-full"
                    >
                      Upload
                    </Button>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
