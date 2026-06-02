<template>
  <div class="app">
    <!-- Header -->
    <header class="hdr">
      <div class="hdr-l">
        <span class="hdr-brand">BacArchive</span>
        <span class="hdr-sep">|</span>
        <span class="hdr-info">{{ settings.annee }}</span>
        <span class="hdr-sep">•</span>
        <span class="hdr-info">{{ sectionShort }}</span>
        <span class="hdr-sep">•</span>
        <span class="hdr-info">{{ matiereShort }}</span>
        <template v-if="settings.lycee">
          <span class="hdr-sep">•</span>
          <span class="hdr-info">{{ settings.lycee }}</span>
        </template>
      </div>
      <div class="hdr-r">
        <button class="btn-daily" @click="handleDailyReport" title="Rapport journalier">📋 Rapport jour</button>
        <button class="btn-cfg" @click="showParams = true">⚙ Paramètres</button>
      </div>
    </header>

    <!-- Modal Params -->
    <ModalParams v-if="showParams" :settings="settings"
      @save="async (s) => { await saveSetting(s); showParams = false }"
      @close="showParams = false" />

    <!-- Body -->
    <div class="body">
      <div class="layout">
        <aside class="sidebar">
          <div class="mode-tabs-sidebar">
            <button class="mode-tab-btn" :class="{ active: mode === 'recup' }"
              @click="mode = 'recup'">💾 Récupération</button>
            <button class="mode-tab-btn" :class="{ active: mode === 'archive' }"
              @click="mode = 'archive'">📂 Archive</button>
          </div>
          <div :class="mode === 'archive' ? 'sidebar-scroll sidebar-scroll-archive' : 'sidebar-scroll'">
            <ModeRecup v-if="mode === 'recup'" part="sidebar"
              :settings="settings" :saveSetting="saveSetting"
              :drives="drives" :refreshDrives="refreshDrives"
              :session="session" :updateSession="updateSession" :resetSession="resetSession" />
            <ModeArchive v-else part="sidebar"
              :settings="settings" :saveSetting="saveSetting"
              :archiveState="archiveState" :updateArchiveState="updateArchiveState" />
          </div>
        </aside>

        <main class="content">
          <ModeRecup v-if="mode === 'recup'" part="content"
            :settings="settings" :saveSetting="saveSetting"
            :drives="drives" :refreshDrives="refreshDrives"
            :session="session" :updateSession="updateSession" :resetSession="resetSession" />
          <ModeArchive v-else part="content"
            :settings="settings" :saveSetting="saveSetting"
            :archiveState="archiveState" :updateArchiveState="updateArchiveState" />
        </main>
      </div>
    </div>

    <!-- Dest Bar -->
    <div v-if="settings.destBase" class="dest-bar" title="Dossier de sauvegarde">
      <span>📁</span>
      <span class="dest-bar-label">Dossier de sauvegarde :</span>
      <span class="dest-bar-text">{{ settings.destBase }}</span>
    </div>
    <div v-else class="dest-bar dest-bar-warn" @click="showParams = true">
      <span>⚠</span>
      <span class="dest-bar-text">Aucun dossier de sauvegarde — cliquer pour configurer</span>
    </div>

    <!-- Toast Manager -->
    <ToastManager ref="toastManager" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { loadSettings as apiLoadSettings, saveSettings as apiSaveSettings, listDrives as apiListDrives, generateDailyReport as apiGenerateDailyReport } from './api.js'
import { showToast } from './composables/useToast.js'
import ModeRecup from './components/ModeRecup.vue'
import ModeArchive from './components/ModeArchive.vue'
import ModalParams from './components/ModalParams.vue'
import ToastManager from './components/ToastManager.vue'

const MATIERE_SHORT = { 'STI': 'STI', 'AlgoProg': 'Algo & Prog', 'Info': 'Informatique' }
const SECTION_SHORT = {
  'SI': 'Section S.I.', 'Sc-TM': 'Section Sc•T•M',
  'EcoGest': 'Section Éco & Gest', 'Lettres': 'Section Lettres', 'Sport': 'Section Sport',
}

const settings = ref({
  annee: new Date().getFullYear(), lycee: '', matiere: 'STI', section: 'SI',
  seance: 1, labo: 1, destBase: '', nbQuestions: 15,
})
const mode = ref('recup')
const showParams = ref(false)
const drives = ref([])
const appReady = ref(false)
const toastManager = ref(null)

const session = ref({
  selDrive: null, folders: [], copyDone: false,
  pdfPath: '', xlsxPath: '', destPath: '',
  dupWarning: null, copyBlocked: false, copyBlockedType: null, dupInfo: null,
  subfolders: [], selSubfolder: '', rootCount: 0,
})

const archiveState = ref({
  archive: [], loading: false, selSeance: null, selLabo: null,
  error: '', busy: '',
})

const matiereShort = computed(() => MATIERE_SHORT[settings.value.matiere] || settings.value.matiere)
const sectionShort = computed(() => SECTION_SHORT[settings.value.section] || ('Section ' + settings.value.section))

let refreshInterval = null

onMounted(async () => {
  try {
    const r = await apiLoadSettings()
    if (r?.success && r.data && Object.keys(r.data).length) {
      settings.value = { ...settings.value, ...r.data }
    }
  } catch (e) { console.error('Failed to load settings:', e) }
  appReady.value = true
  showParams.value = true
  await refreshDrives()
  // refreshInterval = setInterval(refreshDrives, 3000)
})

onUnmounted(() => {
  // if (refreshInterval) clearInterval(refreshInterval)
})

const refreshDrives = async () => {
  try {
    const r = await apiListDrives()
    if (r?.success) {
      const newDrives = r.data || []
      drives.value = newDrives
      if (session.value.selDrive && !newDrives.find(d => d.path === session.value.selDrive.path)) {
        session.value = {
          selDrive: null, folders: [], copyDone: false,
          pdfPath: '', xlsxPath: '', destPath: '',
          dupWarning: null, copyBlocked: false, copyBlockedType: null, dupInfo: null,
          subfolders: [], selSubfolder: '', rootCount: 0,
        }
      }
    }
  } catch (e) { console.error('Failed to list drives:', e) }
}

const handleDailyReport = async () => {
  const dest = settings.value.destBase
  if (!dest) { showToast('Aucun dossier de sauvegarde configuré.', 'err'); return }
  showToast('Génération du rapport journalier…', 'info', 60000)
  try {
    const r = await apiGenerateDailyReport(dest)
    if (r?.success) {
      showToast('Rapport journalier généré avec succès.', 'ok')
    } else {
      showToast('Erreur rapport : ' + (r?.error || 'inconnue'), 'err')
    }
  } catch (e) {
    showToast('Erreur rapport : ' + e.message, 'err')
  }
}

const saveSetting = async (updates) => {
  const next = { ...settings.value, ...updates }
  settings.value = next
  try { await apiSaveSettings(next) } catch (e) { console.error('Failed to save settings:', e) }
  return next
}

const updateSession = (u) => { session.value = { ...session.value, ...u } }
const resetSession = () => {
  session.value = {
    selDrive: null, folders: [], copyDone: false,
    pdfPath: '', xlsxPath: '', destPath: '',
    dupWarning: null, copyBlocked: false, copyBlockedType: null, dupInfo: null,
    subfolders: [], selSubfolder: '', rootCount: 0,
  }
}
const updateArchiveState = (u) => { archiveState.value = { ...archiveState.value, ...u } }
</script>