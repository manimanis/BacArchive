<template>
  <!-- ── SIDEBAR ───────────────────────────────────────────────────────────── -->
  <template v-if="part === 'sidebar'">
    <div class="arch-sidebar">
      <div class="arch-sidebar-hdr">
        <span class="arch-sidebar-title">Archive</span>
        <button class="btn-sm" @click="refreshAll" title="Rafraîchir">↺</button>
      </div>

      <p v-if="!settings.destBase" class="hint" style="padding:8px 10px">Aucun dossier configuré.</p>
      <p v-else-if="loading" class="hint" style="padding:8px 10px">⏳ Chargement…</p>
      <p v-else-if="archive.length === 0" class="hint" style="padding:8px 10px">Aucune séance trouvée.</p>

      <div v-else class="arch-accordion">
        <div v-for="se in archive" :key="se.name"
          :class="'acc-seance' + (isSeanceOpen(se) ? ' open' : '') + (hasDanger(se) ? ' danger' : '')">
          <div :class="'acc-seance-row' + (isSeanceOpen(se) ? ' active' : '')"
            @click="toggleSeance(se)">
            <span class="acc-arrow">{{ isSeanceOpen(se) ? '▾' : '▸' }}</span>
            <span class="acc-seance-name">{{ se.name }}</span>
            <div class="acc-seance-right">
              <span :class="'acc-count ' + (hasDanger(se) ? 'danger' : 'ok')">{{ sePresentCount(se) }}</span>
              <span class="acc-total">/{{ seTotalCount(se) }}</span>
              <span v-if="seAbsentCount(se) > 0" class="acc-absent-count">{{ seAbsentCount(se) }}A</span>
              <span v-if="hasDanger(se)" class="acc-warn">⚠</span>
            </div>
          </div>
          <div class="acc-labos-wrap" v-if="isSeanceOpen(se)">
            <div class="acc-labos">
              <div v-for="la in se.labos" :key="la.name"
                :class="'acc-labo-row' + (isLaboSelected(la, se) ? ' active' : '') + (hasLaboDanger(la) ? ' danger' : '')"
                @click="selectLabo(se, la)">
                <div class="acc-labo-top">
                  <span class="acc-labo-name">{{ la.name }}</span>
                  <div class="acc-labo-right">
                    <span :class="'acc-labo-count ' + (hasLaboDanger(la) ? 'danger' : 'ok')">{{ laboPresentCount(la) }}</span>
                    <span v-if="la.absent > 0" class="acc-labo-abs">{{ la.absent }}A</span>
                    <span class="acc-labo-total">/{{ la.candidates.length }}</span>
                    <span v-if="la.pdfFiles.length > 0" class="acc-doc acc-doc-p" title="Rapport PDF">P</span>
                    <span v-if="la.xlsxFiles.length > 0" class="acc-doc acc-doc-x" title="Grille Excel">X</span>
                  </div>
                </div>
                <div class="acc-labo-bar">
                  <div class="acc-labo-fill"
                    :style="{ width: laboPct(la) + '%', background: hasLaboDanger(la) ? '#e74c3c' : '#27ae60' }"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>

  <!-- ── CONTENU ───────────────────────────────────────────────────────────── -->
  <template v-if="part === 'content'">
    <template v-if="!settings.destBase">
      <div class="welcome">
        <div style="font-size:60px">📁</div>
        <h2>Mode Archive</h2>
        <p>Configurez un dossier de sauvegarde dans <strong>⚙ Paramètres</strong>.</p>
      </div>
    </template>

    <template v-else-if="loading">
      <p class="hint center" style="margin-top:40px">⏳ Chargement…</p>
    </template>

    <template v-else-if="selectedLabo">
      <!-- Labo View -->
      <div class="archive-detail">
        <div class="breadcrumb">
          <button class="bc-btn" @click="clearSelection">← Vue d'ensemble</button>
          <template v-if="selectedSeance">
            <span class="bc-sep">›</span>
            <button class="bc-btn" @click="selectedSeance = selectedSeance; selectedLabo = null">{{ selectedSeance.name }}</button>
          </template>
          <span class="bc-sep">›</span>
          <span class="bc-cur">{{ selectedLabo.name }}</span>
        </div>

        <div class="labo-actions">
          <button class="btn-outline" @click="showToast('Dossier : ' + selectedLabo.path, 'info')">📂 Dossier</button>
          <template v-if="selectedLabo.pdfFiles.length > 0">
            <div class="btn-with-regen">
              <button class="btn-file btn-pdf" @click="openFile(selectedLabo.path + '/' + selectedLabo.pdfFiles[0])"><Icons type="pdf" />Rapport PDF</button>
              <button class="btn-regen" :disabled="busy === 'pdf'" @click="regenPDF(selectedLabo)">{{ busy === 'pdf' ? '⏳' : '↺' }}</button>
            </div>
          </template>
          <button v-else class="btn-outline" :disabled="busy === 'pdf'" @click="regenPDF(selectedLabo, true)">
            {{ busy === 'pdf' ? '⏳ Génération…' : '📄 Générer rapport PDF' }}
          </button>
          <template v-if="selectedLabo.xlsxFiles.length > 0">
            <div class="btn-with-regen">
              <button class="btn-file btn-xls" @click="openFile(selectedLabo.path + '/' + selectedLabo.xlsxFiles[0])"><Icons type="xls" />Grille Excel</button>
              <button class="btn-regen" :disabled="busy === 'xlsx'" @click="regenExcel(selectedLabo)">{{ busy === 'xlsx' ? '⏳' : '↺' }}</button>
            </div>
          </template>
          <button v-else class="btn-outline" :disabled="busy === 'xlsx'" @click="regenExcel(selectedLabo, true)">
            {{ busy === 'xlsx' ? '⏳ Génération…' : '📊 Générer grille Excel' }}
          </button>
        </div>

        <Stats :items="laboStats" style="margin-top:8px" />

        <div class="tbl-wrap" style="margin-top:10px">
          <table class="tbl">
            <thead><tr>
              <th style="width:32px">#</th>
              <th style="width:88px;cursor:pointer" @click="toggleSort('num')">Nom Dossier{{ sortIcon('num') }}</th>
              <th style="width:50px;cursor:pointer" @click="toggleSort('files')">Files{{ sortIcon('files') }}</th>
              <th>Type / Nombre de Fichiers (top 6)</th>
              <th style="width:80px;cursor:pointer" @click="toggleSort('size')">Taille{{ sortIcon('size') }}</th>
              <th style="width:90px">Statut</th>
            </tr></thead>
            <tbody>
              <tr v-for="(c, i) in sortedCandidates" :key="c.candidateNumber"
                :class="i % 2 === 0 ? 'r-even' : 'r-odd'">
                <td class="tc">{{ i + 1 }}</td>
                <td class="num">{{ c.candidateNumber }}</td>
                <template v-if="c.nonConforme">
                  <td colspan="3" class="tc non-conforme">⚠ Dossier vide</td>
                </template>
                <template v-else-if="c.absent">
                  <td colspan="3" class="tc absent">Absent</td>
                </template>
                <template v-else>
                  <td class="tc">{{ c.fileCount }}</td>
                  <td class="exts">{{ (c.topExtensions || []).map(e => e.count + ' ' + e.ext).join(' • ') }}</td>
                  <td class="tr">{{ fmt(c.totalSize) }}</td>
                </template>
                <td class="tc">
                  <span v-if="c.nonConforme" class="note-nc">⚠ N/C</span>
                  <span v-else-if="c.absent" style="color:#e67e22">Absent</span>
                  <span v-else-if="c.fraud" class="note-fraud">⚠ Fraude</span>
                  <span v-else style="color:#27ae60;font-weight:700">✓</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <template v-else-if="selectedSeance">
      <!-- Séance View -->
      <div class="archive-detail">
        <div class="breadcrumb">
          <button class="bc-btn" @click="clearSelection">← Vue d'ensemble</button>
          <span class="bc-sep">›</span>
          <span class="bc-cur">{{ selectedSeance.name }}</span>
        </div>
        <Stats :items="seanceStatsItems" style="margin-bottom:8px" />
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr>
              <th style="width:120px">Labo</th>
              <th style="width:80px">Présents</th>
              <th style="width:80px">Absents</th>
              <th style="width:80px">Fraudes</th>
              <th style="width:70px">PDF</th>
              <th style="width:70px">Excel</th>
            </tr></thead>
            <tbody>
              <tr v-for="(la, i) in selectedSeance.labos" :key="la.name"
                :class="i % 2 === 0 ? 'r-even' : 'r-odd'"
                style="cursor:pointer" @click="selectedLabo = la">
                <td><strong>{{ la.name }}</strong></td>
                <td class="tc" style="color:#27ae60;font-weight:700">{{ la.candidates.length - la.absent }}</td>
                <td class="tc" :style="{ color: la.absent > 0 ? '#e67e22' : '#aaa' }">
                  {{ la.absent > 0 ? la.absent : '—' }}
                </td>
                <td class="tc" :style="{ color: la.fraud > 0 ? '#e74c3c' : '#aaa' }">
                  {{ la.fraud > 0 ? '⚠' + la.fraud : '—' }}
                </td>
                <td class="tc">
                  <button v-if="la.pdfFiles.length > 0" class="btn-icon-sm btn-pdf-sm" @click.stop="openFile(laboFilePath(la, la.pdfFiles[0]))">PDF</button>
                  <span v-else style="color:#ccc">—</span>
                </td>
                <td class="tc">
                  <button v-if="la.xlsxFiles.length > 0" class="btn-icon-sm btn-xls-sm" @click.stop="openFile(laboFilePath(la, la.xlsxFiles[0]))">XLS</button>
                  <span v-else style="color:#ccc">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <template v-else-if="archive.length === 0">
      <div class="welcome">
        <div style="font-size:50px">📭</div>
        <p>Aucune séance dans ce dossier.</p>
        <p style="font-size:11px;opacity:.6;margin-top:6px">Les séances récupérées apparaîtront ici.</p>
      </div>
    </template>

    <template v-else>
      <!-- Dashboard -->
      <div class="arch-dashboard">
        <div class="arch-dash-header">
          <div class="arch-dash-title">Vue d'ensemble</div>
          <div class="arch-dash-global">
            <span class="adg-item present">{{ globalPresent }} présents</span>
            <span class="adg-sep">•</span>
            <span class="adg-item">{{ globalTotal }} total</span>
            <span v-if="globalAbsent > 0" class="adg-sep">•</span>
            <span v-if="globalAbsent > 0" class="adg-item muted">{{ globalAbsent }} absent{{ globalAbsent > 1 ? 's' : '' }}</span>
            <span v-if="globalNC > 0" class="adg-sep">•</span>
            <span v-if="globalNC > 0" class="adg-item danger">⚠ {{ globalNC }} non-conforme{{ globalNC > 1 ? 's' : '' }}</span>
            <span v-if="globalFraud > 0" class="adg-sep">•</span>
            <span v-if="globalFraud > 0" class="adg-item danger">⚠ {{ globalFraud }} fraude{{ globalFraud > 1 ? 's' : '' }}</span>
            <span class="adg-sep">•</span>
            <span class="adg-item muted">{{ archive.length }} séance{{ archive.length > 1 ? 's' : '' }}</span>
          </div>
        </div>

        <div class="arch-seance-grid">
          <div v-for="seance in archive" :key="seance.name"
            :class="'arch-seance-card' + (seHasDanger(seance) ? ' card-danger' : '')">
            <div class="asc-header" @click="selectedSeance = seance; selectedLabo = null" style="cursor:pointer">
              <span class="asc-name">{{ seance.name }}</span>
              <div class="asc-summary">
                <span class="asc-present">{{ sePresentCount(seance) }}</span>
                <span class="asc-slash">/</span>
                <span class="asc-total">{{ seTotalCount(seance) }}</span>
                <span v-if="seAbsentCount(seance) > 0" class="asc-muted" style="margin-left:6px;color:#e67e22;font-weight:700;font-size:11px">{{ seAbsentCount(seance) }}A</span>
                <span v-if="seNCCount(seance) > 0" class="asc-danger" style="margin-left:4px;color:#e74c3c;font-weight:700;font-size:11px">⚠{{ seNCCount(seance) }}NC</span>
              </div>
            </div>
            <div class="asc-progress-bar">
              <div class="asc-progress-fill"
                :style="{ width: sePct(seance) + '%', background: seHasDanger(seance) ? '#e74c3c' : '#27ae60' }"></div>
            </div>
            <div class="asc-labos">
              <div v-for="labo in seance.labos" :key="labo.name"
                :class="'asc-labo-pill' + (laboHasDanger(labo) ? ' pill-danger' : '')"
                @click="selectedSeance = seance; selectedLabo = labo"
                :title="labo.name + ' — ' + (labo.candidates.length - labo.absent) + '/' + labo.candidates.length">
                <span class="pill-name">{{ labo.name.replace(/^Labo[-_]/i, 'L') }}</span>
                <span class="pill-count">{{ labo.candidates.length - labo.absent }}<span class="pill-total" v-if="labo.candidates.length !== (labo.candidates.length - labo.absent)">/{{ labo.candidates.length }}</span></span>
              </div>
            </div>
            <div class="asc-footer">
              <span class="asc-footer-info">{{ seance.labos.length }} labo{{ seance.labos.length > 1 ? 's' : '' }}</span>
              <button class="asc-detail-btn" @click="selectedSeance = seance; selectedLabo = null">Détail →</button>
            </div>
          </div>
        </div>
      </div>
    </template>
  </template>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { showToast } from '../composables/useToast.js'
