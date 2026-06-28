import React, { useState } from 'react';
import { LoadingOverlay } from '../UI/Spinner';
import type { GenerateParams, Tone, TrendingTopic, WordCount } from '../../types';

interface BlogFormProps {
  topic: TrendingTopic | null;
  loading: boolean;
  error: string | null;
  onGenerate: (params: GenerateParams) => void;
  onBack: () => void;
}

const tones: { value: Tone; label: string }[] = [
  { value: 'professional', label: 'Professional' },
  { value: 'friendly', label: 'Friendly' },
  { value: 'conversational', label: 'Conversational' },
  { value: 'beginner-friendly', label: 'Beginner Friendly' },
  { value: 'technical', label: 'Technical' },
];

const wordCounts: { value: WordCount; label: string }[] = [
  { value: 1000, label: '1,000 words' },
  { value: 1500, label: '1,500 words' },
  { value: 2000, label: '2,000 words' },
  { value: 3000, label: '3,000 words' },
];

const settings = window.tzawData?.settings;

export default function BlogForm({ topic, loading, error, onGenerate, onBack }: BlogFormProps) {
  const [title, setTitle] = useState(topic?.title ?? '');
  const [keyword, setKeyword] = useState('');
  const [secondaryKeywords, setSecondaryKeywords] = useState('');
  const [audience, setAudience] = useState('general tech enthusiasts');
  const [tone, setTone] = useState<Tone>((settings?.default_tone as Tone) ?? 'professional');
  const [wordCount, setWordCount] = useState<WordCount>(
    (settings?.default_word_count as WordCount) ?? 1500
  );

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim()) return;

    onGenerate({
      title: title.trim(),
      keyword: keyword.trim() || title.trim(),
      secondary_keywords: secondaryKeywords.trim(),
      audience: audience.trim(),
      tone,
      word_count: wordCount,
    });
  };

  if (loading) {
    return <LoadingOverlay message="Generating your article with AI…" />;
  }

  return (
    <div className="flex-1 px-8 py-6 max-w-2xl animate-slide-up">
      <div className="flex items-center gap-3 mb-6">
        <button
          onClick={onBack}
          className="text-text-secondary hover:text-text-primary transition-colors text-sm"
        >
          ← Back
        </button>
        <div>
          <h1 className="text-xl font-semibold text-text-primary">Configure Article</h1>
          <p className="text-sm text-text-secondary">
            {topic ? `Topic: ${topic.source}` : 'Custom topic'}
          </p>
        </div>
      </div>

      {error && (
        <div className="mb-5 p-4 bg-red-50 border border-red-100 rounded-lg text-sm text-red-700">
          <strong>Error:</strong> {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-5">
        {/* Title */}
        <div>
          <label htmlFor="tzaw-blog-title" className="block text-sm font-medium text-text-primary mb-1.5">
            Blog Title <span className="text-red-500">*</span>
          </label>
          <input
            id="tzaw-blog-title"
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            required
            placeholder="e.g. Best AI Coding Assistants in 2026"
            className="w-full border border-surface-border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand"
          />
        </div>

        {/* Primary Keyword */}
        <div>
          <label htmlFor="tzaw-keyword" className="block text-sm font-medium text-text-primary mb-1.5">
            Primary Keyword
          </label>
          <input
            id="tzaw-keyword"
            type="text"
            value={keyword}
            onChange={(e) => setKeyword(e.target.value)}
            placeholder="e.g. AI coding assistants"
            className="w-full border border-surface-border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand"
          />
          <p className="text-xs text-text-muted mt-1">Defaults to title if left blank</p>
        </div>

        {/* Secondary Keywords */}
        <div>
          <label htmlFor="tzaw-secondary" className="block text-sm font-medium text-text-primary mb-1.5">
            Secondary Keywords
            <span className="ml-1 text-text-muted font-normal">(optional)</span>
          </label>
          <input
            id="tzaw-secondary"
            type="text"
            value={secondaryKeywords}
            onChange={(e) => setSecondaryKeywords(e.target.value)}
            placeholder="e.g. GitHub Copilot, Cursor AI, code generation tools"
            className="w-full border border-surface-border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand"
          />
        </div>

        {/* Target Audience */}
        <div>
          <label htmlFor="tzaw-audience" className="block text-sm font-medium text-text-primary mb-1.5">
            Target Audience
          </label>
          <input
            id="tzaw-audience"
            type="text"
            value={audience}
            onChange={(e) => setAudience(e.target.value)}
            placeholder="e.g. developers, beginners, tech enthusiasts"
            className="w-full border border-surface-border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand"
          />
        </div>

        {/* Tone + Word Count row */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label htmlFor="tzaw-tone" className="block text-sm font-medium text-text-primary mb-1.5">
              Tone
            </label>
            <select
              id="tzaw-tone"
              value={tone}
              onChange={(e) => setTone(e.target.value as Tone)}
              className="w-full border border-surface-border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand bg-white"
            >
              {tones.map((t) => (
                <option key={t.value} value={t.value}>
                  {t.label}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label htmlFor="tzaw-word-count" className="block text-sm font-medium text-text-primary mb-1.5">
              Word Count
            </label>
            <select
              id="tzaw-word-count"
              value={wordCount}
              onChange={(e) => setWordCount(Number(e.target.value) as WordCount)}
              className="w-full border border-surface-border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand bg-white"
            >
              {wordCounts.map((w) => (
                <option key={w.value} value={w.value}>
                  {w.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* API Key warning */}
        {settings && (() => {
          const p = settings.ai_provider ?? 'openai';
          const hasKey =
            p === 'openai' ? settings.has_openai_key :
            p === 'groq'   ? settings.has_groq_key :
            settings.has_gemini_key;
          const label = p === 'openai' ? 'OpenAI' : p === 'groq' ? 'Groq' : 'Gemini';
          if (hasKey) return null;
          return (
            <div className="p-3 bg-amber-50 border border-amber-100 rounded-lg text-sm text-amber-700">
              ⚠ No {label} API key configured. Please add one in{' '}
              <a href="?page=tzaw-settings" className="underline font-medium">Settings</a>.
            </div>
          );
        })()}

        {/* Submit */}
        <div className="flex items-center gap-3 pt-2">
          <button
            id="tzaw-btn-generate"
            type="submit"
            disabled={!title.trim() || loading}
            className="px-6 py-2.5 bg-brand hover:bg-brand-hover text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
          >
            ✦ Generate Article
          </button>
          <p className="text-xs text-text-muted">Takes 30–60 seconds</p>
        </div>
      </form>
    </div>
  );
}
