import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { PageProps } from '@/types';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/utils';

interface EmailListItem {
  id: string;
  subject: string;
  from: string;
  date: string;
  message_id: string;
}

interface InboxProps extends PageProps {
  emails: EmailListItem[];
}

export default function Index({ emails: rawEmails }: InboxProps) {
  // Sort emails by date in descending order (newest first)
  const emails = [...rawEmails].sort((a, b) => {
    return new Date(b.date).getTime() - new Date(a.date).getTime();
  });
  return (
    <>
      <Head title="Inbox" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900 dark:text-gray-100">
              <h1 className="text-2xl font-bold mb-6">Inbox</h1>

              {emails.length === 0 ? (
                <div className="text-center py-8">
                  <p>No emails found.</p>
                </div>
              ) : (
                <div className="overflow-x-auto">
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
                        <tr
                          key={email.id}
                          className="hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer"
                        >
                          <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            <Link
                              href={`/inbox/${email.id}`}
                              prefetch
                              className="block w-full"
                              preserveScroll
                            >
                              {email.subject}
                            </Link>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                            <Link
                              href={`/inbox/${email.id}`}
                              prefetch
                              className="block w-full"
                              preserveScroll
                            >
                              {email.from}
                            </Link>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                            <Link
                              href={`/inbox/${email.id}`}
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
              )}
            </div>
          </div>
        </div>
      </div>
    </>
  );
}

Index.layout = (page: React.ReactNode) => <AppLayout children={page} />;
