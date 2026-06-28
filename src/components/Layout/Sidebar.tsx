import React from 'react';
import type { AppRoute } from '../../types';

interface SidebarProps {
  currentRoute: AppRoute;
  onNavigate: (route: AppRoute) => void;
}

const navItems: { route: AppRoute; label: string; icon: string }[] = [
  { route: 'dashboard', label: 'Dashboard', icon: '⊞' },
  { route: 'generate', label: 'Generate Blog', icon: '✦' },
  { route: 'settings', label: 'Settings', icon: '⚙' },
];

export default function Sidebar({ currentRoute, onNavigate }: SidebarProps) {
  return (
    <aside className="tzaw-sidebar">
      {/* Logo */}
      <div className="px-5 py-5 border-b border-surface-border">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-brand flex items-center justify-center text-white font-bold text-sm">
            TZ
          </div>
          <div>
            <div className="font-semibold text-sm text-text-primary leading-tight">
              TechZapp
            </div>
            <div className="text-xs text-text-secondary leading-tight">
              AI Writer
            </div>
          </div>
        </div>
      </div>

      {/* Navigation */}
      <nav className="p-3 flex-1">
        {navItems.map(({ route, label, icon }) => {
          const isActive = currentRoute === route;
          return (
            <button
              key={route}
              id={`tzaw-nav-${route}`}
              onClick={() => onNavigate(route)}
              className={`
                w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium
                transition-colors duration-150 text-left mb-0.5
                ${
                  isActive
                    ? 'bg-brand-light text-brand'
                    : 'text-text-secondary hover:bg-surface-hover hover:text-text-primary'
                }
              `}
              aria-current={isActive ? 'page' : undefined}
            >
              <span className="w-4 text-center text-base leading-none">{icon}</span>
              {label}
            </button>
          );
        })}
      </nav>

      {/* Version */}
      <div className="px-4 py-4 border-t border-surface-border">
        <p className="text-xs text-text-muted">TechZapp AI Writer v1.0.0</p>
      </div>
    </aside>
  );
}
