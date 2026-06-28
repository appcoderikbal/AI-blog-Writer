import React from 'react';
import type { TrendingTopic } from '../../types';

interface TrendingTopicCardProps {
  topic: TrendingTopic;
  onGenerate: () => void;
}

const sourceColors: Record<string, string> = {
  'Hacker News': 'bg-orange-50 text-orange-600',
  'GitHub Trending': 'bg-gray-100 text-gray-600',
  'Reddit Technology': 'bg-red-50 text-red-600',
  'Dev.to': 'bg-violet-50 text-violet-600',
};

export default function TrendingTopicCard({ topic, onGenerate }: TrendingTopicCardProps) {
  const sourceBadge = sourceColors[topic.source] ?? 'bg-gray-100 text-gray-600';

  const handleCopy = () => {
    navigator.clipboard.writeText(topic.title).catch(() => {});
  };

  return (
    <div className="bg-white border border-surface-border rounded-xl px-4 py-3 flex items-center gap-4 hover:border-brand/40 hover:shadow-card transition-all duration-150 group">
      {/* Score */}
      <div className="w-10 text-center shrink-0">
        <span className="text-sm font-semibold text-text-secondary tabular-nums">
          {topic.score > 999 ? '999+' : topic.score}
        </span>
        <p className="text-xs text-text-muted">score</p>
      </div>

      {/* Content */}
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-text-primary line-clamp-1 leading-snug">
          {topic.title}
        </p>
        <div className="flex items-center gap-2 mt-1">
          <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${sourceBadge}`}>
            {topic.source}
          </span>
          <span className="text-xs text-text-muted line-clamp-1">{topic.category}</span>
        </div>
      </div>

      {/* Actions */}
      <div className="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
        <button
          onClick={handleCopy}
          title="Copy topic"
          className="p-1.5 rounded-md hover:bg-surface-hover text-text-secondary hover:text-text-primary transition-colors text-sm"
        >
          ⎘
        </button>
        <button
          id={`tzaw-generate-topic-${topic.id}`}
          onClick={onGenerate}
          className="px-3 py-1.5 bg-brand hover:bg-brand-hover text-white rounded-md text-xs font-medium transition-colors"
        >
          Generate
        </button>
      </div>
    </div>
  );
}
