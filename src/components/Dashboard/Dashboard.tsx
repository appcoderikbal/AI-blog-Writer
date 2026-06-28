import React, { useEffect, useState } from 'react';
import { fetchStats } from '../../api/drafts';
import StatsCard from './StatsCard';
import TrendingTopicCard from './TrendingTopicCard';
import { useTrending } from '../../hooks/useTrending';
import type { AppRoute, DashboardStats, RecentDraft } from '../../types';

interface DashboardProps {
  onNavigate: (route: AppRoute) => void;
}

export default function Dashboard({ onNavigate }: DashboardProps) {
  const { topics, loading: trendingLoading, refresh } = useTrending();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

  useEffect(() => {
    fetchStats()
      .then(setStats)
      .catch(console.error)
      .finally(() => setStatsLoading(false));
  }, []);

  const recentDrafts: RecentDraft[] = stats?.recent_drafts ?? [];
  const topTopics = topics.slice(0, 6);

  return (
    <div className="flex-1 px-8 py-8 animate-fade-in">
      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-semibold text-text-primary">Dashboard</h1>
          <p className="text-sm text-text-secondary mt-1">
            Welcome back — ready to create great content?
          </p>
        </div>
        <button
          id="tzaw-btn-new-blog"
          onClick={() => onNavigate('generate')}
          className="inline-flex items-center gap-2 px-4 py-2.5 bg-brand hover:bg-brand-hover text-white rounded-lg text-sm font-medium transition-colors"
        >
          <span>✦</span>
          Generate New Blog
        </button>
      </div>

      {/* Stats Row */}
      <div className="grid grid-cols-3 gap-4 mb-8">
        <StatsCard
          label="Total Generated"
          value={statsLoading ? '—' : String(stats?.total_generated ?? 0)}
          icon="📝"
          description="AI-written articles"
        />
        <StatsCard
          label="Trending Topics"
          value={trendingLoading ? '—' : String(topics.length)}
          icon="🔥"
          description="Available right now"
        />
        <StatsCard
          label="Recent Drafts"
          value={statsLoading ? '—' : String(recentDrafts.length)}
          icon="📂"
          description="Saved in WordPress"
        />
      </div>

      {/* Main content grid */}
      <div className="grid grid-cols-5 gap-6">
        {/* Trending Topics */}
        <div className="col-span-3">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-base font-semibold text-text-primary">
              Trending Topics
            </h2>
            <button
              onClick={refresh}
              className="text-xs text-brand hover:underline font-medium"
            >
              Refresh
            </button>
          </div>

          {trendingLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="skeleton h-16 rounded-xl" />
              ))}
            </div>
          ) : topTopics.length === 0 ? (
            <div className="bg-white rounded-xl border border-surface-border p-8 text-center text-text-secondary text-sm">
              No trending topics available. Try refreshing.
            </div>
          ) : (
            <div className="space-y-2">
              {topTopics.map((topic) => (
                <TrendingTopicCard
                  key={topic.id}
                  topic={topic}
                  onGenerate={() => {
                    // Store selected topic and navigate
                    sessionStorage.setItem('tzaw_selected_topic', JSON.stringify(topic));
                    onNavigate('generate');
                  }}
                />
              ))}
            </div>
          )}
        </div>

        {/* Recent Drafts */}
        <div className="col-span-2">
          <h2 className="text-base font-semibold text-text-primary mb-4">
            Recent Drafts
          </h2>

          {statsLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="skeleton h-14 rounded-xl" />
              ))}
            </div>
          ) : recentDrafts.length === 0 ? (
            <div className="bg-white rounded-xl border border-surface-border p-6 text-center text-text-secondary text-sm">
              <p className="mb-3">No drafts yet.</p>
              <button
                onClick={() => onNavigate('generate')}
                className="text-brand hover:underline text-sm font-medium"
              >
                Generate your first article →
              </button>
            </div>
          ) : (
            <div className="space-y-2">
              {recentDrafts.map((draft) => (
                <div
                  key={draft.id}
                  className="bg-white border border-surface-border rounded-xl p-3.5 hover:border-brand/30 transition-colors"
                >
                  <div className="flex items-start justify-between gap-2">
                    <p className="text-sm font-medium text-text-primary line-clamp-2 leading-snug">
                      {draft.title}
                    </p>
                    <span
                      className={`text-xs px-2 py-0.5 rounded-full font-medium shrink-0 ${
                        draft.status === 'publish'
                          ? 'bg-green-50 text-green-700'
                          : 'bg-amber-50 text-amber-700'
                      }`}
                    >
                      {draft.status === 'publish' ? 'Published' : 'Draft'}
                    </span>
                  </div>
                  <div className="flex items-center gap-3 mt-2">
                    <span className="text-xs text-text-muted">
                      {new Date(draft.date).toLocaleDateString()}
                    </span>
                    <a
                      href={draft.edit_url}
                      target="_blank"
                      rel="noreferrer"
                      className="text-xs text-brand hover:underline"
                    >
                      Edit in WP →
                    </a>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
