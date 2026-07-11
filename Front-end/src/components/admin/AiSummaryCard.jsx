import { useEffect, useState } from 'react'
import ReactMarkdown from 'react-markdown'
import remarkGfm from 'remark-gfm'
import { Sparkles, RefreshCw } from 'lucide-react'
import { Card, Btn } from '../ui'
import { getFeedbackSummary } from '../../api/resources'

// Gemini's feedback-summary report comes back as Markdown (headers, tables,
// bold) rather than the old plain-prose paragraph, so it needs an actual
// renderer - these overrides just keep it inside the app's own type scale
// and palette instead of the browser's unstyled h1/table defaults.
const SUMMARY_MARKDOWN_COMPONENTS = {
  h1: (p) => <h3 className="font-bold text-[15px] text-slate-800 mt-4 mb-2 first:mt-0" {...p} />,
  h2: (p) => <h4 className="font-bold text-[14px] text-slate-800 mt-4 mb-1.5 first:mt-0" {...p} />,
  h3: (p) => <h5 className="font-bold text-[13px] text-slate-700 mt-3 mb-1" {...p} />,
  p: (p) => <p className="text-[13px] text-slate-600 leading-relaxed mb-2.5 last:mb-0" {...p} />,
  ul: (p) => <ul className="list-disc pl-5 text-[13px] text-slate-600 leading-relaxed mb-2.5 space-y-1" {...p} />,
  ol: (p) => <ol className="list-decimal pl-5 text-[13px] text-slate-600 leading-relaxed mb-2.5 space-y-1" {...p} />,
  strong: (p) => <strong className="font-semibold text-slate-800" {...p} />,
  hr: () => <hr className="border-slate-100 my-3" />,
  table: (p) => <div className="overflow-x-auto mb-2.5"><table className="w-full text-[12px] border-collapse" {...p} /></div>,
  th: (p) => <th className="text-left font-semibold text-slate-500 border-b border-slate-200 px-2.5 py-1.5" {...p} />,
  td: (p) => <td className="text-slate-600 border-b border-slate-100 px-2.5 py-1.5" {...p} />,
}

// AI-generated synthesis of a single event's feedback comments — cached
// server-side on the event, "Regenerate" forces a fresh call. Shared between
// the org-wide Feedback page and each event's own Feedback tab.
export default function AiSummaryCard({ eventId, className = '' }) {
  const [summary, setSummary] = useState(null)
  const [loading, setLoading] = useState(true)
  const [regenerating, setRegenerating] = useState(false)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setSummary(null)
    getFeedbackSummary(eventId)
      .then(data => { if (!cancelled) setSummary(data) })
      .catch(() => { if (!cancelled) setSummary({ summary: null }) })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [eventId])

  const regenerate = async () => {
    setRegenerating(true)
    try {
      const data = await getFeedbackSummary(eventId, true)
      setSummary(data)
    } catch {
      // keep showing the previous summary if regeneration fails
    } finally {
      setRegenerating(false)
    }
  }

  return (
    <Card className={`p-5 ${className}`}>
      <div className="flex items-center justify-between mb-3">
        <p className="font-bold text-[14px] flex items-center gap-2"><Sparkles size={15} className="text-[#e94560]" />AI Summary</p>
        <Btn variant="ghost" size="sm" icon={RefreshCw} onClick={regenerate} disabled={loading || regenerating} loading={regenerating}>Regenerate</Btn>
      </div>
      {loading ? (
        <div className="flex items-center justify-center py-8"><span className="w-5 h-5 border-2 border-slate-300 border-t-[#1a1a2e] rounded-full animate-spin" /></div>
      ) : summary?.summary ? (
        <ReactMarkdown remarkPlugins={[remarkGfm]} components={SUMMARY_MARKDOWN_COMPONENTS}>{summary.summary}</ReactMarkdown>
      ) : (
        <p className="text-[13px] text-slate-600 leading-relaxed">Not enough feedback yet to summarize.</p>
      )}
    </Card>
  )
}