import Stats from './Stats.vue'
import Icons from './Icons.vue'
import { buildLaboStats, buildSeanceStats } from '../statsHelpers.js'
import { fmt } from '../utils.js'
import { scanArchive as apiScanArchive, generatePDF as apiGeneratePDF, generateExcel as apiGenerateExcel } from '../api.js'

const props = defineProps({
  part: { type: String, required: true },
  settings: { type: Object, required: true },
  saveSetting: { type: Function, required: true },
  archiveState: { type: Object, required: true },
  updateArchiveState: { type: Function, required: true },
})

const sortCol = ref('num')
const sortDir = ref(1)

const archive = computed(() => props.archiveState.archive || [])
const loading = computed(() => props.archiveState.loading)
const selectedSeance = computed({
  get: () => props.archiveState.selSeance,
  set: (v) => props.updateArchiveState({ selSeance: v, selLabo: null }),
})
const selectedLabo = computed({
  get: () => props.archiveState.selLabo,
  set: (v) => props.updateArchiveState({ selLabo: v }),
})
const busy = computed(() => props.archiveState.busy)

const clearSelection = () => props.updateArchiveState({ selSeance: null, selLabo: null })

const isSeanceOpen = (se) => selectedSeance.value?.name === se.name
const isLaboSelected = (la, se) => selectedLabo.value?.name === la.name && isSeanceOpen(se)
const toggleSeance = (se) => {
  if (isSeanceOpen(se)) clearSelection()
  else props.updateArchiveState({ selSeance: se, selLabo: null })
}
const selectLabo = (se, la) => {
  if (isLaboSelected(la, se)) props.updateArchiveState({ selSeance: se, selLabo: null })
  else props.updateArchiveState({ selSeance: se, selLabo: la })
}

