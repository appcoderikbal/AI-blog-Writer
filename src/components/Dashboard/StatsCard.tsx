import React from 'react';

interface StatsCardProps {
  label: string;
  value: string;
  icon: string;
  description: string;
}

export default function StatsCard({ label, value, icon, description }: StatsCardProps) {
  return (
    <div className="bg-white border border-surface-border rounded-xl p-5 shadow-card">
      <div className="flex items-start justify-between">
        <div>
          <p className="text-xs font-medium text-text-secondary uppercase tracking-wide">
            {label}
          </p>
          <p className="text-3xl font-semibold text-text-primary mt-1.5 tabular-nums">
            {value}
          </p>
          <p className="text-xs text-text-muted mt-1">{description}</p>
        </div>
        <span className="text-2xl">{icon}</span>
      </div>
    </div>
  );
}
