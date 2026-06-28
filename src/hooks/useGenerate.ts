import { useState, useCallback } from 'react';
import { generateArticle, improveArticle } from '../api/openai';
import type { GenerateParams, GeneratedArticle } from '../types';

export function useGenerate() {
  const [article, setArticle] = useState<GeneratedArticle | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const generate = useCallback(async (params: GenerateParams) => {
    setLoading(true);
    setError(null);
    try {
      const result = await generateArticle(params);
      setArticle(result);
      return result;
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Generation failed.';
      setError(msg);
      throw err;
    } finally {
      setLoading(false);
    }
  }, []);

  const improve = useCallback(
    async (content: string, instruction: 'improve' | 'expand' | 'shorten') => {
      setLoading(true);
      setError(null);
      try {
        const newContent = await improveArticle(content, instruction);
        setArticle((prev) =>
          prev ? { ...prev, content: newContent } : prev
        );
        return newContent;
      } catch (err) {
        const msg = err instanceof Error ? err.message : 'Improvement failed.';
        setError(msg);
        throw err;
      } finally {
        setLoading(false);
      }
    },
    []
  );

  const reset = useCallback(() => {
    setArticle(null);
    setError(null);
  }, []);

  return { article, loading, error, generate, improve, reset, setArticle };
}
