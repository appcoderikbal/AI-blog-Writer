import React from 'react';

interface SpinnerProps {
  size?: 'sm' | 'md' | 'lg';
  label?: string;
}

const sizeMap = {
  sm: 'w-4 h-4 border',
  md: 'w-6 h-6 border-2',
  lg: 'w-10 h-10 border-2',
};

export default function Spinner({ size = 'md', label = 'Loading…' }: SpinnerProps) {
  return (
    <span
      className="inline-flex items-center gap-2"
      role="status"
      aria-label={label}
    >
      <span
        className={`${sizeMap[size]} border-gray-200 border-t-brand rounded-full animate-spin block`}
      />
      {label && <span className="text-sm text-text-secondary">{label}</span>}
    </span>
  );
}

/** Full-page loading overlay */
export function LoadingOverlay({ message = 'Generating your article…' }: { message?: string }) {
  return (
    <div className="flex flex-col items-center justify-center min-h-[400px] gap-6">
      <div className="relative">
        <div className="w-16 h-16 border-2 border-gray-100 border-t-brand rounded-full animate-spin" />
        <div
          className="absolute inset-0 w-16 h-16 border-2 border-transparent border-b-brand/30 rounded-full animate-spin"
          style={{ animationDirection: 'reverse', animationDuration: '1.5s' }}
        />
      </div>
      <div className="text-center">
        <p className="font-medium text-text-primary">{message}</p>
        <p className="text-sm text-text-secondary mt-1">
          This may take 30–60 seconds…
        </p>
      </div>
    </div>
  );
}
