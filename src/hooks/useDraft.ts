import { useState, useCallback } from 'react';
import { saveDraft } from '../api/drafts';
import type { DraftData } from '../types';

export function useDraft() {
  const [saving, setSaving] = useState(false);
  const [savedId, setSavedId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const save = useCallback(async (data: DraftData) => {
    setSaving(true);
    setError(null);
    try {
      const result = await saveDraft(data);
      setSavedId(result.post_id);
      return result;
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to save draft.';
      setError(msg);
      throw err;
    } finally {
      setSaving(false);
    }
  }, []);

  return { saving, savedId, error, save };
}
