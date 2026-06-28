// Global type declarations for WordPress-injected data and plugin types

// ─── WordPress Globals ─────────────────────────────────────────────────────

declare global {
  interface Window {
    tzawData: TzawData;
  }
}

export interface TzawData {
  apiUrl: string;
  nonce: string;
  siteUrl: string;
  adminUrl: string;
  categories: WPCategory[];
  settings: PublicSettings;
}

// ─── WordPress ─────────────────────────────────────────────────────────────

export interface WPCategory {
  id: number;
  name: string;
}

// ─── Settings ──────────────────────────────────────────────────────────────

export type AIProvider = 'openai' | 'groq' | 'gemini';

export interface ProviderModel {
  value: string;
  label: string;
}

export interface PublicSettings {
  ai_provider: AIProvider;
  ai_model: string;
  default_tone: Tone;
  default_word_count: WordCount;
  default_category: number;
  has_openai_key: boolean;
  has_groq_key: boolean;
  has_gemini_key: boolean;
  provider_models: Record<AIProvider, ProviderModel[]>;
}

export interface FullSettings {
  ai_provider: AIProvider;
  openai_api_key: string;
  groq_api_key: string;
  gemini_api_key: string;
  ai_model: string;
  default_tone: Tone;
  default_word_count: WordCount;
  default_category: number;
  provider_models?: Record<AIProvider, ProviderModel[]>;
}

// ─── Trending ──────────────────────────────────────────────────────────────

export interface TrendingTopic {
  id: string;
  title: string;
  category: string;
  source: string;
  score: number;
  url: string;
}

// ─── Generation ────────────────────────────────────────────────────────────

export type Tone =
  | 'professional'
  | 'friendly'
  | 'conversational'
  | 'beginner-friendly'
  | 'technical';

export type WordCount = 1000 | 1500 | 2000 | 3000;

export interface GenerateParams {
  title: string;
  keyword: string;
  secondary_keywords?: string;
  audience?: string;
  tone: Tone;
  word_count: WordCount;
}

export interface GeneratedArticle {
  seoTitle: string;
  metaDescription: string;
  slug: string;
  focusKeyword: string;
  content: string;
}

// ─── Drafts ────────────────────────────────────────────────────────────────

export interface DraftData {
  title: string;
  content: string;
  meta_description: string;
  focus_keyword: string;
  categories: number[];
  tags: string[];
}

export interface RecentDraft {
  id: number;
  title: string;
  status: 'draft' | 'publish';
  date: string;
  edit_url: string;
  view_url: string;
}

export interface DashboardStats {
  total_generated: number;
  recent_drafts: RecentDraft[];
}

// ─── App Navigation ────────────────────────────────────────────────────────

export type AppRoute = 'dashboard' | 'generate' | 'settings';

// ─── API Responses ─────────────────────────────────────────────────────────

export interface ApiResponse<T> {
  success: boolean;
  data: T;
}

export interface ApiError {
  code: string;
  message: string;
  data?: Record<string, unknown>;
}
