import React, { useEffect, useState } from 'react';
import { fetchSettings, saveSettings } from '../../api/drafts';
import { testConnection } from '../../api/openai';
import { useToast } from '../UI/Toast';
import Spinner from '../UI/Spinner';
import type { AIProvider, FullSettings, ProviderModel, Tone, WordCount } from '../../types';

// ─── Static Data ─────────────────────────────────────────────────────────────

const PROVIDERS: { value: AIProvider; label: string; logo: string; docsUrl: string; keyUrl: string }[] = [
  {
    value: 'openai',
    label: 'OpenAI',
    logo: '🤖',
    docsUrl: 'https://platform.openai.com/docs',
    keyUrl: 'https://platform.openai.com/api-keys',
  },
  {
    value: 'groq',
    label: 'Groq',
    logo: '⚡',
    docsUrl: 'https://console.groq.com/docs',
    keyUrl: 'https://console.groq.com/keys',
  },
  {
    value: 'gemini',
    label: 'Google Gemini',
    logo: '✦',
    docsUrl: 'https://ai.google.dev/gemini-api/docs',
    keyUrl: 'https://aistudio.google.com/app/apikey',
  },
];

const TONES: { value: Tone; label: string }[] = [
  { value: 'professional', label: 'Professional' },
  { value: 'friendly', label: 'Friendly' },
  { value: 'conversational', label: 'Conversational' },
  { value: 'beginner-friendly', label: 'Beginner Friendly' },
  { value: 'technical', label: 'Technical' },
];

const WORD_COUNTS: { value: WordCount; label: string }[] = [
  { value: 1000, label: '1,000 words' },
  { value: 1500, label: '1,500 words' },
  { value: 2000, label: '2,000 words' },
  { value: 3000, label: '3,000 words' },
];

const WP_CATEGORIES = window.tzawData?.categories ?? [];

