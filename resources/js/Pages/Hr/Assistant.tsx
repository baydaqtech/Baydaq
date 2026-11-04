import { useEffect, useRef, useState } from 'react';
import { RotateCcw, Send, Sparkles } from 'lucide-react';
import PageHeader from '@/components/platform/PageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import SuiteLayout from '@/layouts/SuiteLayout';
import { postJson } from '@/lib/http';
import { useCopy, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';

interface Turn { role: 'user' | 'assistant'; content: string }

/** The HR assistant (H15): a chat over HR data, limited to what the asker may see. */
export default function HrAssistant() {
  const t = useT();
  const url = useSuiteUrl();
  const examples = useCopy<string[] | undefined>('hr.assistant.examples') ?? [];
  const [conversation, setConversation] = useState<Turn[]>([]);
  const [question, setQuestion] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const bottom = useRef<HTMLDivElement>(null);

  useEffect(() => bottom.current?.scrollIntoView({ behavior: 'smooth', block: 'end' }), [conversation, busy]);

  async function ask(text: string) {
    const content = text.trim();

    if (content === '' || busy) {
      return;
    }

    const next: Turn[] = [...conversation, { role: 'user', content }].slice(-20) as Turn[];
    setConversation(next);
    setQuestion('');
    setBusy(true);
    setError(null);

    try {
      const { answer } = await postJson<{ answer: string }>(url('/hr/assistant'), { conversation: next });
      setConversation([...next, { role: 'assistant', content: answer }]);
    } catch (failure) {
      setError((failure as Error).message);
      setConversation(conversation);
      setQuestion(content);
    } finally {
      setBusy(false);
    }
  }

  return (
    <SuiteLayout title={t('hr.assistant.title')}>
      <PageHeader
        title={t('hr.assistant.title')}
        description={t('hr.assistant.lede')}
        actions={conversation.length > 0 && <Button size="sm" variant="outline" onClick={() => { setConversation([]); setError(null); }}><RotateCcw data-icon="inline-start" />{t('hr.assistant.clear')}</Button>}
      />
      <Card className="mx-auto max-w-3xl">
        <CardContent className="flex flex-col gap-4">
          {conversation.length === 0 && (
            <div className="flex flex-wrap gap-2">
              {examples.map((example) => (
                <Button key={example} size="sm" variant="outline" className="h-auto whitespace-normal py-1.5 text-start" onClick={() => ask(example)}>
                  <Sparkles data-icon="inline-start" />{example}
                </Button>
              ))}
            </div>
          )}
          <div className="flex flex-col gap-3" aria-live="polite">
            {conversation.map((turn, index) => (
              <div key={index} className={cn('max-w-[85%] whitespace-pre-line rounded-2xl px-4 py-2 text-sm', turn.role === 'user' ? 'self-end bg-walnut text-ivory' : 'self-start bg-muted')}>
                {turn.content}
              </div>
            ))}
            {busy && <div className="self-start animate-pulse rounded-2xl bg-muted px-4 py-2 text-sm text-muted-foreground">{t('hr.assistant.thinking')}</div>}
            <div ref={bottom} />
          </div>
          {error && <p className="text-sm text-destructive">{error}</p>}
          <form className="flex items-end gap-2" onSubmit={(event) => { event.preventDefault(); ask(question); }}>
            <Textarea
              rows={2}
              value={question}
              placeholder={t('hr.assistant.placeholder')}
              onChange={(event) => setQuestion(event.target.value)}
              onKeyDown={(event) => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); ask(question); } }}
              maxLength={4000}
            />
            <Button type="submit" disabled={busy || question.trim() === ''}><Send data-icon="inline-start" />{t('hr.assistant.send')}</Button>
          </form>
          <p className="text-xs text-muted-foreground">{t('hr.assistant.note')}</p>
        </CardContent>
      </Card>
    </SuiteLayout>
  );
}
