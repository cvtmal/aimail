import React, { useState, FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/utils';

interface Email {
  id: string;
  subject: string;
  from: string;
  to: string;
  date: string;
  body: string;
  html?: string;
  message_id: string;
}

interface ChatMessage {
  role: 'user' | 'assistant';
  content: string;
}

interface ShowProps extends PageProps {
  email: Email;
  latestReply?: string;
  chatHistory?: ChatMessage[];
  message?: string;
  success?: boolean;
}

export default function Show({ email, latestReply, chatHistory = [], message, success }: ShowProps) {
  const [isGenerating, setIsGenerating] = useState(false);
  const [isSending, setIsSending] = useState(false);
  const [showChatHistory, setShowChatHistory] = useState(false);

  const { data: generateData, setData: setGenerateData, post: generatePost } = useForm({
    instruction: '',
  });

  const { data: replyData, setData: setReplyData, post: replyPost } = useForm({
    reply: latestReply || '',
  });

  const handleGenerateReply = (e: FormEvent) => {
    e.preventDefault();
    setIsGenerating(true);
    
    generatePost(`/inbox/${email.id}/generate-reply`, {
      preserveScroll: true,
      onSuccess: () => {
        setIsGenerating(false);
        setGenerateData('instruction', '');
      },
      onError: () => {
        setIsGenerating(false);
      },
    });
  };

  const handleSendReply = (e: FormEvent) => {
    e.preventDefault();
    setIsSending(true);
    
    replyPost(`/inbox/${email.id}/send-reply`, {
      preserveScroll: true,
      onSuccess: () => {
        setIsSending(false);
      },
      onError: () => {
        setIsSending(false);
      },
    });
  };

  // Update reply form data when latestReply changes
  React.useEffect(() => {
    if (latestReply) {
      setReplyData('reply', latestReply);
    }
  }, [latestReply]);

  return (
    <>
      <Head title={email.subject} />
      
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          {message && (
            <div className={`mb-4 p-4 rounded-md ${success !== false ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`}>
              {message}
            </div>
          )}

          <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div className="p-6 text-gray-900 dark:text-gray-100">
              <div className="flex justify-between items-start mb-6">
                <h1 className="text-2xl font-bold">{email.subject}</h1>
                <Link
                  href="/inbox"
                  prefetch
                  className="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded-md text-sm font-medium hover:bg-gray-300 dark:hover:bg-gray-600"
                >
                  Back to Inbox
                </Link>
              </div>
              
              <div className="mb-6">
                <div className="text-sm text-gray-600 dark:text-gray-400">
                  <p><strong>From:</strong> {email.from}</p>
                  <p><strong>To:</strong> {email.to}</p>
                  <p><strong>Date:</strong> {formatDate(email.date)}</p>
                </div>
              </div>
              
              <div className="border-t border-gray-200 dark:border-gray-700 pt-4 mb-8">
                <div className="prose dark:prose-invert max-w-none">
                  {/* If HTML content is available, render it, otherwise use plain text */}
                  {email.html ? (
                    <div dangerouslySetInnerHTML={{ __html: email.html }} />
                  ) : (
                    <pre className="whitespace-pre-wrap font-sans">{email.body}</pre>
                  )}
                </div>
              </div>
            </div>
          </div>
          
          {/* AI Prompt Form */}
          <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div className="p-6 text-gray-900 dark:text-gray-100">
              <h2 className="text-xl font-bold mb-4">Generate AI Reply</h2>
              
              <form onSubmit={handleGenerateReply}>
                <div className="mb-4">
                  <label htmlFor="instruction" className="block text-sm font-medium mb-1">
                    Instructions for AI
                  </label>
                  <textarea
                    id="instruction"
                    value={generateData.instruction}
                    onChange={(e) => setGenerateData('instruction', e.target.value)}
                    rows={3}
                    placeholder="e.g., 'Reply in a friendly tone' or 'Make it shorter'"
                    className="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-indigo-300 dark:focus:border-indigo-700 focus:ring focus:ring-indigo-200 dark:focus:ring-indigo-800 focus:ring-opacity-50"
                  />
                </div>
                
                <div className="flex items-center justify-between">
                  <button
                    type="submit"
                    disabled={isGenerating}
                    className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {isGenerating ? 'Generating...' : 'Generate AI Reply'}
                  </button>
                  
                  {chatHistory.length > 0 && (
                    <button
                      type="button"
                      onClick={() => setShowChatHistory(!showChatHistory)}
                      className="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                    >
                      {showChatHistory ? 'Hide Chat History' : 'Show Chat History'}
                    </button>
                  )}
                </div>
              </form>
              
              {/* Chat History */}
              {showChatHistory && chatHistory.length > 0 && (
                <div className="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                  <h3 className="text-lg font-medium mb-3">Chat History</h3>
                  <div className="space-y-4">
                    {chatHistory.map((message, index) => (
                      <div 
                        key={index} 
                        className={`p-3 rounded-lg ${
                          message.role === 'user' 
                            ? 'bg-gray-100 dark:bg-gray-700 ml-8' 
                            : 'bg-indigo-50 dark:bg-indigo-900 mr-8'
                        }`}
                      >
                        <div className="text-xs text-gray-500 dark:text-gray-400 mb-1">
                          {message.role === 'user' ? 'You' : 'AI Assistant'}
                        </div>
                        <div className="whitespace-pre-wrap">{message.content}</div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>
          
          {/* Reply Form */}
          {latestReply && (
            <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
              <div className="p-6 text-gray-900 dark:text-gray-100">
                <h2 className="text-xl font-bold mb-4">Edit & Send Reply</h2>
                
                <form onSubmit={handleSendReply}>
                  <div className="mb-4">
                    <textarea
                      id="reply"
                      value={replyData.reply}
                      onChange={(e) => setReplyData('reply', e.target.value)}
                      rows={10}
                      className="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-indigo-300 dark:focus:border-indigo-700 focus:ring focus:ring-indigo-200 dark:focus:ring-indigo-800 focus:ring-opacity-50"
                      required
                    />
                  </div>
                  
                  <div>
                    <button
                      type="submit"
                      disabled={isSending}
                      className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {isSending ? 'Sending...' : 'Send Reply'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}
        </div>
      </div>
    </>
  );
}

Show.layout = (page: React.ReactNode) => <AppLayout children={page} />;
