import { useState } from 'react'
import { Plus, Trash2, ClipboardList } from 'lucide-react'
import { Card, Btn, Input } from '../../components/ui'
import { useTaskTemplates } from '../../hooks/useApi'
import { createTaskTemplate } from '../../api/resources'
import { useApp } from '../../context/AppContext'

export default function Templates() {
  const { addToast } = useApp()
  const { data, loading, refetch } = useTaskTemplates()
  const templates = data || []

  const [name, setName] = useState('')
  const [tasks, setTasks] = useState([''])
  const [saving, setSaving] = useState(false)

  const updateTask = (i, v) => setTasks(t => t.map((x, idx) => idx === i ? v : x))
  const addTaskRow = () => setTasks(t => [...t, ''])
  const removeTaskRow = (i) => setTasks(t => t.filter((_, idx) => idx !== i))

  const handleSave = async () => {
    const cleanTasks = tasks.map(t => t.trim()).filter(Boolean)
    if (!name.trim() || cleanTasks.length === 0) {
      addToast('Give the template a name and at least one task', 'error')
      return
    }
    setSaving(true)
    try {
      await createTaskTemplate({ name: name.trim(), tasks: cleanTasks })
      addToast('Template saved!', 'success')
      setName(''); setTasks([''])
      refetch()
    } catch (err) {
      addToast(err.message || 'Failed to save template', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-5">
      <div><h1 className="text-2xl font-extrabold">Checklists</h1><p className="text-[13px] text-slate-500">Reusable task templates applied when creating an event</p></div>

      <div className="grid lg:grid-cols-2 gap-5">
        <Card className="p-5">
          <p className="font-bold text-[14px] mb-4">New template</p>
          <div className="space-y-3">
            <Input label="Template name" value={name} onChange={e => setName(e.target.value)} placeholder="e.g. Conference Standard" />
            <div>
              <label className="block text-[12px] font-semibold text-slate-600 mb-1.5">Tasks</label>
              <div className="space-y-2">
                {tasks.map((t, i) => (
                  <div key={i} className="flex gap-2">
                    <div className="flex-1"><Input value={t} onChange={e => updateTask(i, e.target.value)} placeholder="e.g. Book venue" /></div>
                    <button type="button" onClick={() => removeTaskRow(i)} className="p-2.5 rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-50"><Trash2 size={15} /></button>
                  </div>
                ))}
              </div>
              <Btn variant="ghost" size="sm" icon={Plus} className="mt-2" onClick={addTaskRow}>Add task</Btn>
            </div>
            <Btn variant="accent" full onClick={handleSave} loading={saving}>Save Template</Btn>
          </div>
        </Card>

        <Card className="p-5">
          <p className="font-bold text-[14px] mb-4">Existing templates</p>
          {loading ? (
            <p className="text-center text-slate-400 text-[12px] py-8">Loading…</p>
          ) : templates.length === 0 ? (
            <div className="text-center py-8">
              <ClipboardList size={28} className="mx-auto text-slate-300 mb-2" />
              <p className="text-[12px] text-slate-400">No templates yet.</p>
            </div>
          ) : (
            <div className="space-y-3">
              {templates.map(t => (
                <div key={t.id} className="p-3 rounded-xl border border-slate-200">
                  <p className="text-[13px] font-bold text-slate-800 mb-1.5">{t.name}</p>
                  <ul className="space-y-1">
                    {(t.tasks || []).map((task, i) => <li key={i} className="text-[12px] text-slate-500 flex items-center gap-1.5">· {task}</li>)}
                  </ul>
                </div>
              ))}
            </div>
          )}
        </Card>
      </div>
    </div>
  )
}
