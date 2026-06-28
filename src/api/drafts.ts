import type { ApiResponse, DashboardStats, DraftData, FullSettings } from '../types';

const getBase = () => window.tzawData.apiUrl;
const getNonce = () => window.tzawData.nonce;

/**
 * Save an article as a WordPress draft.
 */
export async function saveDraft(
  data: DraftData
): Promise<{ post_id: number; edit_url: string; message: string }> {
  const res = await fetch(`${getBase()}/save-draft`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': getNonce(),
    },
    body: JSON.stringify(data),
  });

  const json = await res.json();

  if (!res.ok || !json.success) {
    throw new Error(json.message ?? 'Failed to save draft.');
  }

  return {
    post_id: json.post_id,
    edit_url: json.edit_url,
    message: json.message,
  };
}

/**
 * Get dashboard stats (total posts, recent drafts).
 */
export async function fetchStats(): Promise<DashboardStats> {
  const res = await fetch(`${getBase()}/stats`, {
    headers: { 'X-WP-Nonce': getNonce() },
  });

  const json: ApiResponse<DashboardStats> = await res.json();

  if (!res.ok || !json.success) {
    const err = json as unknown as { message?: string };
    throw new Error(err.message ?? 'Failed to fetch stats.');
  }

  return json.data;
}

/**
 * Get current settings (with masked API key).
 */
export async function fetchSettings(): Promise<FullSettings> {
  const res = await fetch(`${getBase()}/settings`, {
    headers: { 'X-WP-Nonce': getNonce() },
  });

  const json: ApiResponse<FullSettings> = await res.json();

  if (!res.ok || !json.success) {
    const err = json as unknown as { message?: string };
    throw new Error(err.message ?? 'Failed to fetch settings.');
  }

  return json.data;
}

/**
 * Save settings to the server.
 */
export async function saveSettings(
  settings: Partial<FullSettings>
): Promise<string> {
  const res = await fetch(`${getBase()}/settings`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': getNonce(),
    },
    body: JSON.stringify(settings),
  });

  const json = await res.json();

  if (!res.ok || !json.success) {
    throw new Error(json.message ?? 'Failed to save settings.');
  }

  return json.message;
}
