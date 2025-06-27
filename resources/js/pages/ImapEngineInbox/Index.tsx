import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { PageProps } from '@/types';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/utils';

interface Email {
  id: string;
  subject: string;
  from: string;
  date: string;
  message_id: string;
}

interface ImapEngineInboxProps extends PageProps {
  emails: Email[];
  message?: string;
  success?: boolean;
  account: string;
}

export default function Index({ emails: rawEmails, message, success, account }: ImapEngineInboxProps) {
  // Handle the case when emails might be undefined or null
  const emails = rawEmails ? [...rawEmails].sort((a, b) => {
    return new Date(b.date).getTime() - new Date(a.date).getTime();
  }) : [];
  
  const breadcrumbs = [
    { title: 'ImapEngine Inbox', href: '/imapengine-inbox' }
  ];

  return (
    <>
      <Head title={`ImapEngine Inbox (${account})`} />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          {message && (
              <div className={`mb-4 p-4 rounded-md ${success !== false ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`}>
                {message}
              </div>
            )}
            <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900 dark:text-gray-100">
              <h1 className="text-2xl font-bold mb-6">ImapEngine Inbox ({account})</h1>
              
              {emails.length === 0 ? (
                <p className="text-gray-500 dark:text-gray-400">No emails found.</p>
              ) : (
                <>
                  {/* Mobile list view */}
                  <div className="sm:hidden space-y-4">
                    {emails.map((email) => (
                      <Link
                        key={email.id}
                        href={`/imapengine-inbox/${email.id}?account=${account}`}
                        prefetch
                        preserveScroll
                        className="block p-4 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                      >
                        <div className="font-medium text-gray-900 dark:text-white break-words">
                          {email.subject}
                        </div>
                        <div className="text-sm text-gray-500 dark:text-gray-300 mt-1">
                          {email.from}
                        </div>
                        <div className="text-xs text-gray-400 mt-1">
                          {formatDate(email.date)}
                        </div>
                      </Link>
                    ))}
                  </div>

                  {/* Desktop table view */}
                  <div className="hidden sm:block overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-700">
                      <tr>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                          Subject
                        </th>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                          From
                        </th>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                          Date
                        </th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                      {emails.map((email) => (
                        <tr key={email.id} className="hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                          <td className="px-6 py-4 whitespace-normal max-w-xs break-words text-sm font-medium text-gray-900 dark:text-white">
                            <Link
                              href={`/imapengine-inbox/${email.id}?account=${account}`}
                              prefetch
                              className="block w-full"
                              preserveScroll
                            >
                              {email.subject}
                            </Link>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                            <Link
                              href={`/imapengine-inbox/${email.id}?account=${account}`}
                              prefetch
                              className="block w-full"
                              preserveScroll
                            >
                              {email.from}
                            </Link>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                            <Link
                              href={`/imapengine-inbox/${email.id}?account=${account}`}
                              prefetch
                              className="block w-full"
                              preserveScroll
                            >
                              {formatDate(email.date)}
                            </Link>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  </div>
                  </>
              )}
            </div>
          </div>
        </div>
      </div>
    </>
  );
}

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={[
  { title: 'ImapEngine Inbox', href: '/imapengine-inbox' }
]} />;
