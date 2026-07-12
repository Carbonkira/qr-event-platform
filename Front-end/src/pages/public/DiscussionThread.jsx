import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, Lock, MessageSquare } from 'lucide-react'
import { Card, Btn, Textarea } from '../../components/ui'
import { useApp } from '../../context/AppContext'
import { useDiscussionThread } from '../../hooks/useApi'
import { replyToDiscussionThread } from '../../api/resources'
import { timeAgo } from '../../lib/utils'

export default function DiscussionThread() {
  const { slug, threadId } = useParams()
  const navigate = useNavigate()
  const { user, addToast } = useApp()
  const { data: thread, loading, error, refetch } = useDiscussionThread(user ? threadId : null)
  const [body, setBody] = useState('')
  const [posting, setPosting] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    if (!body.trim()) return
    setPosting(true)
    try {
      await replyToDiscussionThread(threadId, body.trim())
      setBody('')
      refetch()
    } catch (err) {
      addToast(err.message || 'Failed to post reply', 'error')
    } finally {
      setPosting(false)
    }
  }

  if (!user) {
    return (
      <div className="max-w-2xl mx-auto px-5 py-16 text-center">
        <Card className="p-8">
          <Lock size={22} className="mx-auto mb-2 text-slate-300" />
          <p className="text-[13px] text-slate-500"><Link to={`/login?next=/org/${slug}/discussion/${threadId}`} className="font-semibold text-[#1a1a2e] hover:text-[#e94560]">Log in</Link> to view this thread.</p>
        </Card>
      </div>
    )
  }

  if (loading) return <div className="text-center py-20 text-slate-400 text-[13px]">Loading…</div>

  if (error?.status === 403 || !thread) {
    return (
      <div className="max-w-2xl mx-auto px-5 py-16 text-center">
        <Card className="p-8 text-[13px] text-slate-500">
          <Lock size={22} className="mx-auto mb-2 text-slate-300" />
          Only members and registered attendees can view this thread.
        </Card>
      </div>
    )
  }

  return (
    <div className="max-w-2xl mx-auto px-5 py-6">
      <button onClick={() => navigate(`/org/${slug}/discussion`)} className="flex items-center gap-1.5 text-[13px] text-slate-500 hover:text-slate-800 mb-4 font-medium"><ArrowLeft size={15} />Discussion</button>

      <Card className="p-5 mb-4">
        <h1 className="text-xl font-extrabold mb-1">{thread.title}</h1>
        <p className="text-[11px] text-slate-400 mb-3">{thread.author?.name} · {timeAgo(thread.createdAt)}</p>
        <p className="text-[14px] text-slate-700 whitespace-pre-line">{thread.body}</p>
      </Card>

      <p className="text-[12px] font-bold text-slate-500 uppercase tracking-wide mb-2 flex items-center gap-1.5"><MessageSquare size={13} />{(thread.replies || []).length} {thread.replies?.length === 1 ? 'reply' : 'replies'}</p>

      <div className="space-y-3 mb-5">
        {(thread.replies || []).map(r => (
          <Card key={r.id} className="p-4">
            <p className="text-[13px] text-slate-700 whitespace-pre-line">{r.body}</p>
            <p className="text-[11px] text-slate-400 mt-2">{r.author?.name} · {timeAgo(r.createdAt)}</p>
          </Card>
        ))}
      </div>

      <Card className="p-4">
        <form onSubmit={submit} className="space-y-3">
          <Textarea value={body} onChange={e => setBody(e.target.value)} rows={3} placeholder="Write a reply…" required />
          <div className="flex justify-end"><Btn type="submit" size="sm" loading={posting}>Reply</Btn></div>
        </form>
      </Card>
    </div>
  )
}
