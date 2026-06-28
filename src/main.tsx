import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/global.css';

const container = document.getElementById('tzaw-root');

if (!container) {
  throw new Error('TechZapp AI Writer: #tzaw-root element not found.');
}

const root = createRoot(container);
const initialRoute = container.dataset.route ?? 'dashboard';

root.render(
  <React.StrictMode>
    <App initialRoute={initialRoute as 'dashboard' | 'generate' | 'settings'} />
  </React.StrictMode>
);