const sePresentCount = (se) => se.labos.reduce((s, l) => s + l.candidates.length - l.absent, 0)
const seTotalCount = (se) => se.labos.reduce((s, l) => s + l.candidates.length, 0)
const seAbsentCount = (se) => se.labos.reduce((s, l) => s + l.absent, 0)
const seNCCount = (se) => se.labos.reduce((s, l) => s + l.candidates.filter(c => c.nonConforme).length, 0)
const hasDanger = (se) => seNCCount(se) > 0 || se.labos.some(l => l.fraud > 0)
const seHasDanger = (se) => hasDanger(se)
const sePct = (se) => { const t = seTotalCount(se); return t > 0 ? Math.round(sePresentCount(se) / t * 100) : 0 }

const laboPresentCount = (la) => la.candidates.length - la.absent
const laboHasDanger = (la) => la.candidates.some(c => c.nonConforme) || la.fraud > 0
const laboPct = (la) => { const t = la.candidates.length; return t > 0 ? Math.round(laboPresentCount(la) / t * 100) : 0 }
const laboFilePath = (la, file) => la.path + '/' + file

const hasLaboDanger = (la) => la.candidates.some(c => c.nonConforme) || la.fraud > 0

const laboStats = computed(() => {
  if (!selectedLabo.value) return []
  return buildLaboStats(selectedLabo.value.candidates)
})