// Default fallback model lists (server sends the real ones)
const DEFAULT_MODELS: Record<AIProvider, ProviderModel[]> = {
  openai: [
    { value: 'gpt-4o-mini', label: 'GPT-4o Mini (Faster, Cheaper)' },
    { value: 'gpt-4o', label: 'GPT-4o (Best Quality)' },
    { value: 'gpt-4-turbo', label: 'GPT-4 Turbo' },
    { value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo (Fastest)' },
  ],
  groq: [
    { value: 'llama-3.3-70b-versatile', label: 'Llama 3.3 70B Versatile (Recommended)' },
    { value: 'llama-3.1-8b-instant', label: 'Llama 3.1 8B Instant (Fastest)' },
    { value: 'mixtral-8x7b-32768', label: 'Mixtral 8x7B' },
    { value: 'gemma2-9b-it', label: 'Gemma 2 9B' },
  ],
  gemini: [
    { value: 'gemini-2.0-flash', label: 'Gemini 2.0 Flash (Recommended)' },
    { value: 'gemini-2.0-flash-lite', label: 'Gemini 2.0 Flash Lite (Fastest)' },
    { value: 'gemini-1.5-pro', label: 'Gemini 1.5 Pro (Best Quality)' },
    { value: 'gemini-1.5-flash', label: 'Gemini 1.5 Flash' },
  ],
};

// ─── Component ────────────────────────────────────────────────────────────────

export default function Settings() {
  const { showToast } = useToast();

  // Form state
  const [provider, setProvider] = useState<AIProvider>('openai');
  const [openaiKey, setOpenaiKey] = useState('');
  const [groqKey, setGroqKey] = useState('');
  const [geminiKey, setGeminiKey] = useState('');
  const [model, setModel] = useState('gpt-4o-mini');
  const [tone, setTone] = useState<Tone>('professional');
  const [wordCount, setWordCount] = useState<WordCount>(1500);
  const [defaultCategory, setDefaultCategory] = useState(0);
  const [providerModels, setProviderModels] = useState<Record<AIProvider, ProviderModel[]>>(DEFAULT_MODELS);

  // Key visibility toggles
  const [showKeys, setShowKeys] = useState<Record<AIProvider, boolean>>({
    openai: false, groq: false, gemini: false,
  });

  // Status flags
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState(false);

  // ─── Load settings ──────────────────────────────────────────────────────────
  useEffect(() => {
    fetchSettings()
      .then((data: FullSettings) => {
        setProvider(data.ai_provider ?? 'openai');
        setOpenaiKey(data.openai_api_key ?? '');
        setGroqKey(data.groq_api_key ?? '');
        setGeminiKey(data.gemini_api_key ?? '');
        setModel(data.ai_model ?? 'gpt-4o-mini');
        setTone(data.default_tone ?? 'professional');
        setWordCount(data.default_word_count ?? 1500);
        setDefaultCategory(data.default_category ?? 0);
        if (data.provider_models) {
          setProviderModels(data.provider_models);
        }
      })
      .catch(() => showToast('Failed to load settings.', 'error'))
      .finally(() => setLoading(false));
  }, [showToast]);

  // Reset model to provider default when provider changes
  const handleProviderChange = (newProvider: AIProvider) => {
    setProvider(newProvider);
    const models = providerModels[newProvider] ?? DEFAULT_MODELS[newProvider];
    setModel(models[0]?.value ?? '');
  };

  // ─── Save ────────────────────────────────────────────────────────────────────
  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      await saveSettings({
        ai_provider: provider,
        openai_api_key: openaiKey,
        groq_api_key: groqKey,
        gemini_api_key: geminiKey,
        ai_model: model,
        default_tone: tone,
        default_word_count: wordCount,
        default_category: defaultCategory,
      });
      showToast('Settings saved successfully.', 'success');
      // Update in-memory public settings for other components
      if (window.tzawData?.settings) {
        window.tzawData.settings.ai_provider = provider;
        window.tzawData.settings.ai_model = model;
        window.tzawData.settings.default_tone = tone;
        window.tzawData.settings.default_word_count = wordCount;
      }
    } catch (err) {
      showToast(err instanceof Error ? err.message : 'Failed to save.', 'error');
    } finally {
      setSaving(false);
    }
  };

  // ─── Test connection ─────────────────────────────────────────────────────────
  const handleTest = async () => {
    const activeKey = { openai: openaiKey, groq: groqKey, gemini: geminiKey }[provider];
    if (!activeKey.trim()) {
      showToast(`Please enter a ${PROVIDERS.find(p => p.value === provider)?.label} API key first.`, 'error');
      return;
    }
    setTesting(true);
    try {
      // Save current settings first so server uses the entered key
      await saveSettings({
        ai_provider: provider,
        openai_api_key: openaiKey,
        groq_api_key: groqKey,
        gemini_api_key: geminiKey,
        ai_model: model,
        default_tone: tone,
        default_word_count: wordCount,
        default_category: defaultCategory,
      });
      const msg = await testConnection();
      showToast(`${PROVIDERS.find(p => p.value === provider)?.label}: ${msg}`, 'success');
    } catch (err) {
      showToast(err instanceof Error ? err.message : 'Connection failed.', 'error');
    } finally {
      setTesting(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[300px]">
        <Spinner size="lg" label="Loading settings…" />
      </div>
    );
  }

  const activeProviderInfo = PROVIDERS.find(p => p.value === provider)!;
  const activeModels = providerModels[provider] ?? DEFAULT_MODELS[provider];

  return (
    <div className="flex-1 px-8 py-8 max-w-2xl animate-fade-in">
      <h1 className="text-2xl font-semibold text-text-primary mb-1">Settings</h1>
      <p className="text-sm text-text-secondary mb-8">
        Configure your AI provider and generation defaults.
      </p>

      <form onSubmit={handleSave} className="space-y-8">

        {/* ── AI Provider Section ───────────────────────────────────────── */}
        <section>
          <h2 className="text-sm font-semibold text-text-primary uppercase tracking-wide mb-4">
            AI Provider
          </h2>

          {/* Provider tabs */}
          <div className="flex gap-2 mb-5">
            {PROVIDERS.map((p) => (
              <button
                key={p.value}
                type="button"
                id={`tzaw-provider-tab-${p.value}`}
                onClick={() => handleProviderChange(p.value)}
                className={`flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium border transition-all duration-150 ${
                  provider === p.value
                    ? 'bg-brand text-white border-brand shadow-sm'
                    : 'bg-white text-text-secondary border-surface-border hover:border-brand/40 hover:text-text-primary'
                }`}
              >
                <span>{p.logo}</span>
                {p.label}
              </button>
            ))}
          </div>

          {/* Provider config card */}
          <div className="bg-white border border-surface-border rounded-xl p-5 space-y-4">

            {/* Provider description banner */}
            <div className="flex items-center justify-between pb-3 border-b border-surface-border">
              <div className="flex items-center gap-2">
                <span className="text-xl">{activeProviderInfo.logo}</span>
                <div>
                  <p className="text-sm font-semibold text-text-primary">
                    {activeProviderInfo.label}
                  </p>
                  <a
                    href={activeProviderInfo.docsUrl}
                    target="_blank"
                    rel="noreferrer"
                    className="text-xs text-brand hover:underline"
                  >
                    View documentation →
                  </a>
                </div>
              </div>
              <ProviderStatusBadge
                provider={provider}
                openaiKey={openaiKey}
                groqKey={groqKey}
                geminiKey={geminiKey}
              />
            </div>

            {/* API key field for current provider */}
            <ProviderKeyField
              provider={provider}
              providerInfo={activeProviderInfo}
              openaiKey={openaiKey}
              groqKey={groqKey}
              geminiKey={geminiKey}
              showKeys={showKeys}
              onOpenaiChange={setOpenaiKey}
              onGroqChange={setGroqKey}
              onGeminiChange={setGeminiKey}
              onToggleShow={(p) =>
                setShowKeys((prev) => ({ ...prev, [p]: !prev[p] }))
              }
              onTest={handleTest}
              testing={testing}
            />

            {/* Model selection */}
            <div>
              <label
                htmlFor="tzaw-model"
                className="block text-sm font-medium text-text-primary mb-1.5"
              >
                Model
              </label>
              <select
                id="tzaw-model"
                value={model}
                onChange={(e) => setModel(e.target.value)}
                className="w-full border border-surface-border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 bg-white"
              >
                {activeModels.map((m) => (
                  <option key={m.value} value={m.value}>
                    {m.label}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </section>

        {/* ── Generation Defaults ───────────────────────────────────────── */}
        <section>
          <h2 className="text-sm font-semibold text-text-primary uppercase tracking-wide mb-4">
            Generation Defaults
          </h2>
          <div className="bg-white border border-surface-border rounded-xl p-5 space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label
                  htmlFor="tzaw-default-tone"
                  className="block text-sm font-medium text-text-primary mb-1.5"
                >
                  Default Tone
                </label>
                <select
                  id="tzaw-default-tone"
                  value={tone}
                  onChange={(e) => setTone(e.target.value as Tone)}
                  className="w-full border border-surface-border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 bg-white"
                >
                  {TONES.map((t) => (
                    <option key={t.value} value={t.value}>
                      {t.label}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label
                  htmlFor="tzaw-default-words"
                  className="block text-sm font-medium text-text-primary mb-1.5"
                >
                  Default Word Count
                </label>
                <select
                  id="tzaw-default-words"
                  value={wordCount}
                  onChange={(e) => setWordCount(Number(e.target.value) as WordCount)}
                  className="w-full border border-surface-border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 bg-white"
                >
                  {WORD_COUNTS.map((w) => (
                    <option key={w.value} value={w.value}>
                      {w.label}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            {WP_CATEGORIES.length > 0 && (
              <div>
                <label
                  htmlFor="tzaw-default-category"
                  className="block text-sm font-medium text-text-primary mb-1.5"
                >
                  Default Category
                </label>
                <select
                  id="tzaw-default-category"
                  value={defaultCategory}
                  onChange={(e) => setDefaultCategory(Number(e.target.value))}
                  className="w-full border border-surface-border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 bg-white"
                >
                  <option value={0}>No default category</option>
                  {WP_CATEGORIES.map((cat) => (
                    <option key={cat.id} value={cat.id}>
                      {cat.name}
                    </option>
                  ))}
                </select>
              </div>
            )}
          </div>
        </section>

        {/* ── Save button ───────────────────────────────────────────────── */}
        <div className="flex items-center gap-3">
          <button
            id="tzaw-btn-save-settings"
            type="submit"
            disabled={saving}
            className="flex items-center gap-2 px-6 py-2.5 bg-brand hover:bg-brand-hover text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-40"
          >
            {saving && <Spinner size="sm" label="" />}
            {saving ? 'Saving…' : 'Save Settings'}
          </button>
        </div>
      </form>
    </div>
  );
}

// ─── Sub-components ───────────────────────────────────────────────────────────

interface KeyFieldProps {
  provider: AIProvider;
  providerInfo: { value: AIProvider; label: string; keyUrl: string };
  openaiKey: string;
  groqKey: string;
  geminiKey: string;
  showKeys: Record<AIProvider, boolean>;
  onOpenaiChange: (v: string) => void;
  onGroqChange: (v: string) => void;
  onGeminiChange: (v: string) => void;
  onToggleShow: (p: AIProvider) => void;
  onTest: () => void;
  testing: boolean;
}

function ProviderKeyField({
  provider,
  providerInfo,
  openaiKey,
  groqKey,
  geminiKey,
  showKeys,
  onOpenaiChange,
  onGroqChange,
  onGeminiChange,
  onToggleShow,
  onTest,
  testing,
}: KeyFieldProps) {
  const keyMap: Record<AIProvider, { value: string; onChange: (v: string) => void }> = {
    openai: { value: openaiKey, onChange: onOpenaiChange },
    groq:   { value: groqKey,   onChange: onGroqChange },
    gemini: { value: geminiKey, onChange: onGeminiChange },
  };

  const { value, onChange } = keyMap[provider];
  const show = showKeys[provider];

  return (
    <div>
      <label
        htmlFor={`tzaw-api-key-${provider}`}
        className="block text-sm font-medium text-text-primary mb-1.5"
      >
        {providerInfo.label} API Key
      </label>
      <div className="flex gap-2">
        <div className="relative flex-1">
          <input
            id={`tzaw-api-key-${provider}`}
            type={show ? 'text' : 'password'}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder={
              provider === 'openai' ? 'sk-…' :
              provider === 'groq' ? 'gsk_…' :
              'AI…'
            }
            className="w-full border border-surface-border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand pr-14"
            autoComplete="off"
          />
          <button
            type="button"
            onClick={() => onToggleShow(provider)}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-text-muted hover:text-text-secondary"
          >
            {show ? 'Hide' : 'Show'}
          </button>
        </div>
        <button
          type="button"
          id={`tzaw-btn-test-${provider}`}
          onClick={onTest}
          disabled={testing}
          className="px-4 py-2.5 border border-surface-border rounded-lg text-sm font-medium text-text-secondary hover:bg-surface-hover transition-colors disabled:opacity-40 whitespace-nowrap flex items-center gap-1.5"
        >
          {testing ? <Spinner size="sm" label="" /> : null}
          Test
        </button>
      </div>
      <p className="text-xs text-text-muted mt-1.5">
        Stored securely on the server.{' '}
        <a
          href={providerInfo.keyUrl}
          target="_blank"
          rel="noreferrer"
          className="text-brand hover:underline"
        >
          Get your API key →
        </a>
      </p>
    </div>
  );
}

function ProviderStatusBadge({
  provider,
  openaiKey,
  groqKey,
  geminiKey,
}: {
  provider: AIProvider;
  openaiKey: string;
  groqKey: string;
  geminiKey: string;
}) {
  const hasKey =
    provider === 'openai' ? !!openaiKey.trim() :
    provider === 'groq'   ? !!groqKey.trim() :
    !!geminiKey.trim();

  return (
    <span
      className={`text-xs px-2.5 py-1 rounded-full font-medium ${
        hasKey
          ? 'bg-green-50 text-green-700 border border-green-100'
          : 'bg-gray-50 text-text-muted border border-surface-border'
      }`}
    >
      {hasKey ? '● Configured' : '○ Not configured'}
    </span>
  );
}
