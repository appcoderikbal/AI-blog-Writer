import React, { useState, useEffect } from 'react';
import TopicSelector from './TopicSelector';
import BlogForm from './BlogForm';
import ArticleEditor from './ArticleEditor';
import { useGenerate } from '../../hooks/useGenerate';
import type { GenerateParams, TrendingTopic } from '../../types';

type Step = 'topic' | 'form' | 'editor';

export default function GenerateBlog() {
  const [step, setStep] = useState<Step>('topic');
  const [selectedTopic, setSelectedTopic] = useState<TrendingTopic | null>(null);
  const { article, loading, error, generate, improve, reset, setArticle } = useGenerate();

  // Pick up topic pre-selected from Dashboard
  useEffect(() => {
    const stored = sessionStorage.getItem('tzaw_selected_topic');
    if (stored) {
      try {
        const topic: TrendingTopic = JSON.parse(stored);
        setSelectedTopic(topic);
        setStep('form');
        sessionStorage.removeItem('tzaw_selected_topic');
      } catch {
        // ignore
      }
    }
  }, []);

  const handleTopicSelect = (topic: TrendingTopic | null) => {
    setSelectedTopic(topic);
    setStep('form');
  };

  const handleGenerate = async (params: GenerateParams) => {
    try {
      await generate(params);
      setStep('editor');
    } catch {
      // Error is stored in hook state
    }
  };

  const handleRegenerate = () => {
    reset();
    setStep('form');
  };

  return (
    <div className="flex-1 flex flex-col animate-fade-in">
      {/* Step indicator */}
      <div className="px-8 pt-6 pb-0">
        <div className="flex items-center gap-2 text-xs text-text-secondary mb-6">
          <StepDot active={step === 'topic'} done={step !== 'topic'} label="1. Topic" />
          <div className="h-px flex-1 bg-surface-border" />
          <StepDot active={step === 'form'} done={step === 'editor'} label="2. Configure" />
          <div className="h-px flex-1 bg-surface-border" />
          <StepDot active={step === 'editor'} done={false} label="3. Editor" />
        </div>
      </div>

      {/* Page content */}
      {step === 'topic' && (
        <TopicSelector onSelect={handleTopicSelect} />
      )}

      {step === 'form' && (
        <BlogForm
          topic={selectedTopic}
          loading={loading}
          error={error}
          onGenerate={handleGenerate}
          onBack={() => setStep('topic')}
        />
      )}

      {step === 'editor' && article && (
        <ArticleEditor
          article={article}
          loading={loading}
          onImprove={improve}
          onRegenerate={handleRegenerate}
          onArticleChange={setArticle}
        />
      )}
    </div>
  );
}

function StepDot({
  active,
  done,
  label,
}: {
  active: boolean;
  done: boolean;
  label: string;
}) {
  return (
    <span
      className={`font-medium ${
        active
          ? 'text-brand'
          : done
          ? 'text-text-secondary line-through'
          : 'text-text-muted'
      }`}
    >
      {label}
    </span>
  );
}
