import React, { useState } from 'react';
import Sidebar from './components/Layout/Sidebar';
import Dashboard from './components/Dashboard/Dashboard';
import GenerateBlog from './components/Generate/GenerateBlog';
import Settings from './components/Settings/Settings';
import { ToastProvider } from './components/UI/Toast';
import type { AppRoute } from './types';

interface AppProps {
  initialRoute: AppRoute;
}

export default function App({ initialRoute }: AppProps) {
  const [route, setRoute] = useState<AppRoute>(initialRoute);

  const navigate = (newRoute: AppRoute) => {
    setRoute(newRoute);
    // Update the browser URL so WordPress nav links stay in sync
    const adminUrl = window.tzawData?.adminUrl ?? '';
    const pageMap: Record<AppRoute, string> = {
      dashboard: 'techzapp-ai-writer',
      generate: 'tzaw-generate',
      settings: 'tzaw-settings',
    };
    if (adminUrl) {
      window.history.pushState(
        {},
        '',
        `${adminUrl}?page=${pageMap[newRoute]}`
      );
    }
  };

  const renderPage = () => {
    switch (route) {
      case 'dashboard':
        return <Dashboard onNavigate={navigate} />;
      case 'generate':
        return <GenerateBlog />;
      case 'settings':
        return <Settings />;
      default:
        return <Dashboard onNavigate={navigate} />;
    }
  };

  return (
    <ToastProvider>
      <div className="tzaw-layout">
        <Sidebar currentRoute={route} onNavigate={navigate} />
        <main className="tzaw-main">
          {renderPage()}
        </main>
      </div>
    </ToastProvider>
  );
}
