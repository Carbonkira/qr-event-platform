import { useEffect, useRef, useState } from 'react'
import { Html5QrcodeScanner } from 'html5-qrcode'
import { CheckCircle2, AlertTriangle, XCircle, Hash } from 'lucide-react'
import { Card, Btn, Input } from '../ui'
import { scanAttendance } from '../../api/resources'

/**
 * Check-in scanning for a single, already-known event - the event-picker
 * dropdown from the old standalone /organizer/scan page isn't needed here
 * since EventDetail already fixes which event we're scanning for.
 */
export default function EventScannerPanel({ eventId, checkedInCount, totalCount, onCheckedIn }) {
  const [result, setResult] = useState(null)
  const [manualCode, setManualCode] = useState('')
  const [checking, setChecking] = useState(false)
  const [history, setHistory] = useState([])
  const scannerRef = useRef(null)
  const busyRef = useRef(false)

  const recordResult = (data, qr) => {
    setResult(data)
    setHistory(h => [{ ...data, qr, time: new Date() }, ...h.slice(0, 7)])
    onCheckedIn?.()
  }

  const handleScan = async (qrCode) => {
    if (busyRef.current) return
    busyRef.current = true
    try {
      const data = await scanAttendance(qrCode)
      recordResult(data, qrCode)
    } catch (err) {
      recordResult({ success: false, type: 'error', message: err.message }, qrCode)
    } finally {
      setTimeout(() => { busyRef.current = false }, 1500)
    }
  }

  useEffect(() => {
    const scanner = new Html5QrcodeScanner(`qr-reader-${eventId}`, { fps: 10, qrbox: 250 }, false)
    scanner.render(
      (decodedText) => handleScan(decodedText),
      () => {} // ignore per-frame scan errors, expected while searching
    )
    scannerRef.current = scanner
    return () => { scanner.clear().catch(() => {}) }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [eventId])

  const handleManualSubmit = async (e) => {
    e.preventDefault()
    if (!manualCode.trim()) return
    setChecking(true)
    try {
      const data = await scanAttendance(manualCode.trim())
      recordResult(data, manualCode.trim())
      setManualCode('')
    } catch (err) {
      recordResult({ success: false, type: 'error', message: err.message }, manualCode.trim())
    } finally {
      setChecking(false)
    }
  }

  const pct = totalCount ? (checkedInCount / totalCount) * 100 : 0

  return (
    <div className="space-y-5">
      <Card className="p-5 !bg-[#1a1a2e] text-white">
        <div className="flex items-center justify-between mb-3">
          <div><p className="text-[11px] text-slate-300 uppercase tracking-wide font-bold">Live check-in</p><p className="text-3xl font-extrabold mt-1">{checkedInCount}<span className="text-slate-400 text-lg"> / {totalCount}</span></p></div>
          <div className="text-right"><p className="text-2xl font-extrabold text-emerald-400">{pct.toFixed(0)}%</p><p className="text-[11px] text-slate-400">checked in</p></div>
        </div>
        <div className="h-2 rounded-full bg-white/10"><div className="h-full rounded-full bg-emerald-400 transition-all" style={{ width: `${pct}%` }} /></div>
      </Card>

      {result && <ResultBanner result={result} />}

      <div className="grid lg:grid-cols-2 gap-5">
        <Card className="p-5">
          <p className="font-bold text-[14px] mb-3">Camera scan</p>
          <div id={`qr-reader-${eventId}`} className="rounded-xl overflow-hidden" />
        </Card>

        <Card className="p-5">
          <p className="font-bold text-[14px] mb-3">Manual entry</p>
          <p className="text-[12px] text-slate-500 mb-3">Type or paste the QR code value if scanning isn't available.</p>
          <form onSubmit={handleManualSubmit} className="flex gap-2">
            <div className="flex-1"><Input icon={Hash} value={manualCode} onChange={e => setManualCode(e.target.value)} placeholder="e.g. QR-E1-P004" /></div>
            <Btn type="submit" loading={checking}>Check In</Btn>
          </form>
        </Card>
      </div>

      {history.length > 0 && (
        <Card className="p-5">
          <p className="font-bold text-[14px] mb-3">Recent scans</p>
          <div className="space-y-1.5">
            {history.map((h, i) => (
              <div key={i} className="flex items-center gap-3 py-2 border-b border-slate-100 last:border-0">
                {h.success ? <CheckCircle2 size={16} className="text-emerald-500 flex-shrink-0" /> : h.type === 'duplicate' ? <AlertTriangle size={16} className="text-amber-500 flex-shrink-0" /> : <XCircle size={16} className="text-rose-500 flex-shrink-0" />}
                <div className="flex-1"><p className="text-[12px] font-semibold">{h.registration?.name || h.qr}</p><p className="text-[10px] text-slate-400">{h.time.toLocaleTimeString()}</p></div>
              </div>
            ))}
          </div>
        </Card>
      )}
    </div>
  )
}

function ResultBanner({ result }) {
  if (result.success) {
    return (
      <div className="p-4 rounded-2xl border-2 flex items-center gap-3 border-emerald-300 bg-emerald-50">
        <CheckCircle2 size={24} className="text-emerald-600 flex-shrink-0" />
        <p className="text-[13px] font-semibold text-emerald-800">Attendance confirmed — {result.registration?.name}</p>
      </div>
    )
  }
  if (result.type === 'duplicate') {
    return (
      <div className="p-4 rounded-2xl border-2 flex items-center gap-3 border-amber-300 bg-amber-50">
        <AlertTriangle size={24} className="text-amber-600 flex-shrink-0" />
        <p className="text-[13px] font-semibold text-amber-800">Already checked in — {result.registration?.name}</p>
      </div>
    )
  }
  if (result.type === 'not_found') {
    return (
      <div className="p-4 rounded-2xl border-2 flex items-center gap-3 border-rose-300 bg-rose-50">
        <XCircle size={24} className="text-rose-600 flex-shrink-0" />
        <p className="text-[13px] font-semibold text-rose-800">QR code not recognized</p>
      </div>
    )
  }
  return (
    <div className="p-4 rounded-2xl border-2 flex items-center gap-3 border-rose-300 bg-rose-50">
      <XCircle size={24} className="text-rose-600 flex-shrink-0" />
      <p className="text-[13px] font-semibold text-rose-800">{result.message || 'Something went wrong'}</p>
    </div>
  )
}
