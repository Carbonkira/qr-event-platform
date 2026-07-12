import { Link } from 'react-router-dom'
import { Compass, Ticket, ScanLine, ArrowRight, Cpu, GraduationCap, Palette, Landmark, HeartPulse, Megaphone, Building2, Sparkles } from 'lucide-react'
import { Card, Btn } from '../ui'
import { INDUSTRIES } from '../../lib/utils'

const STEPS = [
  { icon: Compass, title: 'Discover', desc: 'Browse events happening near you, sorted by real distance once you share your location.' },
  { icon: Ticket, title: 'Register', desc: 'Grab your spot in a couple of clicks — you get a QR pass instantly, no printing required.' },
  { icon: ScanLine, title: 'Check in', desc: 'Show your pass at the door for an instant scan-in, then share feedback once it wraps up.' },
]

// One icon + accent color per industry (see lib/utils.js INDUSTRIES - the
// same fixed list the create/edit event forms use), so a category picked
// when organizing an event always renders the same way here.
const CATEGORY_STYLE = {
  Technology: { icon: Cpu, color: '#1a1a2e' },
  Education: { icon: GraduationCap, color: '#0f9d8f' },
  Design: { icon: Palette, color: '#e94560' },
  Finance: { icon: Landmark, color: '#b3760a' },
  Healthcare: { icon: HeartPulse, color: '#dc2626' },
  Marketing: { icon: Megaphone, color: '#6d28d9' },
  'Real Estate': { icon: Building2, color: '#0369a1' },
  Other: { icon: Sparkles, color: '#64748b' },
}

// Shown only to logged-out visitors, above the real event browser (Home.jsx
// renders this then falls straight into the same search/listing everyone
// gets) - so a guest never hits a dead end, they just scroll past the pitch
// into real, live events.
export default function LandingHero({ events, onSelectCategory }) {
  const countByIndustry = events.reduce((acc, e) => {
    if (!e.industry) return acc
    acc[e.industry] = (acc[e.industry] || 0) + 1
    return acc
  }, {})
  // Show every category the create-event form offers, not just ones with
  // events yet - a new category naturally starts at 0 and fills in over
  // time, same as Meetup/Luma's own static category grids.
  const categories = INDUSTRIES.map(name => [name, countByIndustry[name] || 0]).sort((a, b) => b[1] - a[1])

  const scrollToListing = () => document.getElementById('event-listing')?.scrollIntoView({ behavior: 'smooth' })

  return (
    <div className="mb-12">
      <div className="relative overflow-hidden rounded-3xl bg-[#1a1a2e] px-6 py-14 sm:px-12 sm:py-20 text-center mb-10">
        <div className="absolute inset-0 opacity-40" style={{ background: 'radial-gradient(circle at 15% 20%, #e94560 0%, transparent 45%), radial-gradient(circle at 85% 80%, #6d28d9 0%, transparent 45%)' }} />
        <div className="relative">
          <h1 className="text-3xl sm:text-[40px] font-extrabold text-white tracking-tight leading-tight text-balance">Find your people.<br />Meet in person.</h1>
          <p className="text-[15px] text-slate-300 mt-4 max-w-lg mx-auto">
            Real events, real rooms, real people — register in seconds and check in with a QR pass.
          </p>
          <div className="flex items-center justify-center gap-3 mt-8 flex-wrap">
            <Btn variant="accent" size="lg" onClick={scrollToListing}>Browse Events</Btn>
            <Link to="/organizer/register"><Btn variant="dark" size="lg" icon={ArrowRight}>Host your own</Btn></Link>
          </div>
        </div>
      </div>

      <div className="grid sm:grid-cols-3 gap-4 mb-10">
        {STEPS.map(({ icon: Icon, title, desc }, i) => (
          <Card key={title} className="p-5">
            <div className="flex items-center gap-2.5 mb-2">
              <div className="w-8 h-8 rounded-lg bg-[#1a1a2e] text-white flex items-center justify-center text-[12px] font-bold flex-shrink-0">{i + 1}</div>
              <Icon size={17} className="text-[#e94560]" />
              <p className="font-bold text-[14px]">{title}</p>
            </div>
            <p className="text-[12.5px] text-slate-500 leading-relaxed">{desc}</p>
          </Card>
        ))}
      </div>

      <div>
        <p className="font-bold text-[15px] text-slate-800 mb-3">Browse by category</p>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {categories.map(([name, count]) => {
            const { icon: Icon, color } = CATEGORY_STYLE[name] || CATEGORY_STYLE.Other
            return (
              <Card
                key={name} hover
                className="p-4 flex items-center gap-3 cursor-pointer"
                onClick={() => { onSelectCategory?.(name); scrollToListing() }}
              >
                <div className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style={{ background: `${color}1a`, color }}>
                  <Icon size={19} />
                </div>
                <div className="min-w-0">
                  <p className="font-bold text-[13.5px] text-slate-800 truncate">{name}</p>
                  <p className="text-[11.5px] text-slate-400">{count} event{count === 1 ? '' : 's'}</p>
                </div>
              </Card>
            )
          })}
        </div>
      </div>
    </div>
  )
}
