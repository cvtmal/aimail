import React, { useState, FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Loader2, Send } from 'lucide-react';

interface EmailDetails {
  id: string;
  subject: string;
  from: string;
  to: string;
  date: string;
  body: string;
  html: string | null;
  message_id: string;
}

interface ChatMessage {
  role: 'user' | 'assistant';
  content: string;
}

interface EmailShowProps extends PageProps {
  email: EmailDetails;
  latestReply?: string;
  chatHistory?: ChatMessage[];
  message?: string;
  success?: boolean;
  account: string;
}

export default function Show({ email, latestReply, chatHistory = [], message, success, account }: EmailShowProps) {
  // When email is not found
  if (!email) {
    return (
      <AppLayout
        children={
          <>
            <Head title="Email Not Found" />
            <div className="py-12">
              <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                  <div className="p-6 text-gray-900 dark:text-gray-100">
                    <p>The requested email could not be found.</p>
                    <div className="mt-4">
                      <Link
                        href={`/imapengine-inbox?account=${account}`}
                        className="text-blue-600 dark:text-blue-400 hover:underline"
                      >
                        Back to Inbox
                      </Link>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </>
        }
        breadcrumbs={[
          { title: 'ImapEngine Inbox', href: `/imapengine-inbox?account=${account}` },
          { title: 'Email Not Found', href: '#' },
        ]}
      />
    );
  }

  // State & Forms for AI replies (copied from Inbox UI)
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
    generatePost(`/imapengine-inbox/${email.id}/generate-reply?account=${account}`, {
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
    replyPost(`/imapengine-inbox/${email.id}/send-reply?account=${account}`, {
      preserveScroll: true,
      onSuccess: () => {
        setIsSending(false);
      },
      onError: () => {
        setIsSending(false);
      },
    });
  };

  // Keep reply textarea synced with latestReply prop
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
            <div
              className={`mb-4 p-4 rounded-md ${success !== false ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`}
            >
              {message}
            </div>
          )}

          {/* Desktop Email Details (hidden on small screens) */}
          <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div className="p-6 text-gray-900 dark:text-gray-100">
              <div className="flex justify-between items-start mb-6">
                <h1 className="text-2xl font-bold">{email.subject}</h1>
                <Button variant="outline" asChild>
                  <Link href={`/imapengine-inbox?account=${account}`} prefetch>
                    Back to Inbox
                  </Link>
                </Button>
              </div>

              <div className="mb-6 text-sm text-gray-600 dark:text-gray-400">
                <p>
                  <strong>From:</strong> {email.from}
                </p>
                <p>
                  <strong>To:</strong> {email.to}
                </p>
                <p>
                  <strong>Date:</strong> {formatDate(email.date)}
                </p>
              </div>

              <div className="border-t border-gray-200 dark:border-gray-700 pt-4 mb-8 prose dark:prose-invert max-w-none">
                {email.html ? (
                  <div dangerouslySetInnerHTML={{ __html: email.html ?? '' }} />
                ) : (
                  <pre className="whitespace-pre-wrap font-sans">{email.body}</pre>
                )}
              </div>
            </div>
          </div>

          {/* Desktop AI Prompt Form */}
          <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div className="p-6 text-gray-900 dark:text-gray-100">
              <h2 className="text-xl font-bold mb-4">Ask AI for a Reply</h2>
              <form onSubmit={handleGenerateReply} className="space-y-4">
                <textarea
                  id="instruction"
                  value={generateData.instruction}
                  onChange={(e) => setGenerateData('instruction', e.target.value)}
                  rows={4}
                  className="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-indigo-300 dark:focus:border-indigo-700 focus:ring focus:ring-indigo-200 dark:focus:ring-indigo-800 focus:ring-opacity-50"
                  placeholder="e.g. Answer in a friendly tone"
                  required
                />
                <div className="flex items-center justify-between">
                  <Button type="submit" disabled={isGenerating} variant="default">
                    {isGenerating && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                    {isGenerating ? 'Generating...' : 'Generate AI Reply'}
                  </Button>

                  {chatHistory.length > 0 && (
                    <Button
                      type="button"
                      onClick={() => setShowChatHistory(!showChatHistory)}
                      variant="ghost"
                      size="sm"
                    >
                      {showChatHistory ? 'Hide Chat History' : 'Show Chat History'}
                    </Button>
                  )}
                </div>
              </form>

              {/* Chat History */}
              {showChatHistory && chatHistory.length > 0 && (
                <div className="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                  <h3 className="text-lg font-medium mb-3">Chat History</h3>
                  <div className="space-y-4">
                    {chatHistory.map((msg, idx) => (
                      <div
                        key={idx}
                        className={`p-3 rounded-lg ${
                          msg.role === 'user'
                            ? 'bg-gray-100 dark:bg-gray-700 ml-8'
                            : 'bg-indigo-50 dark:bg-indigo-900 mr-8'
                        }`}
                      >
                        <div className="text-xs text-gray-500 dark:text-gray-400 mb-1">
                          {msg.role === 'user' ? 'You' : 'AI Assistant'}
                        </div>
                        <div className="whitespace-pre-wrap">{msg.content}</div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Desktop Reply Form */}
          {latestReply && (
            <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
              <div className="p-6 text-gray-900 dark:text-gray-100">
                <h2 className="text-xl font-bold mb-4">Edit & Send Reply</h2>
                <form onSubmit={handleSendReply} className="space-y-4">
                  <textarea
                    id="reply"
                    value={replyData.reply}
                    onChange={(e) => setReplyData('reply', e.target.value)}
                    rows={10}
                    className="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-indigo-300 dark:focus:border-indigo-700 focus:ring focus:ring-indigo-200 dark:focus:ring-indigo-800 focus:ring-opacity-50"
                    required
                  />
                  <Button type="submit" disabled={isSending} variant="default">
                    {isSending ? (
                      <>
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" /> Sending...
                      </>
                    ) : (
                      <>
                        Send
                        <Send className="ml-2 h-4 w-4" />
                      </>
                    )}
                  </Button>
                </form>
              </div>
            </div>
          )}
        </div>
      </div>
    </>
  );
}

    /* Duplicate legacy code below retained for reference but excluded from compilation
    return (
      <AppLayout children={
        <>
          <Head title="Email Not Found" />

          <div className="py-12">
            <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
              <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 text-gray-900 dark:text-gray-100">
                  <p>The requested email could not be found.</p>
                  <div className="mt-4">
                    <Link
                      href={`/imapengine-inbox?account=${account}`}
                      className="text-blue-600 dark:text-blue-400 hover:underline"
                    >
                      Back to Inbox
                    </Link>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </>
      } breadcrumbs={[
        { title: 'ImapEngine Inbox', href: `/imapengine-inbox?account=${account}` },
        { title: 'Email Not Found', href: '#' },
      ]} />
    );
  }

  return (
    <AppLayout children={
      <>
        <Head title={`${email.subject} - ImapEngine Email`} />

        <div className="py-12">
          <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
              <div className="p-6 text-gray-900 dark:text-gray-100">
                <div className="mb-4">
                  <Link
                    href={`/imapengine-inbox?account=${account}`}
                    className="text-blue-600 dark:text-blue-400 hover:underline"
                  >
                    ← Back to Inbox
                  </Link>
                </div>

                <div>
                  <div className="mb-6 border-b border-gray-200 dark:border-gray-700 pb-4">
                    <h1 className="text-2xl font-bold mb-2">{email.subject}</h1>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400">
                      <div>
                        <strong>From:</strong> {email.from}
                      </div>
                      <div>
                        <strong>To:</strong> {email.to}
                      </div>
                      <div>
                        <strong>Date:</strong> {formatDate(email.date)}
                      </div>
                      <div>
                        <strong>Message ID:</strong> {email.message_id}
                      </div>
                    </div>
                  </div>
                  
                  <div className="mt-4">
                    {email.html ? (
                      <div dangerouslySetInnerHTML={{ __html: email.html ?? '' }} />
                    ) : (
                      <pre className="whitespace-pre-wrap font-sans">{email.body}</pre>
                    )}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </>
    } breadcrumbs={[
      { title: 'ImapEngine Inbox', href: `/imapengine-inbox?account=${account}` },
      { title: email.subject || 'Email Details', href: '#' },
    ]} />
  );
*/

Show.layout = (page: React.ReactNode) => (
  <AppLayout
    children={page}
    breadcrumbs={[
      { title: 'ImapEngine Inbox', href: '/imapengine-inbox' },
      { title: 'View Email', href: '#' },
    ]}
  />
);
