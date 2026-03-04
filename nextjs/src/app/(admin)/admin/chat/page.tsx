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

interface Conversation {
  userId: number;
  user: { id: number; name: string; email: string } | null;
  lastMessage: ChatMessage | null;
  unreadCount: number;
}

export default function AdminChatPage() {
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [selectedUserId, setSelectedUserId] = useState<number | null>(null);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [newMessage, setNewMessage] = useState("");
  const [sending, setSending] = useState(false);
  const [isLoadingConvos, setIsLoadingConvos] = useState(true);
  const [isLoadingMessages, setIsLoadingMessages] = useState(false);
  const bottomRef = useRef<HTMLDivElement>(null);

  const fetchConversations = useCallback(async () => {
    const res = await fetch("/api/admin/chat");
    const data = await res.json();
    if (data.success) setConversations(data.data);
    setIsLoadingConvos(false);
  }, []);

  const fetchMessages = useCallback(async (userId: number) => {
    setIsLoadingMessages(true);
    const res = await fetch(`/api/admin/chat/${userId}`);
    const data = await res.json();
    if (data.success) setMessages(data.data);
    setIsLoadingMessages(false);
  }, []);

  useEffect(() => {
    fetchConversations();
    const interval = setInterval(fetchConversations, 5000);
    return () => clearInterval(interval);
  }, [fetchConversations]);

  useEffect(() => {
    if (selectedUserId) fetchMessages(selectedUserId);
  }, [selectedUserId, fetchMessages]);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  const selectUser = (userId: number) => {
    setSelectedUserId(userId);
    // Mark as read in conversations list
    setConversations((prev) =>
      prev.map((c) => (c.userId === userId ? { ...c, unreadCount: 0 } : c))
    );
  };

  const sendMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newMessage.trim() || !selectedUserId) return;
    setSending(true);
    const res = await fetch(`/api/admin/chat/${selectedUserId}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ message: newMessage }),
    });
    const data = await res.json();
    if (data.success) {
      setMessages((prev) => [...prev, data.data]);
      setNewMessage("");
      await fetchConversations();
    }
    setSending(false);
  };

  const selectedUser = conversations.find((c) => c.userId === selectedUserId)?.user;

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">💬 Support Chat</h1>
        <p className="text-white/80 mt-1">Manage user conversations</p>
      </div>

      <div className="bg-white rounded-2xl shadow-sm border border-gray-100 flex h-[70vh]">
        {/* Sidebar: Conversations */}
        <div className="w-72 border-r border-gray-100 flex flex-col">
          <div className="p-4 border-b border-gray-50">
            <h2 className="font-semibold text-gray-900">Conversations</h2>
          </div>
          <div className="flex-1 overflow-y-auto">
            {isLoadingConvos ? (
              <div className="flex justify-center py-8">
                <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-[#667eea]" />
              </div>
            ) : !conversations.length ? (
              <p className="text-sm text-gray-500 p-4">No conversations yet.</p>
            ) : (
              conversations.map((c) => (
                <button
                  key={c.userId}
                  onClick={() => selectUser(c.userId)}
                  className={`w-full text-left px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition-colors ${
                    selectedUserId === c.userId ? "bg-[#667eea]/5" : ""
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <p className="text-sm font-medium text-gray-900 truncate">
                      {c.user?.name ?? `User #${c.userId}`}
                    </p>
                    {c.unreadCount > 0 && (
                      <span className="bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5 min-w-[18px] text-center">
                        {c.unreadCount}
                      </span>
                    )}
                  </div>
                  {c.lastMessage && (
                    <p className="text-xs text-gray-400 truncate mt-0.5">
                      {c.lastMessage.sender === "admin" ? "You: " : ""}
                      {c.lastMessage.message}
                    </p>
                  )}
                </button>
              ))
            )}
          </div>
        </div>

        {/* Chat Area */}
        <div className="flex-1 flex flex-col">
          {!selectedUserId ? (
            <div className="flex-1 flex items-center justify-center text-gray-400">
              <div className="text-center">
                <p className="text-4xl mb-2">💬</p>
                <p className="text-sm">Select a conversation to start chatting</p>
              </div>
            </div>
          ) : (
            <>
              {/* Chat Header */}
              <div className="px-6 py-4 border-b border-gray-100">
                <p className="font-semibold text-gray-900">
                  {selectedUser?.name ?? `User #${selectedUserId}`}
                </p>
                <p className="text-xs text-gray-400">{selectedUser?.email}</p>
              </div>

              {/* Messages */}
              <div className="flex-1 overflow-y-auto p-4 space-y-3">
                {isLoadingMessages ? (
                  <div className="flex justify-center py-8">
                    <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-[#667eea]" />
                  </div>
                ) : !messages.length ? (
                  <div className="text-center py-8 text-gray-400 text-sm">
                    No messages yet.
                  </div>
                ) : (
                  messages.map((msg) => (
                    <div
                      key={msg.id}
                      className={`flex ${msg.sender === "admin" ? "justify-end" : "justify-start"}`}
                    >
                      <div
                        className={`max-w-[75%] px-4 py-2 rounded-2xl text-sm ${
                          msg.sender === "admin"
                            ? "bg-gradient-to-r from-[#667eea] to-[#764ba2] text-white rounded-br-sm"
                            : "bg-gray-100 text-gray-800 rounded-bl-sm"
                        }`}
                      >
                        <p>{msg.message}</p>
                        <p
                          className={`text-xs mt-1 ${
                            msg.sender === "admin" ? "text-white/60" : "text-gray-400"
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
                    placeholder="Type a reply..."
                    className="flex-1 px-4 py-2 text-sm border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#667eea]/30"
                  />
                  <Button type="submit" variant="primary" size="sm" isLoading={sending}>
                    Send
                  </Button>
                </form>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
