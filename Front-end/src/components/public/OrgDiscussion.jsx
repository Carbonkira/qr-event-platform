import { useState } from 'react'
import { Link } from 'react-router-dom'
import { MessageSquare, MessagesSquare, Lock } from 'lucide-react'
import { Card, Btn, Input, Textarea } from '../ui'
import { useApp } from '../../context/AppContext'
import { useDiscussionThreads } from '../../hooks/useApi'
import { createDiscussionThread } from '../../api/resources'
import { timeAgo } from '../../lib/utils'

// Async, message-board style discussion for an organization - open to any
// member, or anyone registered for one of the org's events (enforced
// server-side; a 403 here just means neither is true for this visitor).
export default function OrgDiscussion({ org }) {
  const { user, addToast } = useApp()
  const { data, loading, error, refetch } = useDiscussionThreads(org.id, !!user)
  const threads = data || []
  const [composing, setComposing] = useState(false)
  const [title, setTitle] = useState('')
  const [body, setBody] = useState('')
  const [posting, setPosting] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    if (!title.trim() || !body.trim()) return
    setPosting(true)
    try {
      await createDiscussionThread(org.id, { title: title.trim(), body: body.trim() })
      setTitle(''); setBody(''); setComposing(false)
      addToast('Thread posted', 'success')
      refetch()
    } catch (err) {
      addToast(err.message || 'Failed to post thread', 'error')
    } finally {
      setPosting(false)
    }
  }

  if (!user) {
    return (
      <Card className="p-8 text-center text-[13px] text-slate-500">
        <Lock size={22} className="mx-auto mb-2 text-slate-300" />
        <Link to={`/login?next=/org/${org.slug}`} className="font-semibold text-[#1a1a2e] hover:text-[#e94560]">Log in</Link> to view this organization's discussion board.
      </Card>
    )
  }

  if (error?.status === 403) {
    return (
      <Card className="p-8 text-center text-[13px] text-slate-500">
        <Lock size={22} className="mx-auto mb-2 text-slate-300" />
        Only members and people registered for one of {org.name}'s events can view its discussion board.
      </Card>
    )
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <p className="text-[12px] text-slate-500">Ask questions, coordinate, or just say hi.</p>
        <Btn size="sm" variant="secondary" onClick={() => setComposing(o => !o)}>{composing ? 'Cancel' : 'New thread'}</Btn>
      </div>

      {composing && (
        <Card className="p-4 mb-4">
          <form onSubmit={submit} className="space-y-3">
            <Input value={title} onChange={e => setTitle(e.target.value)} placeholder="Title" required />
            <Textarea value={body} onChange={e => setBody(e.target.value)} rows={3} placeholder="What's on your mind?" required />
            <div className="flex justify-end"><Btn type="submit" size="sm" loading={posting}>Post</Btn></div>
          </form>
        </Card>
      )}

      {loading ? (
        <div className="text-center py-10 text-slate-400 text-[13px]">Loading…</div>
      ) : threads.length === 0 ? (
        <Card className="p-8 text-center text-[13px] text-slate-400"><MessagesSquare size={22} className="mx-auto mb-2 text-slate-300" />No threads yet — be the first to post.</Card>
      ) : (
        <div className="space-y-2">
          {threads.map(t => (
            <Link key={t.id} to={`/org/${org.slug}/discussion/${t.id}`}>
              <Card hover className="p-4 flex items-center gap-3">
                <div className="flex-1 min-w-0">
                  <p className="font-semibold text-[14px] text-slate-800 truncate">{t.title}</p>
                  <p className="text-[11px] text-slate-400 mt-0.5">{t.author?.name} · {timeAgo(t.createdAt)}</p>
                </div>
                <div className="flex items-center gap-1.5 text-[12px] text-slate-400 flex-shrink-0"><MessageSquare size={13} />{t.repliesCount ?? 0}</div>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}
