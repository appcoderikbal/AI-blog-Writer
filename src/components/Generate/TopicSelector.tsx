import React, { useState } from 'react';
import { useTrending } from '../../hooks/useTrending';
import type { TrendingTopic } from '../../types';

interface TopicSelectorProps {
  onSelect: (topic: TrendingTopic | null) => void;
}

const sourceColors: Record<string, string> = {
  'Hacker News': 'bg-orange-50 text-orange-600 border-orange-100',
  'GitHub Trending': 'bg-gray-100 text-gray-600 border-gray-200',
  'Reddit Technology': 'bg-red-50 text-red-600 border-red-100',
  'Dev.to': 'bg-violet-50 text-violet-600 border-violet-100',
};

export default function TopicSelector({ onSelect }: TopicSelectorProps) {
  const { topics, loading, error, refresh } = useTrending();
  const [search, setSearch] = useState('');
  const [customTopic, setCustomTopic] = useState('');

  const filtered = search
    ? topics.filter(
        (t) =>
          t.title.toLowerCase().includes(search.toLowerCase()) ||
          t.category.toLowerCase().includes(search.toLowerCase()) ||
          t.source.toLowerCase().includes(search.toLowerCase())
      )
    : topics;

  const handleCustomSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!customTopic.trim()) return;
    // Create a synthetic topic object from custom input
    onSelect({
      id: 'custom-' + Date.now(),
      title: customTopic.trim(),
      category: 'Custom Topic',
      source: 'Custom',
      score: 0,
      url: '',
    });
  };

  return (
    <div className="flex-1 px-8 py-6 max-w-4xl">
      <h1 className="text-xl font-semibold text-text-primary mb-1">Choose a Topic</h1>
      <p className="text-sm text-text-secondary mb-6">
        Select a trending topic or enter your own to get started.
      </p>

      {/* Custom topic input */}
      <div className="mb-6">
        <label className="block text-sm font-medium text-text-primary mb-2">
          Enter a Custom Topic
        </label>
        <form onSubmit={handleCustomSubmit} className="flex gap-2">
          <input
            id="tzaw-custom-topic"
            type="text"
            value={customTopic}
            onChange={(e) => setCustomTopic(e.target.value)}
            placeholder="e.g. Best AI Coding Assistants in 2026"
            className="flex-1 border border-surface-border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand"
          />
          <button
            type="submit"
            disabled={!customTopic.trim()}
            className="px-4 py-2.5 bg-brand hover:bg-brand-hover text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
          >
            Use This Topic
          </button>
        </form>
      </div>

      {/* Divider */}
      <div className="flex items-center gap-3 mb-5">
        <div className="h-px flex-1 bg-surface-border" />
        <span className="text-xs text-text-muted font-medium uppercase tracking-wide">
          Or pick from trending
        </span>
        <div className="h-px flex-1 bg-surface-border" />
      </div>

      {/* Search & Refresh */}
      <div className="flex gap-2 mb-4">
        <div className="relative flex-1">
          <span className="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted text-sm">
            ⌕
          </span>
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Filter topics…"
            className="w-full pl-8 pr-4 py-2 text-sm border border-surface-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand"
          />
        </div>
        <button
          onClick={refresh}
          className="px-3 py-2 border border-surface-border rounded-lg text-sm text-text-secondary hover:bg-surface-hover transition-colors"
          title="Refresh trending topics"
        >
          ↻ Refresh
        </button>
      </div>

      {/* Topics grid */}
      {loading ? (
        <div className="grid grid-cols-1 gap-2">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="skeleton h-16 rounded-xl" />
          ))}
        </div>
      ) : error ? (
        <div className="text-center py-10 text-text-secondary text-sm">
          <p>Failed to load topics. <button onClick={refresh} className="text-brand hover:underline">Try again</button></p>
        </div>
      ) : (
        <div className="space-y-2">
          {filtered.map((topic) => {
            const badge = sourceColors[topic.source] ?? 'bg-gray-100 text-gray-600 border-gray-200';
            return (
              <button
                key={topic.id}
                id={`tzaw-topic-${topic.id}`}
                onClick={() => onSelect(topic)}
                className="w-full text-left bg-white border border-surface-border rounded-xl px-4 py-3 flex items-center gap-4 hover:border-brand/40 hover:shadow-card transition-all duration-150 group"
              >
                <div className="w-10 text-center shrink-0">
                  <span className="text-sm font-semibold text-text-secondary tabular-nums">
                    {Math.min(topic.score, 999)}
                  </span>
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-text-primary line-clamp-1">
                    {topic.title}
                  </p>
                  <div className="flex items-center gap-2 mt-0.5">
                    <span className={`text-xs px-2 py-0.5 rounded-full border font-medium ${badge}`}>
                      {topic.source}
                    </span>
                    <span className="text-xs text-text-muted">{topic.category}</span>
                  </div>
                </div>
                <span className="text-brand opacity-0 group-hover:opacity-100 transition-opacity text-sm font-medium shrink-0">
                  Select →
                </span>
              </button>
            );
          })}

          {filtered.length === 0 && (
            <div className="text-center py-8 text-text-secondary text-sm">
              No topics match your search.
            </div>
          )}
        </div>
      )}
    </div>
  );
}