const seanceStatsItems = computed(() => {
  if (!selectedSeance.value) return []
  return buildSeanceStats(selectedSeance.value)
})

const sortedCandidates = computed(() => {
  if (!selectedLabo.value) return []
  const cands = [...(selectedLabo.value.candidates || [])]
  return cands.sort((a, b) => {
    if (sortCol.value === 'num') return sortDir.value * a.candidateNumber.localeCompare(b.candidateNumber)
    if (sortCol.value === 'files') return sortDir.value * ((a.fileCount || 0) - (b.fileCount || 0))
    if (sortCol.value === 'size') return sortDir.value * ((a.totalSize || 0) - (b.totalSize || 0))
    return 0
  })
})

const toggleSort = (col) => {
  if (sortCol.value === col) sortDir.value = -sortDir.value
  else { sortCol.value = col; sortDir.value = 1 }
}
const sortIcon = (col) => sortCol.value === col ? (sortDir.value === 1 ? ' ▲' : ' ▼') : ''

const globalPresent = computed(() => archive.value.reduce((s, se) => s + se.labos.reduce((ss, l) => ss + l.candidates.length - l.absent, 0), 0))
const globalTotal = computed(() => archive.value.reduce((s, se) => s + se.labos.reduce((ss, l) => ss + l.candidates.length, 0), 0))
const globalAbsent = computed(() => archive.value.reduce((s, se) => s + se.labos.reduce((ss, l) => ss + l.absent, 0), 0))
const globalNC = computed(() => archive.value.reduce((s, se) => s + se.labos.reduce((ss, l) => ss + l.candidates.filter(c => c.nonConforme).length, 0), 0))
const globalFraud = computed(() => archive.value.reduce((s, se) => s + se.labos.reduce((ss, l) => ss + l.fraud, 0), 0))

