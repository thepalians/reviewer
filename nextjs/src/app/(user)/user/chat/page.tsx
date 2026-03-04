"use client";

import { useState, useEffect, useCallback, useRef } from "react";
import Button from "@/components/ui/Button";

interface ChatMessage {
  id: number;
  userId: number;
  sender: string;
  message: string;
  isRead: boolean;
  createdAt: string;
}

export default function UserChatPage() {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [newMessage, setNewMessage] = useState("");
  const [sending, setSending] = useState(false);
  const bottomRef = useRef<HTMLDivElement>(null);

  const fetchMessages = useCallback(async () => {
    const res = await fetch("/api/user/chat");
    const data = await res.json();
    if (data.success) setMessages(data.data);
    setIsLoading(false);
  }, []);

  useEffect(() => {
    fetchMessages();
    const interval = setInterval(fetchMessages, 5000);
    return () => clearInterval(interval);
  }, [fetchMessages]);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  const sendMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newMessage.trim()) return;
    setSending(true);
    const res = await fetch("/api/user/chat", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ message: newMessage }),
    });
    const data = await res.json();
    if (data.success) {
      setMessages((prev) => [...prev, data.data]);
      setNewMessage("");
    }
    setSending(false);
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">💬 Support Chat</h1>
        <p className="text-white/80 mt-1">Chat with our support team</p>
      </div>

      {/* Chat Window */}
      <div className="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col h-[60vh]">
        {/* Messages */}
        <div className="flex-1 overflow-y-auto p-4 space-y-3">
          {isLoading ? (
            <div className="flex justify-center py-8">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#667eea]" />
            </div>
          ) : !messages.length ? (
            <div className="text-center py-8 text-gray-500">
              <p className="text-4xl mb-2">💬</p>
              <p className="text-sm">No messages yet. Start a conversation!</p>
            </div>
          ) : (
            messages.map((msg) => (
              <div
                key={msg.id}
                className={`flex ${msg.sender === "user" ? "justify-end" : "justify-start"}`}
              >
                <div
                  className={`max-w-[75%] px-4 py-2 rounded-2xl text-sm ${
                    msg.sender === "user"
                      ? "bg-gradient-to-r from-[#667eea] to-[#764ba2] text-white rounded-br-sm"
                      : "bg-gray-100 text-gray-800 rounded-bl-sm"
                  }`}
                >
                  <p>{msg.message}</p>
                  <p
                    className={`text-xs mt-1 ${
                      msg.sender === "user" ? "text-white/60" : "text-gray-400"
                    }`}
                  >
                    {new Date(msg.createdAt).toLocaleTimeString([], {
                      hour: "2-digit",
                      minute: "2-digit",
                    })}
                  </p>
                </div>
              </div>
            ))
          )}
          <div ref={bottomRef} />
        </div>

        {/* Input */}
        <div className="border-t border-gray-100 p-4">
          <form onSubmit={sendMessage} className="flex gap-3">
            <input
              type="text"
              value={newMessage}
              onChange={(e) => setNewMessage(e.target.value)}
              placeholder="Type a message..."
              className="flex-1 px-4 py-2 text-sm border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#667eea]/30"
            />
            <Button type="submit" variant="primary" size="sm" isLoading={sending}>
              Send
            </Button>
          </form>
        </div>
      </div>
    </div>
  );
}
