/**
 * Stats helper functions (exported separately from <script setup>)
 */

export function buildLaboStats(candidates) {
  const absent = (candidates || []).filter(c => c.absent && !c.nonConforme).length
  const nc = (candidates || []).filter(c => c.nonConforme).length
  const fraud = (candidates || []).filter(c => c.fraud).length
  const present = (candidates || []).filter(c => !c.absent).length
  const total = (candidates || []).length
  const items = [
    { label: 'Présents', value: present, color: present > 0 ? 'ok' : 'neutral' },
    { label: 'Total', value: total, color: 'neutral' },
  ]
  if (absent > 0) items.push({ label: 'Absents', value: absent, color: 'warn' })
  if (nc > 0) items.push({ label: 'Non-conformes', value: nc, color: 'danger' })
  if (fraud > 0) items.push({ label: 'Fraudes', value: fraud, color: 'danger' })
  return items
}

export function buildSeanceStats(seance) {
  const labos = seance.labos || []
  const total = labos.reduce((s, l) => s + (l.candidates || []).length, 0)
  const absent = labos.reduce((s, l) => s + (l.absent || 0), 0)
  const nc = labos.reduce((s, l) => s + (l.candidates || []).filter(c => c.nonConforme).length, 0)
  const fraud = labos.reduce((s, l) => s + (l.fraud || 0), 0)
  const present = total - absent
  const items = [
    { label: 'Présents', value: present, color: present > 0 ? 'ok' : 'neutral' },
    { label: 'Total', value: total, color: 'neutral' },
    { label: labos.length > 1 ? 'Labos' : 'Labo', value: labos.length, color: 'muted' },
  ]
  if (absent > 0) items.push({ label: 'Absents', value: absent, color: 'warn' })
  if (nc > 0) items.push({ label: 'Non-conformes', value: nc, color: 'danger' })
  if (fraud > 0) items.push({ label: 'Fraudes', value: fraud, color: 'danger' })
  return items
}