import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { Building2, CheckCircle2 } from 'lucide-react'
import { Card, Btn } from '../../components/ui'
import { useApp } from '../../context/AppContext'
import { getInvite, acceptInvite } from '../../api/resources'

export default function InviteAccept() {
  const { token } = useParams()
  const navigate = useNavigate()
  const { user, authReady, addToast } = useApp()
  const [invite, setInvite] = useState(null)
  const [loading, setLoading] = useState(true)
  const [accepting, setAccepting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    getInvite(token)
      .then(setInvite)
      .catch(err => setError(err.message || 'This invite link is invalid.'))
      .finally(() => setLoading(false))
  }, [token])

  const accept = async () => {
    setAccepting(true)
    try {
      await acceptInvite(token)
      addToast('Invite accepted!', 'success')
      navigate('/organizer/organizations')
    } catch (err) {
      addToast(err.message || 'Failed to accept invite', 'error')
    } finally {
      setAccepting(false)
    }
  }

  if (loading || !authReady) return <div className="max-w-md mx-auto px-5 py-16 text-center text-slate-400 text-[13px]">Loading…</div>

  if (error) {
    return (
      <div className="max-w-md mx-auto px-5 py-16">
        <Card className="p-8 text-center">
          <p className="text-[14px] text-slate-500">{error}</p>
        </Card>
      </div>
    )
  }

  return (
    <div className="max-w-md mx-auto px-5 py-16">
      <Card className="p-8 text-center">
        <div className="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4 overflow-hidden">
          {invite.organization.logo ? <img src={invite.organization.logo} alt="" className="w-full h-full object-cover" /> : <Building2 size={24} className="text-slate-400" />}
        </div>
        <h1 className="text-xl font-extrabold mb-1">Join {invite.organization.name}</h1>
        <p className="text-[13px] text-slate-500 mb-6">{invite.inviter_name} invited <b>{invite.email}</b> to join as a member.</p>

        {invite.accepted ? (
          <p className="text-[13px] text-emerald-600 flex items-center justify-center gap-1.5"><CheckCircle2 size={15} />This invite has already been accepted.</p>
        ) : invite.expired ? (
          <p className="text-[13px] text-rose-500">This invite has expired. Ask an owner of {invite.organization.name} to send a new one.</p>
        ) : !user ? (
          <div className="space-y-2">
            <Btn full variant="accent" onClick={() => navigate(`/login?next=/invites/${token}`)}>Log in to accept</Btn>
            <Btn full variant="secondary" onClick={() => navigate(`/organizer/register?next=/invites/${token}`)}>Create an account</Btn>
          </div>
        ) : user.email.toLowerCase() !== invite.email.toLowerCase() ? (
          <p className="text-[13px] text-amber-600">You're signed in as <b>{user.email}</b>, but this invite was sent to <b>{invite.email}</b>. Log in with that account to accept it.</p>
        ) : (
          <Btn full variant="accent" loading={accepting} onClick={accept}>Accept invite</Btn>
        )}

        <p className="text-[12px] text-slate-400 mt-6"><Link to="/" className="font-semibold text-[#1a1a2e] hover:text-[#e94560]">Back to QRMeets</Link></p>
      </Card>
    </div>
  )
}
