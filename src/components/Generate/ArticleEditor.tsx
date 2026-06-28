import React, { useCallback, useRef, useState } from 'react';
import { useDraft } from '../../hooks/useDraft';
import { useToast } from '../UI/Toast';
import Spinner from '../UI/Spinner';
import type { GeneratedArticle } from '../../types';

interface ArticleEditorProps {
  article: GeneratedArticle;
  loading: boolean;
  onImprove: (
    content: string,
    instruction: 'improve' | 'expand' | 'shorten'
  ) => Promise<string>;
  onRegenerate: () => void;
  onArticleChange: (article: GeneratedArticle | null) => void;
}

const categories = window.tzawData?.categories ?? [];

export default function ArticleEditor({
  article,
  loading,
  onImprove,
  onRegenerate,
  onArticleChange,
}: ArticleEditorProps) {
  const [title, setTitle] = useState(article.seoTitle);
  const [metaDescription, setMetaDescription] = useState(article.metaDescription);
  const [tags, setTags] = useState('');
  const [selectedCategories, setSelectedCategories] = useState<number[]>([]);
  const [activeImprove, setActiveImprove] = useState<string | null>(null);
  const editorRef = useRef<HTMLDivElement>(null);
  const { saving, save } = useDraft();
  const { showToast } = useToast();

  // Toolbar formatting helper
  const execCommand = useCallback((command: string, value?: string) => {
    document.execCommand(command, false, value);
    editorRef.current?.focus();
  }, []);

  const getCurrentContent = () => editorRef.current?.innerHTML ?? article.content;

  const handleImprove = async (instruction: 'improve' | 'expand' | 'shorten') => {
    const content = getCurrentContent();
    setActiveImprove(instruction);
    try {
      const newContent = await onImprove(content, instruction);
      if (editorRef.current) {
        editorRef.current.innerHTML = newContent;
      }
      onArticleChange({ ...article, content: newContent, seoTitle: title, metaDescription });
      showToast(`Article ${instruction}d successfully.`, 'success');
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to improve article.';
      showToast(msg, 'error');
    } finally {
      setActiveImprove(null);
    }
  };

  const handleSaveDraft = async () => {
    const content = getCurrentContent();
    try {
      const result = await save({
        title,
        content,
        meta_description: metaDescription,
        focus_keyword: article.focusKeyword,
        categories: selectedCategories,
        tags: tags
          .split(',')
          .map((t) => t.trim())
          .filter(Boolean),
      });
      showToast(`Draft saved! Post ID: ${result.post_id}`, 'success');
      setTimeout(() => {
        if (result.edit_url) {
          window.open(result.edit_url, '_blank');
        }
      }, 800);
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to save draft.';
      showToast(msg, 'error');
    }
  };

  return (
    <div className="flex-1 flex flex-col overflow-hidden animate-fade-in">
      {/* Toolbar */}
      <div className="border-b border-surface-border bg-white px-6 py-3 flex items-center gap-2 flex-wrap">
        {/* Formatting */}
        <div className="flex items-center gap-1 border-r border-surface-border pr-3 mr-1">
          <ToolbarBtn onClick={() => execCommand('bold')} title="Bold" label="B" bold />
          <ToolbarBtn onClick={() => execCommand('italic')} title="Italic" label="I" italic />
          <ToolbarBtn onClick={() => execCommand('underline')} title="Underline" label="U" underline />
        </div>
        <div className="flex items-center gap-1 border-r border-surface-border pr-3 mr-1">
          <ToolbarBtn onClick={() => execCommand('formatBlock', 'h2')} title="Heading 2" label="H2" />
          <ToolbarBtn onClick={() => execCommand('formatBlock', 'h3')} title="Heading 3" label="H3" />
          <ToolbarBtn onClick={() => execCommand('formatBlock', 'p')} title="Paragraph" label="P" />
        </div>
        <div className="flex items-center gap-1 border-r border-surface-border pr-3 mr-1">
          <ToolbarBtn onClick={() => execCommand('insertUnorderedList')} title="Bullet list" label="• List" />
          <ToolbarBtn onClick={() => execCommand('insertOrderedList')} title="Numbered list" label="1. List" />
        </div>

        {/* Spacer */}
        <div className="flex-1" />

        {/* AI Actions */}
        {(['improve', 'expand', 'shorten'] as const).map((action) => (
          <button
            key={action}
            id={`tzaw-btn-${action}`}
            onClick={() => handleImprove(action)}
            disabled={loading || saving}
            className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-surface-border rounded-md hover:bg-surface-hover text-text-secondary hover:text-text-primary transition-colors disabled:opacity-40"
          >
            {activeImprove === action ? <Spinner size="sm" label="" /> : null}
            {action.charAt(0).toUpperCase() + action.slice(1)}
          </button>
        ))}

        <button
          id="tzaw-btn-regenerate"
          onClick={onRegenerate}
          disabled={loading || saving}
          className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-surface-border rounded-md hover:bg-surface-hover text-text-secondary hover:text-text-primary transition-colors disabled:opacity-40"
        >
          ↺ Regenerate
        </button>

        <button
          id="tzaw-btn-save-draft"
          onClick={handleSaveDraft}
          disabled={loading || saving}
          className="flex items-center gap-2 px-4 py-1.5 bg-brand hover:bg-brand-hover text-white text-xs font-medium rounded-md transition-colors disabled:opacity-40"
        >
          {saving ? <Spinner size="sm" label="" /> : null}
          {saving ? 'Saving…' : '💾 Save Draft'}
        </button>
      </div>

      {/* Two-column layout: editor + metadata */}
      <div className="flex-1 flex overflow-hidden">
        {/* Editor area */}
        <div className="flex-1 overflow-y-auto px-10 py-8">
          {/* SEO Title */}
          <input
            id="tzaw-editor-title"
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            className="w-full text-2xl font-bold text-text-primary border-none outline-none mb-2 placeholder-text-muted bg-transparent"
            placeholder="Article title…"
          />

          {/* Meta description inline */}
          <textarea
            id="tzaw-editor-meta"
            value={metaDescription}
            onChange={(e) => setMetaDescription(e.target.value)}
            rows={2}
            className="w-full text-sm text-text-secondary border-none outline-none resize-none mb-6 bg-transparent placeholder-text-muted"
            placeholder="Meta description…"
          />

          <hr className="border-surface-border mb-6" />

          {/* Article body */}
          <div
            id="tzaw-editor-body"
            ref={editorRef}
            className="tzaw-editor-body tzaw-prose focus:outline-none"
            contentEditable
            suppressContentEditableWarning
            dangerouslySetInnerHTML={{ __html: article.content }}
          />
        </div>

        {/* Right panel: meta & save */}
        <div className="w-72 border-l border-surface-border bg-white overflow-y-auto p-5 shrink-0">
          <h3 className="text-xs font-semibold text-text-secondary uppercase tracking-wide mb-4">
            Article Details
          </h3>

          {/* Slug */}
          <div className="mb-4">
            <label className="block text-xs font-medium text-text-secondary mb-1">
              Suggested Slug
            </label>
            <div className="text-xs bg-surface-hover rounded-md px-3 py-2 font-mono text-text-secondary break-all">
              {article.slug}
            </div>
          </div>

          {/* Focus keyword */}
          <div className="mb-4">
            <label className="block text-xs font-medium text-text-secondary mb-1">
              Focus Keyword
            </label>
            <div className="text-xs bg-surface-hover rounded-md px-3 py-2 text-text-secondary">
              {article.focusKeyword}
            </div>
          </div>

          {/* Category */}
          {categories.length > 0 && (
            <div className="mb-4">
              <label htmlFor="tzaw-category" className="block text-xs font-medium text-text-secondary mb-1">
                Category
              </label>
              <select
                id="tzaw-category"
                multiple
                value={selectedCategories.map(String)}
                onChange={(e) => {
                  const vals = Array.from(e.target.selectedOptions).map((o) =>
                    Number(o.value)
                  );
                  setSelectedCategories(vals);
                }}
                className="w-full border border-surface-border rounded-md text-xs p-2 h-28 focus:outline-none focus:ring-2 focus:ring-brand/30"
              >
                {categories.map((cat) => (
                  <option key={cat.id} value={cat.id}>
                    {cat.name}
                  </option>
                ))}
              </select>
              <p className="text-xs text-text-muted mt-1">Hold Ctrl to select multiple</p>
            </div>
          )}

          {/* Tags */}
          <div className="mb-4">
            <label htmlFor="tzaw-tags" className="block text-xs font-medium text-text-secondary mb-1">
              Tags
            </label>
            <input
              id="tzaw-tags"
              type="text"
              value={tags}
              onChange={(e) => setTags(e.target.value)}
              placeholder="ai, tools, coding"
              className="w-full border border-surface-border rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand/30"
            />
            <p className="text-xs text-text-muted mt-1">Comma-separated</p>
          </div>

          <hr className="border-surface-border mb-4" />

          {/* Save Draft button (duplicate for convenience) */}
          <button
            onClick={handleSaveDraft}
            disabled={loading || saving}
            className="w-full py-2.5 bg-brand hover:bg-brand-hover text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-40"
          >
            {saving ? 'Saving…' : '💾 Save as Draft'}
          </button>

          <p className="text-xs text-text-muted text-center mt-2">
            Saves to WordPress Drafts.
            <br />Does not publish automatically.
          </p>
        </div>
      </div>
    </div>
  );
}

function ToolbarBtn({
  onClick,
  label,
  title,
  bold,
  italic,
  underline,
}: {
  onClick: () => void;
  label: string;
  title: string;
  bold?: boolean;
  italic?: boolean;
  underline?: boolean;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      title={title}
      className={`px-2 py-1 text-xs rounded hover:bg-surface-hover text-text-secondary hover:text-text-primary transition-colors ${
        bold ? 'font-bold' : ''
      } ${italic ? 'italic' : ''} ${underline ? 'underline' : ''}`}
    >
      {label}
    </button>
  );
}