const loadArchive = async () => {
  props.updateArchiveState({ loading: true, error: '', selSeance: null, selLabo: null })
  try {
    const r = await apiScanArchive(props.settings.destBase)
    if (r?.success) {
      props.updateArchiveState({ archive: r.data || [], loading: false })
    } else {
      props.updateArchiveState({ error: r?.error || 'Erreur lecture archive', loading: false })
    }
  } catch (e) {
    props.updateArchiveState({ error: e.message, loading: false })
  }
}

const refreshAll = () => loadArchive()

const regenPDF = async (labo, skipConfirm = false) => {
  const ncCount = (labo.candidates || []).filter(c => c.nonConforme).length
  if (ncCount > 0) {
    showToast(`⚠ ${ncCount} dossier(s) non-conforme(s) — réglez-les avant de générer le rapport PDF.`, 'err', 6000)
    return
  }
  if (!skipConfirm && labo.pdfFiles.length > 0) {
    if (!confirm('Un rapport PDF existe déjà. Voulez-vous le regénérer ?')) return
  }
  props.updateArchiveState({ busy: 'pdf', error: '' })
  const seance = selectedSeance.value || archive.value.find(s => s.labos.some(l => l.name === labo.name))
  const buildFolders = (lab) => lab.candidates.map(c => ({
    candidateNumber: c.candidateNumber, rawNumber: c.rawNumber || c.candidateNumber,
    absent: c.absent, fraud: c.fraud, fileCount: c.fileCount || 0,
    totalSize: c.totalSize || 0, topExtensions: c.topExtensions || [],
    sizeAlert: (c.totalSize || 0) > 5 * 1024 * 1024,
  }))
  try {
    const r = await apiGeneratePDF({
      matiere: props.settings.matiere, section: props.settings.section, annee: props.settings.annee,
      labo: labo.labo, seance: seance?.seance || 1, lycee: props.settings.lycee,
      folders: buildFolders(labo), destPath: labo.path, copyResults: {}
    }, labo.path)
    props.updateArchiveState({ busy: '' })
    if (r?.success) { showToast('✓ Rapport PDF généré', 'ok'); refreshAndSync() }
    else props.updateArchiveState({ error: 'Erreur PDF : ' + r?.error })
  } catch (e) {
    props.updateArchiveState({ busy: '', error: 'Erreur PDF : ' + e.message })
  }
}

