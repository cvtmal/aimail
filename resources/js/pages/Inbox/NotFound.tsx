import React from 'react';
import { Head } from '@inertiajs/react';
import { PageProps } from '@/types';
import AppLayout from '@/layouts/app-layout';

export default function NotFound({}: PageProps) {
  return (
    <>
      <Head title="Email Not Found" />
      
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900 dark:text-gray-100">
              <div className="text-center py-8">
                <h1 className="text-2xl font-bold mb-4">Email Not Found</h1>
                <p className="mb-6">The email you are looking for could not be found.</p>
                
                <a 
                  href="/inbox" 
                  className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                  Return to Inbox
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}

NotFound.layout = (page: React.ReactNode) => <AppLayout children={page} />;
