<template>
  <!-- ── SIDEBAR ──────────────────────────────────────────────────────────── -->
  <template v-if="part === 'sidebar'">
    <!-- Clés USB -->
    <div class="card">
      <div class="card-hdr">
        <span>Clé USB</span>
        <button class="btn-sm" @click="refreshAll" title="Rafraîchir tout">↺</button>
      </div>
      <template v-if="usbDrives.length === 0">
        <p class="hint">Aucune clé USB détectée.</p>
      </template>
      <template v-else>
        <div v-for="d in usbDrives" :key="d.path"
          class="drive" :class="{ active: session.selDrive?.path === d.path }"
          @click="!copying && selectDrive(d)">
          <span style="font-size:20px;flex-shrink:0">🔌</span>
          <div style="min-width:0;flex:1">
            <div class="drive-name">{{ d.name }}</div>
            <div class="drive-path">{{ d.path }}</div>
            <div v-if="d.size > 0" class="drive-path">{{ fmt(d.available) }} / {{ fmt(d.size) }}</div>
          </div>
        </div>
      </template>
    </div>

    <!-- Disques locaux -->
    <div class="card">
      <div class="card-hdr">
        <span>Disque</span>
      </div>
      <template v-if="diskDrives.length === 0">
        <p class="hint">Aucun disque local détecté.</p>
      </template>
      <template v-else>
        <div v-for="d in diskDrives" :key="d.path"
          class="drive" :class="{ active: session.selDrive?.path === d.path }"
          @click="!copying && selectDrive(d)">
          <span style="font-size:20px;flex-shrink:0">🖴</span>
          <div style="min-width:0;flex:1">
            <div class="drive-name">{{ d.name }}</div>
            <div class="drive-path">{{ d.path }}</div>
            <div v-if="d.size > 0" class="drive-path">{{ fmt(d.available) }} / {{ fmt(d.size) }}</div>
          </div>
        </div>
      </template>
    </div>

    <!-- Sous-dossier picker -->
    <div v-if="session.selDrive && session.subfolders.length > 0 && !session.copyDone" class="card">
      <div class="card-hdr"><span>📂 Dossier source</span></div>
      <div class="subfolder-picker">
        <div class="subfolder-picker-label">Dossier :</div>
        <div class="subfolder-list">
          <div v-if="session.rootCount > 0"
            class="subfolder-item subfolder-root" :class="{ active: session.selSubfolder === '' }"
            @click="session.selSubfolder !== '' && handleSubfolderSelect('')">
            <span class="subfolder-icon">🗂</span>
            <span class="subfolder-name">Racine de la clé</span>
            <span class="subfolder-badge" :class="session.selSubfolder === '' ? '' : 'badge-dim'">
              {{ session.selSubfolder === '' ? session.folders.length : session.rootCount }}
            </span>
          </div>
          <div class="subfolder-children">
            <div v-for="sub in session.subfolders" :key="sub.name"
              class="subfolder-item subfolder-child" :class="{ active: session.selSubfolder === sub.name }"
              @click="handleSubfolderSelect(sub.name)">
              <span class="subfolder-icon">📁</span>
              <span class="subfolder-name">{{ sub.name }}</span>
              <span class="subfolder-badge" :class="session.selSubfolder === sub.name ? '' : 'badge-dim'">
                {{ session.selSubfolder === sub.name ? session.folders.length : sub.count }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Séance / Labo : sélection en 3 étapes -->
    <div class="card">
      <div class="card-hdr"><span>Séance / Labo</span></div>

      <p v-if="examLoading" class="hint">⏳ Chargement configuration…</p>
      <p v-else-if="jours.length === 0" class="hint">
        Aucune journée configurée.<br>
        <small>Configurer dans ⚙ Paramètres → Examens</small>
      </p>

      <template v-else>
        <!-- Étape 1 : Journée -->
        <div class="rec-step">
          <div class="rec-step-hdr">
            <span class="rec-step-badge">1</span> Journée
          </div>
          <div class="rec-step-list">
            <button v-for="(jour, jIdx) in jours" :key="jIdx"
              :class="'rec-step-item' + (selectedJourIdx === jIdx ? ' active' : '')"
              @click="selectJour(jIdx)">
              <span class="rec-step-num">J{{ jIdx + 1 }}</span>
              <span class="rec-step-meta">{{ formatJourDate(jour) }}</span>
            </button>
          </div>
        </div>

        <!-- Étape 2 : Séance -->
        <div v-if="selectedJourIdx !== null" class="rec-step">
          <div class="rec-step-hdr">
            <span class="rec-step-badge">2</span> Séance
          </div>
          <div class="rec-step-list">
            <button v-for="(se, sIdx) in currentSeances" :key="sIdx"
              :class="'rec-step-item' + (selectedSeanceIdx === sIdx ? ' active' : '')"
              @click="selectSeance(sIdx)">
              <span class="rec-step-num">S{{ sIdx + 1 }}</span>
              <span class="rec-step-meta">{{ se.date_deb }} · {{ se.duree }}min</span>
            </button>
            <p v-if="currentSeances.length === 0" class="hint" style="padding:6px 4px;font-size:11px">
              Aucune séance configurée pour ce jour.
            </p>
          </div>
        </div>

        <!-- Étape 3 : Labo -->
        <div v-if="selectedSeanceIdx !== null" class="rec-step">
          <div class="rec-step-hdr">
            <span class="rec-step-badge">3</span> Labo
          </div>
          <div class="rec-step-list rec-step-list-grid">
            <button v-for="laboNum in laboRange" :key="laboNum"
              :class="'rec-step-item rec-step-labo' + (settings.labo === laboNum ? ' active' : '')"
              @click="selectLabo(laboNum)">
              L{{ String(laboNum).padStart(2, '0') }}
            </button>
          </div>
        </div>
      </template>

      <div v-if="currentDestPath" class="dest-preview" style="margin-top:8px">→ {{ currentDestPath }}</div>
      <div v-else class="dest-missing">⚠ Configurez un dossier dans ⚙ Paramètres</div>
    </div>

    <!-- Copie -->
    <div v-if="session.selDrive && session.folders.length > 0 && !session.copyDone" class="card">
      <button class="btn-primary full"
        :disabled="!settings.destBase || copying" @click="startCopy">
        {{ copying ? `⏳ Copie… ${progress?.percentage || 0}%` : '▶ Copier & Vérifier (MD5)' }}
      </button>
      <div v-if="copying && progress" class="prog-wrap">
        <div class="prog-bar"><div class="prog-fill" :style="{ width: progress.percentage + '%' }"></div></div>
        <div class="prog-lbl">{{ progress.copied }}/{{ progress.total }} — {{ progress.current }}</div>
      </div>
    </div>

    <!-- Scan en cours -->
    <div v-if="scanning" class="card">
      <div style="display:flex;align-items:center;gap:8px;padding:4px 0">
        <div class="scan-spinner-sm"></div>
        <span style="font-size:11px;color:#1a3a5c;font-weight:600">Lecture en cours…</span>
      </div>
    </div>

    <!-- Post-copie -->
    <div v-if="session.copyDone" class="card">
      <p v-if="genBusy" class="hint" style="margin-bottom:6px">⏳ Génération…</p>
      <div class="action-grp">
        <button v-if="session.pdfPath" class="btn-file btn-pdf full" @click="openFile(session.pdfPath)">
          <Icons type="pdf" />Rapport PDF
        </button>
        <button v-if="session.xlsxPath" class="btn-file btn-xls full" @click="openFile(session.xlsxPath)">
          <Icons type="xls" />Grille Excel
        </button>
        <button v-if="session.destPath" class="btn-outline full" @click="openFolder(session.destPath)">📂 Dossier copié</button>
        <button class="btn-outline full" @click="reset">↩ Nouvelle séance</button>
      </div>
    </div>
  </template>

  <!-- ── CONTENU ──────────────────────────────────────────────────────────── -->
  <template v-if="part === 'content'">
    <div v-if="error" class="alert-err">
      <span style="white-space:pre-wrap">{{ error }}</span>
      <button @click="error = ''">✕</button>
    </div>

    <template v-if="session.selDrive">
      <!-- Indicateur source -->
      <div v-if="session.selDrive && session.subfolders.length > 0" class="scan-source-bar">
        <span>📂 Source :</span>
        <strong>{{ session.selSubfolder ? session.selDrive.path + '\\' + session.selSubfolder : session.selDrive.path + ' (racine)' }}</strong>
      </div>

      <Stats :items="statsItems" />

      <!-- Scan en cours -->
      <div v-if="scanning" class="scan-wait">
        <div class="scan-spinner"></div>
        <p style="font-weight:600;color:#1a3a5c;margin:12px 0 4px;font-size:14px">Lecture du lecteur</p>
        <p style="font-size:12px;color:#64748b;margin:0">Analyse des dossiers candidats en cours…</p>
      </div>

      <!-- Choix dossier source -->
      <div v-else-if="session.subfolders.length > 0 && !session.selSubfolder && session.folders.length === 0" class="welcome" style="padding-top:40px">
        <div style="font-size:48px">📂</div>
        <p style="margin-top:12px;color:#555;font-weight:600">Choisissez le dossier source</p>
        <p style="font-size:11px;opacity:.6;margin-top:6px">Sélectionnez le dossier correspondant à la séance en cours dans le panneau gauche.</p>
      </div>

      <!-- Aucun dossier -->
      <p v-else-if="session.folders.length === 0" class="hint center">Aucun dossier candidat trouvé (format NNNNNN).</p>

      <!-- Tableau -->
      <div v-else class="tbl-wrap">
        <table class="tbl">
          <thead><tr>
            <th style="width:32px">#</th>
            <th style="width:80px">Nom Dossier</th>
            <th style="width:46px">Files</th>
            <th>Nombre de Fichiers / Type (top 6)</th>
            <th style="width:80px">Taille</th>
            <th style="width:100px">Remarques</th>
          </tr></thead>
          <tbody>
            <tr v-for="(f, i) in session.folders" :key="f.candidateNumber"
              :class="f.sizeAlert ? 'r-alert' : (i % 2 === 0 ? 'r-even' : 'r-odd')">
              <td class="tc">{{ i + 1 }}</td>
              <td class="num">{{ f.candidateNumber }}</td>
              <template v-if="f.nonConforme">
                <td colspan="3" class="tc non-conforme">⚠ Dossier vide</td>
              </template>
              <template v-else-if="f.absent">
                <td colspan="3" class="tc absent">Absent</td>
              </template>
              <template v-else>
                <td class="tc">{{ f.fileCount }}</td>
                <td class="exts">{{ (f.topExtensions || []).map(e => e.count + ' ' + e.ext).join(' • ') }}</td>
                <td class="tr" :class="{ 'sz-alert': f.sizeAlert }">{{ fmt(f.totalSize) }}</td>
              </template>
              <td :class="'note' + (f.fraud ? ' note-fraud' : f.nonConforme ? ' note-nc' : '')">
                {{ f.fraud ? '⚠ Fraude' : f.nonConforme ? '⚠ N/C' : '' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Welcome -->
    <div v-else class="welcome">
      <div style="font-size:60px">💾</div>
      <h2>Mode Récupération</h2>
      <p>Branchez une clé USB pour démarrer</p>
      <p style="font-size:11px;opacity:.6;margin-top:6px">Détection et chargement automatiques dès le branchement</p>
    </div>
  </template>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { showToast } from '../composables/useToast.js'
import Stats from './Stats.vue'
import Icons from './Icons.vue'
import { fmt, pad2, SEANCES, LABOS } from '../utils.js'
import {
  readBacConfig as apiReadBacConfig,
  listDriveSubfolders as apiListDriveSubfolders,
  scanFolders as apiScanFolders,
  copyFiles as apiCopyFiles,
  generatePDF as apiGeneratePDF,
  generateExcel as apiGenerateExcel,
  scanArchive as apiScanArchive,
  updateBacLabo as apiUpdateBacLabo,
  loadExamConfig as apiLoadExamConfig,
} from '../api.js'

const props = defineProps({
  part: { type: String, required: true },
  settings: { type: Object, required: true },
  saveSetting: { type: Function, required: true },
  drives: { type: Array, default: () => [] },
  refreshDrives: { type: Function, required: true },
  session: { type: Object, required: true },
  updateSession: { type: Function, required: true },
  resetSession: { type: Function, required: true },
})

const scanning = ref(false)
const copying = ref(false)
const progress = ref(null)
const error = ref('')
const genBusy = ref(false)

// ── Configuration des examens (sélection 3 étapes) ──
const examConfig = ref({ nbre_jours: 0, jours: [] })
const examLoading = ref(false)
const selectedJourIdx = ref(null)
const selectedSeanceIdx = ref(null)
const laboRange = Array.from({ length: 10 }, (_, i) => i + 1)

const loadExamConfigData = async () => {
  examLoading.value = true
  try {
    const r = await apiLoadExamConfig()
    if (r?.success && r.data) {
      examConfig.value = { nbre_jours: r.data.nbre_jours || 0, jours: r.data.jours || [] }
    }
  } catch (e) {
    console.error('Erreur chargement config examens:', e)
  } finally {
    examLoading.value = false
  }
}

const jours = computed(() => examConfig.value.jours || [])

const currentSeances = computed(() => {
  if (selectedJourIdx.value === null) return []
  const jour = jours.value[selectedJourIdx.value]
  if (!jour || !jour.examens || jour.examens.length === 0) return []
  const all = []
  for (const exam of jour.examens) {
    if (exam.seances) all.push(...exam.seances)
  }
  return all
})

const formatJourDate = (jour) => {
  if (!jour || !jour.examens || jour.examens.length === 0) return '—'
  const date = jour.examens[0].date
  if (!date) return '—'
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(date)
  if (!m) return '—'
  return `${m[3]}/${m[2]}${m[1] ? '/' + m[1].slice(2) : ''}`
}

const selectJour = (idx) => {
  selectedJourIdx.value = idx
  selectedSeanceIdx.value = null
}

const selectSeance = (idx) => {
  selectedSeanceIdx.value = idx
  // Synchroniser avec settings.seance
  props.saveSetting({ seance: idx + 1 })
}

const selectLabo = (num) => {
  handleLaboChange(num)
}

const currentDestPath = computed(() =>
  props.settings.destBase
    ? `${props.settings.destBase}\\Séance-${pad2(props.settings.seance)}\\Labo-${pad2(props.settings.labo)}`
    : ''
)

// Clés USB (type 2 = Removable Disk)
const usbDrives = computed(() =>
  (props.drives || []).filter(d => d.type === 2)
)

// Disques locaux (type 3 = Local Disk)
const diskDrives = computed(() =>
  (props.drives || []).filter(d => d.type === 3)
)

const statsItems = computed(() => {
  const folders = props.session.folders || []
  const { nonConf, absent, present, fraud } = folders.reduce((a, f) => {
    if (f.nonConforme) a.nonConf++
    if (f.absent && !f.nonConforme) a.absent++
    if (!f.absent && !f.nonConforme) a.present++
    if (f.fraud) a.fraud++
    return a
  }, { nonConf: 0, absent: 0, present: 0, fraud: 0 })

  const items = [
    { label: 'Présents', value: present, color: present > 0 ? 'ok' : 'neutral' },
    { label: 'Total', value: folders.length, color: 'neutral' },
  ]
  if (absent > 0) items.push({ label: 'Absents', value: absent, color: 'warn' })
  if (nonConf > 0) items.push({ label: 'Non-conformes', value: nonConf, color: 'danger' })
  if (fraud > 0) items.push({ label: 'Fraudes', value: fraud, color: 'danger' })
  return items
})

const selectDrive = async (drive) => {
  error.value = ''
  scanning.value = true
  props.updateSession({ subfolders: [], selSubfolder: '' })
  props.updateSession({ selDrive: drive, folders: [], copyDone: false, pdfPath: '', xlsxPath: '',
    dupWarning: null, copyBlocked: false, copyBlockedType: null, dupInfo: null })

  try {
    const cfg = await apiReadBacConfig(drive.path)
    if (cfg?.success && cfg.data?.Labo) {
      const iniLabo = parseInt(cfg.data.Labo) || props.settings.labo
      if (iniLabo !== props.settings.labo) await props.saveSetting({ labo: iniLabo })
    }

    const subR = await apiListDriveSubfolders(drive.path)
    const { subs = [], rootCount = 0 } = subR?.data || {}
    props.updateSession({ subfolders: subs, rootCount })

    await doScan(drive, '')

    if (rootCount === 0 && subs.length > 0) {
      const first = subs[0].name
      props.updateSession({ selSubfolder: first })
      await doScan(drive, first)
    }
  } catch (e) {
    scanning.value = false
    error.value = 'Erreur : ' + e.message
  }
}

const doScan = async (drive, sub) => {
  const sp = sub ? `${drive.path}\\${sub}` : drive.path
  props.updateSession({ folders: [], copyDone: false, pdfPath: '', xlsxPath: '',
    dupWarning: null, copyBlocked: false, copyBlockedType: null, dupInfo: null })
  scanning.value = true

  try {
    const r = await apiScanFolders(sp)
    if (r?.success) {
      const fds = r.data || []
      props.updateSession({ folders: fds })
      scanning.value = false
      if (fds.length === 0) {
        showToast('Aucun dossier candidat trouvé.', 'info')
      } else {
        showToast(`${fds.length} dossier(s) candidat(s) trouvé(s).`, 'ok')
      }
    } else {
      scanning.value = false
      error.value = 'Erreur scan : ' + r?.error
    }
  } catch (e) {
    scanning.value = false
    error.value = 'Erreur scan : ' + e.message
  }
}

const handleSubfolderSelect = async (sub) => {
  props.updateSession({ selSubfolder: sub })
  error.value = ''
  props.updateSession({ folders: [], copyDone: false, pdfPath: '', xlsxPath: '',
    dupWarning: null, copyBlocked: false, copyBlockedType: null, dupInfo: null })
  if (props.session.selDrive) await doScan(props.session.selDrive, sub)
}

const handleSeanceChange = (val) => {
  props.saveSetting({ seance: val })
  if (props.session.copyBlocked && props.session.copyBlockedType === 'dest_exists')
    props.updateSession({ copyBlocked: false, dupWarning: null, copyBlockedType: null, dupInfo: null })
}

const handleLaboChange = async (newLabo) => {
  await props.saveSetting({ labo: newLabo })
  if (props.session.copyBlocked && props.session.copyBlockedType === 'dest_exists')
    props.updateSession({ copyBlocked: false, dupWarning: null, copyBlockedType: null, dupInfo: null })
  if (props.session.selDrive) {
    apiUpdateBacLabo(props.session.selDrive.path, newLabo)
  }
}

const startCopy = async () => {
  if (!props.settings.destBase) { error.value = 'Aucun dossier de sauvegarde. Cliquez sur ⚙ Paramètres.'; return }
  if (!props.session.folders.length) { error.value = 'Aucun dossier candidat.'; return }
  if (props.session.copyBlocked) {
    showToast('⛔ Copie bloquée — vérifiez le numéro de Séance/Labo.', 'err', 10000)
    return
  }

  const ncFolders = props.session.folders.filter(f => f.nonConforme)
  if (ncFolders.length > 0) {
    const nums = ncFolders.slice(0, 6).map(f => f.candidateNumber).join(', ')
    const more = ncFolders.length > 6 ? ` … +${ncFolders.length - 6} autre(s)` : ''
    showToast(`⛔ Copie bloquée — ${ncFolders.length} dossier(s) non-conforme(s)\nCandidats : ${nums}${more}\nUn dossier vide doit contenir soit des fichiers de travail, soit un sous-dossier "Absent" avec une remarque.`, 'err', 14000)
    return
  }

  error.value = ''
  copying.value = true
  progress.value = { copied: 0, total: 0, percentage: 0 }

  try {
    const r = await apiCopyFiles(props.session.folders, currentDestPath.value)
    copying.value = false

    if (!r?.success) {
      const errMsg = r?.error || 'Erreur inconnue'
      showToast(`⛔ Échec de la copie\n${errMsg}`, 'err', 14000)
      error.value = "Copie échouée — voir le message d'erreur."
      return
    }

    props.updateSession({ copyDone: true, destPath: currentDestPath.value,
      dupWarning: null, copyBlocked: false, copyBlockedType: null, dupInfo: null })

    genBusy.value = true
    showToast('✓ Copie vérifiée (MD5) — Génération des documents…', 'info', 12000)

    const pdfR = await apiGeneratePDF({
      matiere: props.settings.matiere, section: props.settings.section,
      annee: props.settings.annee, labo: props.settings.labo, seance: props.settings.seance,
      lycee: props.settings.lycee, folders: props.session.folders, destPath: currentDestPath.value,
      copyResults: r.data || {}
    }, currentDestPath.value)
    if (pdfR?.success) props.updateSession({ pdfPath: pdfR.data.pdfPath })

    const xlsR = await apiGenerateExcel({
      matiere: props.settings.matiere, section: props.settings.section,
      annee: props.settings.annee, labo: props.settings.labo, seance: props.settings.seance,
      lycee: props.settings.lycee, folders: props.session.folders, nbQuestions: props.settings.nbQuestions || 15
    }, currentDestPath.value)
    if (xlsR?.success) props.updateSession({ xlsxPath: xlsR.data.xlsxPath })
    else error.value = 'Erreur Excel : ' + xlsR?.error

    genBusy.value = false
    showToast('✓ Copie vérifiée (MD5) • Rapport PDF et Grille Excel générés', 'ok', 8000)
  } catch (e) {
    copying.value = false
    showToast('⛔ Erreur : ' + e.message, 'err', 14000)
  }
}

const refreshAll = async () => {
  props.refreshDrives()
  if (props.session.selDrive && !props.session.copyDone) {
    await selectDrive(props.session.selDrive)
  }
}

const openFile = (p) => {
  if (p) window.open('./api/index.php?action=open-file&path=' + encodeURIComponent(p), '_blank')
}

const openFolder = (p) => {
  // In web mode, we can't directly open folders
  showToast('Dossier : ' + p, 'info')
}

const reset = () => {
  props.resetSession()
  error.value = ''
  progress.value = null
}

onMounted(() => {
  loadExamConfigData()
})
</script>

<style scoped>
.rec-step {
  margin-bottom: 8px;
  border: 1px solid #dde3ea;
  border-radius: 5px;
  overflow: hidden;
}

.rec-step-hdr {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 8px;
  background: #f0f4fa;
  font-size: 11px;
  font-weight: 600;
  color: #1a3a5c;
  border-bottom: 1px solid #dde3ea;
}

.rec-step-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #1a3a5c;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
}

.rec-step-list {
  display: flex;
  flex-direction: column;
}

.rec-step-list-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 3px;
  padding: 5px;
}

.rec-step-item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 8px;
  background: transparent;
  border: none;
  border-bottom: 1px solid #f0f4fa;
  text-align: left;
  cursor: pointer;
  font-size: 11px;
  color: #1a3a5c;
  transition: background 0.15s;
  width: 100%;
}

.rec-step-item:hover:not(.active) {
  background: #f0f4fa;
}

.rec-step-item.active {
  background: #1a3a5c;
  color: #fff;
  font-weight: 600;
}

.rec-step-labo {
  justify-content: center;
  border: 1px solid #dde3ea;
  border-radius: 3px;
  padding: 6px 4px;
  font-size: 10px;
  font-weight: 600;
}

.rec-step-labo.active {
  border-color: #1a3a5c;
}

.rec-step-num {
  font-weight: 700;
  min-width: 20px;
}

.rec-step-meta {
  font-size: 10px;
  opacity: 0.85;
}
</style>