const regenExcel = async (labo, skipConfirm = false) => {
  const ncCount = (labo.candidates || []).filter(c => c.nonConforme).length
  if (ncCount > 0) {
    showToast(`⚠ ${ncCount} dossier(s) non-conforme(s) — réglez-les avant de générer la grille Excel.`, 'err', 6000)
    return
  }
  if (!skipConfirm && labo.xlsxFiles.length > 0) {
    if (!confirm('Une grille Excel existe déjà. Voulez-vous la regénérer ?')) return
  }
  props.updateArchiveState({ busy: 'xlsx', error: '' })
  const seance = selectedSeance.value || archive.value.find(s => s.labos.some(l => l.name === labo.name))
  const buildFolders = (lab) => lab.candidates.map(c => ({
    candidateNumber: c.candidateNumber, rawNumber: c.rawNumber || c.candidateNumber,
    absent: c.absent, fraud: c.fraud, fileCount: c.fileCount || 0,
    totalSize: c.totalSize || 0, topExtensions: c.topExtensions || [],
    sizeAlert: (c.totalSize || 0) > 5 * 1024 * 1024,
  }))
  try {
    const r = await apiGenerateExcel({
      matiere: props.settings.matiere, section: props.settings.section, annee: props.settings.annee,
      labo: labo.labo, seance: seance?.seance || 1, lycee: props.settings.lycee,
      folders: buildFolders(labo), nbQuestions: props.settings.nbQuestions || 15
    }, labo.path)
    props.updateArchiveState({ busy: '' })
    if (r?.success) { showToast('✓ Grille Excel générée', 'ok'); refreshAndSync() }
    else props.updateArchiveState({ error: 'Erreur Excel : ' + r?.error })
  } catch (e) {
    props.updateArchiveState({ busy: '', error: 'Erreur Excel : ' + e.message })
  }
}

const refreshAndSync = async () => {
  const r = await apiScanArchive(props.settings.destBase)
  if (!r?.success) return
  const na = r.data || []
  if (selectedSeance.value) {
    const newSe = na.find(s => s.name === selectedSeance.value.name)
    if (newSe) {
      const newLa = selectedLabo.value ? newSe.labos.find(l => l.name === selectedLabo.value.name) : null
      props.updateArchiveState({ archive: na, selSeance: newSe, selLabo: newLa || selectedLabo.value })
      return
    }
  }
  props.updateArchiveState({ archive: na })
}

const openFile = (p) => {
  window.open('./api/index.php?action=open-file&path=' + encodeURIComponent(p), '_blank')
}

onMounted(() => {
  if (props.settings.destBase) loadArchive()
})

watch(() => props.settings.destBase, () => {
  if (props.settings.destBase) loadArchive()
})
</script>