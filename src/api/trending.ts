import type { ApiResponse, TrendingTopic } from '../types';

const getBase = () => window.tzawData.apiUrl;
const getNonce = () => window.tzawData.nonce;

/**
 * Fetch trending topics from all sources.
 */
export async function fetchTrendingTopics(
  forceRefresh = false
): Promise<TrendingTopic[]> {
  const url = new URL(`${getBase()}/trending`);
  if (forceRefresh) url.searchParams.set('refresh', 'true');

  const res = await fetch(url.toString(), {
    headers: { 'X-WP-Nonce': getNonce() },
  });

  const json: ApiResponse<TrendingTopic[]> = await res.json();

  if (!res.ok || !json.success) {
    const err = json as unknown as { message?: string };
    throw new Error(err.message ?? 'Failed to fetch trending topics.');
  }

  return json.data;
}
