import { useState, useEffect, useCallback } from 'react';
import { fetchTrendingTopics } from '../api/trending';
import type { TrendingTopic } from '../types';

export function useTrending() {
  const [topics, setTopics] = useState<TrendingTopic[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async (forceRefresh = false) => {
    setLoading(true);
    setError(null);
    try {
      const data = await fetchTrendingTopics(forceRefresh);
      setTopics(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load trending topics.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  return { topics, loading, error, refresh: () => load(true) };
}
