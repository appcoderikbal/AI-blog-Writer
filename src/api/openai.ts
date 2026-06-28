import type { ApiResponse, GenerateParams, GeneratedArticle } from '../types';

const getBase = () => window.tzawData.apiUrl;
const getNonce = () => window.tzawData.nonce;

/**
 * Generate a full blog article via the REST API.
 */
export async function generateArticle(
  params: GenerateParams
): Promise<GeneratedArticle> {
  const res = await fetch(`${getBase()}/generate`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': getNonce(),
    },
    body: JSON.stringify(params),
  });

  const json: ApiResponse<GeneratedArticle> = await res.json();

  if (!res.ok || !json.success) {
    const err = json as unknown as { message?: string; code?: string };
    throw new Error(err.message ?? 'Failed to generate article.');
  }

  return json.data;
}

/**
 * Improve, expand, or shorten an article.
 */
export async function improveArticle(
  content: string,
  instruction: 'improve' | 'expand' | 'shorten'
): Promise<string> {
  const res = await fetch(`${getBase()}/improve`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': getNonce(),
    },
    body: JSON.stringify({ content, instruction }),
  });

  const json: ApiResponse<{ content: string }> = await res.json();

  if (!res.ok || !json.success) {
    const err = json as unknown as { message?: string };
    throw new Error(err.message ?? `Failed to ${instruction} article.`);
  }

  return json.data.content;
}

/**
 * Test the OpenAI API connection.
 */
export async function testConnection(): Promise<string> {
  const res = await fetch(`${getBase()}/test-connection`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': getNonce(),
    },
  });

  const json: { success: boolean; message?: string } = await res.json();

  if (!res.ok || !json.success) {
    const err = json as unknown as { message?: string };
    throw new Error(err.message ?? 'Connection test failed.');
  }

  return json.message ?? 'Connection successful.';
}
