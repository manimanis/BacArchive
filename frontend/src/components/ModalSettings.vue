<template>
  <div class="overlay" @click.self="$emit('close')">
    <div class="modal modal-wide" @click.stop>
      <div class="modal-hdr">
        <span>⚙ Paramètres & Configuration des Examens</span>
        <button @click="$emit('close')" title="Fermer">✕</button>
      </div>

      <!-- Onglets : Paramètres globaux / Configuration des examens -->
      <div class="settings-tabs-bar">
        <button class="settings-tab" :class="{ active: tab === 'global' }" @click="selectTab('global')">⚙ Paramètres
          globaux</button>
        <button class="settings-tab" :class="{ active: tab === 'examens' }" @click="selectTab('examens')">📅
          Configuration des examens</button>
      </div>

      <div class="modal-body modal-body-scroll">
        <!-- ── Onglet : Paramètres globaux ────────────── -->
        <template v-if="tab === 'global'">
          <div class="param-highlight">
            <div class="param-highlight-label">
              📁 Dossier de sauvegarde
              <span class="param-required">obligatoire</span>
            </div>
            <div class="dest-pick-row">
              <input type="text" :value="s.destBase" @input="upd('destBase', $event.target.value)"
                placeholder="Entrer le chemin du dossier…"
                :class="'dest-pick-input' + (!s.destBase ? ' dest-pick-empty' : '')" />
            </div>
            <div v-if="!s.destBase" class="param-warn">
              ⚠ Aucun dossier sélectionné — la copie ne pourra pas démarrer
            </div>
            <div v-if="s.destBase" class="param-path-preview">{{ s.destBase }}</div>
          </div>

          <div class="param-sep" />

          <div class="field-row">
            <label>Année Bac</label>
            <select :value="s.annee" @change="upd('annee', parseInt($event.target.value))">
              <option v-for="a in annees" :key="a" :value="a">{{ a }}</option>
            </select>
          </div>

          <div class="field-row">
            <label>Lycée / Centre</label>
            <input type="text" :value="s.lycee" @input="upd('lycee', $event.target.value)"
              placeholder="Nom du lycée ou centre…" />
          </div>

        </template>

        <!-- ── Onglet : Configuration des examens ────── -->
        <template v-else>
          <div v-if="loadingExam" class="hint center">⏳ Chargement…</div>

          <template v-else>
            <!-- Barre d'outils : nombre de jours + actions -->
            <div class="exam-toolbar">
              <div class="exam-toolbar-actions">
                <button class="btn-sm" @click="addJour" :disabled="cfg.jours.length >= 20"
                  title="Ajouter un nouveau jour">＋ Nouveau jour</button>
                <button class="btn-sm" @click="duplicateJour(activeJour)"
                  :disabled="cfg.jours.length === 0 || cfg.jours.length >= 20" title="Dupliquer le jour actif">⎘
                  Dupliquer</button>
                <button class="btn-sm btn-danger" @click="removeJour(activeJour)" :disabled="cfg.jours.length === 0"
                  title="Supprimer le jour actif">🗑 Supprimer jour</button>
              </div>
            </div>

            <p v-if="cfg.jours.length === 0" class="hint center" style="margin-top:16px">
              Aucun jour défini. Cliquez sur « + Nouveau jour » pour commencer.
            </p>

            <!-- Onglets des jours -->
            <div v-if="cfg.jours.length > 0" class="exam-tabs-bar">
              <button v-for="(jour, di) in cfg.jours" :key="di" class="exam-tab" :class="{ active: activeJour === di }"
                @click="activeJour = di" :title="`Jour ${di + 1} — ${jour.examens.length} examen(s)`">
                <span class="exam-tab-num">Jour {{ di + 1 }}</span>
                <span v-if="getJourDate(di)" class="exam-tab-date">{{ getJourDate(di) }}</span>
              </button>
            </div>

            <!-- Contenu du jour actif -->
            <div v-if="cfg.jours.length > 0" class="exam-tab-content">
              <div class="exam-jour-header">
                <h3 class="exam-jour-title">📅 Jour {{ activeJour + 1 }}</h3>
                <div class="exam-jour-actions">
                  <button class="btn-sm" @click="moveJour(activeJour, -1)" :disabled="activeJour === 0"
                    title="Monter ce jour">↑</button>
                  <button class="btn-sm" @click="moveJour(activeJour, 1)" :disabled="activeJour >= cfg.jours.length - 1"
                    title="Descendre ce jour">↓</button>
                </div>
              </div>

              <div class="field-row">
                <button class="btn-sm" @click="addExamen(activeJour)" :disabled="currentJour.examens.length >= 10"
                  title="Ajouter un examen">＋ Examen</button>
              </div>

              <p v-if="currentJour.examens.length === 0" class="hint center" style="margin:14px 0">
                Aucun examen pour ce jour. Cliquez sur « + Examen ».
              </p>

              <!-- Examens -->
              <div v-for="(exam, ei) in currentJour.examens" :key="ei" class="exam-examen">
                <div class="exam-examen-hdr">
                  <span class="exam-examen-title">📝 Examen {{ ei + 1 }}</span>
                  <div class="exam-jour-actions">
                    <button class="btn-sm" @click="moveExamen(activeJour, ei, -1)" :disabled="ei === 0"
                      title="Monter">↑</button>
                    <button class="btn-sm" @click="moveExamen(activeJour, ei, 1)"
                      :disabled="ei >= currentJour.examens.length - 1" title="Descendre">↓</button>
                    <button class="btn-sm" @click="duplicateExamen(activeJour, ei)"
                      :disabled="currentJour.examens.length === 0 || currentJour.examens.length >= 5"
                      title="Dupliquer l'examen' actif">⎘ Dupliquer</button>
                    <button class="btn-sm btn-danger" @click="removeExamen(activeJour, ei)"
                      title="Supprimer cet examen">🗑</button>
                  </div>
                </div>

                <div class="field-row">
                  <label>Section</label>
                  <select :value="exam.section" @change="setExamField(activeJour, ei, 'section', $event.target.value)">
                    <option value="">— Choisir —</option>
                    <option v-for="sec in sectionsList" :key="sec.value" :value="sec.value">{{ sec.label }}</option>
                  </select>
                </div>

                <div class="field-row">
                  <label>Matière</label>
                  <input type="text" :value="exam.examen"
                    @input="setExamField(activeJour, ei, 'examen', $event.target.value)"
                    placeholder="Ex : STI, Informatique, Algo…" />
                </div>

                <div class="field-row">
                  <label>Date</label>
                  <input type="date" :value="exam.date"
                    @input="setExamField(activeJour, ei, 'date', $event.target.value)" />
                </div>

                <div class="field-row">
                  <button class="btn-sm" @click="addSeance(activeJour, ei)" :disabled="exam.seances.length >= 10"
                    title="Ajouter une séance">＋ Séance</button>
                </div>

                <p v-if="exam.seances.length === 0" class="hint" style="margin:8px 0 0 12px">
                  Aucune séance. Cliquez sur « + Séance ».
                </p>

                <!-- Séances -->
                <div class="exam-seances">
                  <div v-for="(se, si) in exam.seances" :key="si" class="exam-seance">
                    <span class="exam-seance-num">Séance {{ si + 1 }}</span>
                    <div class="field-row inline">
                      <label>Début</label>
                      <input type="time" :value="se.date_deb"
                        @input="setSeanceField(activeJour, ei, si, 'date_deb', $event.target.value)" />
                    </div>
                    <div class="field-row inline">
                      <label>Durée</label>
                      <input type="number" min="0" max="600" :value="se.duree"
                        @input="setSeanceField(activeJour, ei, si, 'duree', parseInt($event.target.value) || 0)"
                        style="width:70px" />
                      <span class="unit">min</span>
                    </div>
                    <button class="btn-sm btn-danger" @click="removeSeance(activeJour, ei, si)"
                      title="Supprimer">✕</button>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </template>

        <div v-if="errMsg" class="alert-err">
          <span style="white-space:pre-wrap">{{ errMsg }}</span>
          <button @click="errMsg = ''">✕</button>
        </div>
      </div>

      <div class="modal-ftr">
        <span class="modal-ftr-info" v-if="tab === 'examens' && cfg.file">📄 {{ cfg.file }}</span>
        <div class="modal-ftr-actions">
          <button class="btn-outline" @click="$emit('close')">Annuler</button>
          <button v-if="tab === 'global'" class="btn-primary" @click="onSaveGlobal">✓ Enregistrer</button>
          <button v-else class="btn-primary" :disabled="savingExam" @click="onSaveExamens">
            {{ savingExam ? '⏳ Enregistrement…' : '✓ Enregistrer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue'
import { loadExamConfig, saveExamConfig } from '../api.js'
import { showToast } from '../composables/useToast.js'

const props = defineProps({
  settings: { type: Object, required: true },
  initialTab: { type: String, default: 'global' },
})

const emit = defineEmits(['save', 'close', 'exam-saved'])

// ── Liste des sections (partagée entre les deux onglets) ──
const sectionsList = [
  { value: 'SI', label: "Sciences de l'Informatique (S.I.)" },
  { value: 'Sc-TM', label: 'Scientifiques (Sc • T • M)' },
  { value: 'EcoGest', label: 'Économie et Gestion' },
  { value: 'Lettres', label: 'Lettres' },
  { value: 'Sport', label: 'Sport' },
]

// ═══════════════════════════════════════════════════════════
// ONGLET 1 : Paramètres globaux
// ═══════════════════════════════════════════════════════════
const s = reactive({ ...props.settings })

const annees = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() + i)

const upd = (key, val) => { s[key] = val }

const onSaveGlobal = () => {
  emit('save', { ...s })
}

// ═══════════════════════════════════════════════════════════
// ONGLET 2 : Configuration des examens
// ═══════════════════════════════════════════════════════════
const tab = ref(props.initialTab)
const loadingExam = ref(false)
const examLoaded = ref(false)
const savingExam = ref(false)
const errMsg = ref('')
const activeJour = ref(0)

const cfg = reactive({
  file: '',
  exists: false,
  nbre_jours: 0,
  jours: [],
})

const clampActiveJour = () => {
  if (cfg.jours.length === 0) {
    activeJour.value = 0
  } else if (activeJour.value >= cfg.jours.length) {
    activeJour.value = cfg.jours.length - 1
  } else if (activeJour.value < 0) {
    activeJour.value = 0
  }
}

const currentJour = computed(() => {
  if (cfg.jours.length === 0) return { nb_examens: 0, examens: [] }
  return cfg.jours[activeJour.value] || { nb_examens: 0, examens: [] }
})

const getJourDate = (di) => {
  const jour = cfg.jours[di]
  if (!jour || !jour.examens || jour.examens.length === 0) return ''
  const date = jour.examens[0].date
  if (!date) return ''
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(date)
  if (!m) return ''
  return `${m[3]}/${m[2]}`
}

const newSeance = () => ({ date_deb: '08:00', duree: 60 })

const newExamen = (annee) => ({
  section: 'SI',
  examen: '',
  date: `${annee}-06-01`,
  nb_seances: 1,
  seances: [newSeance()],
})

const newJour = (annee) => ({
  nb_examens: 1,
  examens: [newExamen(annee)],
})

const loadExamConfigData = async () => {
  loadingExam.value = true
  errMsg.value = ''
  try {
    const r = await loadExamConfig()
    if (r?.success) {
      Object.assign(cfg, r.data)
      cfg.jours = r.data.jours || []
    } else {
      errMsg.value = r?.error || 'Erreur de chargement'
    }
  } catch (e) {
    errMsg.value = e.message
  } finally {
    loadingExam.value = false
    examLoaded.value = true
  }
}

const selectTab = (newTab) => {
  tab.value = newTab
  // Charge la config examens à la demande au premier affichage
  if (newTab === 'examens' && !examLoaded.value && !loadingExam.value) {
    loadExamConfigData()
  }
}

onMounted(() => {
  if (props.initialTab === 'examens') {
    loadExamConfigData()
  }
})


const annee = computed(() => s.annee || new Date().getFullYear())

const addJour = () => {
  if (cfg.jours.length >= 20) return
  cfg.jours.push(newJour(annee.value))
  cfg.nbre_jours = cfg.jours.length
  activeJour.value = cfg.jours.length - 1
}

const duplicateJour = (di) => {
  if (cfg.jours.length >= 20 || di < 0 || di >= cfg.jours.length) return
  const src = cfg.jours[di]
  const copy = {
    nb_examens: src.nb_examens,
    examens: src.examens.map(e => ({
      section: e.section,
      examen: e.examen,
      date: e.date,
      nb_seances: e.nb_seances,
      seances: e.seances.map(s => ({ date_deb: s.date_deb, duree: s.duree })),
    })),
  }
  if (copy.examens.length > 0 && copy.examens[0].date) {
    const d = new Date(copy.examens[0].date)
    if (!isNaN(d.getTime())) {
      d.setDate(d.getDate() + 1)
      copy.examens.forEach(e => { e.date = d.toISOString().slice(0, 10) })
    }
  }
  cfg.jours.splice(di + 1, 0, copy)
  cfg.nbre_jours = cfg.jours.length
  activeJour.value = di + 1
}

const removeJour = (di) => {
  if (di < 0 || di >= cfg.jours.length) return
  if (!confirm('Etes-vous sûr de vouloir supprimer cette journée ?')) return
  cfg.jours.splice(di, 1)
  cfg.nbre_jours = cfg.jours.length
  clampActiveJour()
}

const moveJour = (di, delta) => {
  const ni = di + delta
  if (ni < 0 || ni >= cfg.jours.length) return
  const tmp = cfg.jours[di]
  cfg.jours[di] = cfg.jours[ni]
  cfg.jours[ni] = tmp
  if (activeJour.value === di) activeJour.value = ni
  else if (activeJour.value === ni) activeJour.value = di
}

const addExamen = (di) => {
  if (cfg.jours[di].examens.length >= 10) return
  cfg.jours[di].examens.push(newExamen(annee.value))
  cfg.jours[di].nb_examens = cfg.jours[di].examens.length
}

const removeExamen = (di, ei) => {
  if (!confirm('Etes-vous sûr de vouloir supprimer cet examen ?')) return
  cfg.jours[di].examens.splice(ei, 1)
  cfg.jours[di].nb_examens = cfg.jours[di].examens.length
}

const moveExamen = (di, ei, delta) => {
  const ne = ei + delta
  if (ne < 0 || ne >= cfg.jours[di].examens.length) return
  const arr = cfg.jours[di].examens
  const tmp = arr[ei]
  arr[ei] = arr[ne]
  arr[ne] = tmp
}

const duplicateExamen = (di, ei) => {
  if (cfg.jours[di].examens.length >= 5) return
  const newEx = { ...cfg.jours[di].examens[ei] }
  cfg.jours[di].examens.push(newEx)
  cfg.jours[di].nb_examens = cfg.jours[di].examens.length
}

const setExamField = (di, ei, key, val) => {
  cfg.jours[di].examens[ei][key] = val
}

const addSeance = (di, ei) => {
  const seances = cfg.jours[di].examens[ei].seances
  if (seances.length >= 10) return
  seances.push(newSeance())
  cfg.jours[di].examens[ei].nb_seances = seances.length
}

const removeSeance = (di, ei, si) => {
  if (!confirm('Etes-vous sûr de vouloir supprimer cette séance ?')) return
  cfg.jours[di].examens[ei].seances.splice(si, 1)
  cfg.jours[di].examens[ei].nb_seances = cfg.jours[di].examens[ei].seances.length
}

const setSeanceField = (di, ei, si, key, val) => {
  cfg.jours[di].examens[ei].seances[si][key] = val
}

const onSaveExamens = async () => {
  errMsg.value = ''
  savingExam.value = true
  try {
    const payload = {
      nbre_jours: cfg.nbre_jours,
      jours: cfg.jours,
    }
    const r = await saveExamConfig(payload)
    if (r?.success) {
      Object.assign(cfg, r.data)
      cfg.jours = r.data.jours || []
      showToast('Configuration des examens enregistrée.', 'ok')
      emit('exam-saved', r.data)
    } else {
      errMsg.value = r?.error || 'Erreur inconnue'
      showToast('Erreur : ' + (r?.error || 'inconnue'), 'err')
    }
  } catch (e) {
    errMsg.value = e.message
    showToast('Erreur : ' + e.message, 'err')
  } finally {
    savingExam.value = false
  }
}
</script>

<style scoped>
.modal-wide {
  max-width: 760px;
  width: 95vw;
  max-height: 92vh;
  display: flex;
  flex-direction: column;
}

.modal-body-scroll {
  max-height: calc(92vh - 160px);
  overflow-y: auto;
}

.settings-tabs-bar {
  display: flex;
  gap: 0;
  border-bottom: 2px solid #dde3ea;
  background: #f8fafc;
  padding: 0 8px;
}

.settings-tab {
  padding: 10px 18px;
  background: transparent;
  color: #555;
  border: none;
  border-bottom: 3px solid transparent;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: -2px;
  transition: color 0.15s, border-color 0.15s, background 0.15s;
}

.settings-tab:hover:not(.active) {
  color: #1a3a5c;
  background: #eef2f7;
}

.settings-tab.active {
  color: #1a3a5c;
  border-bottom-color: #1a3a5c;
  background: #fff;
  font-weight: 600;
}

.exam-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 4px 0 8px;
}

.exam-toolbar-actions {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.exam-tabs-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  border-bottom: 2px solid #dde3ea;
  margin-bottom: 14px;
}

.exam-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px 6px;
  background: #f0f4fa;
  color: #1a3a5c;
  border: 1px solid #dde3ea;
  border-bottom: none;
  border-radius: 6px 6px 0 0;
  cursor: pointer;
  font-size: 12px;
  font-weight: 500;
  position: relative;
  bottom: -2px;
  transition: background 0.15s, color 0.15s;
  width: 18%;
}

.exam-tab:hover:not(.active) {
  background: #e4e8ed;
}

.exam-tab.active {
  background: #1a3a5c;
  color: #fff;
  border-color: #1a3a5c;
  font-weight: 600;
}

.exam-tab-num {
  font-weight: 700;
}

.exam-tab-date {
  font-size: 10px;
  opacity: 0.85;
  font-family: monospace;
}

.exam-tab-content {
  background: #f8fafc;
  border: 1px solid #dde3ea;
  border-radius: 6px;
  padding: 12px 14px;
  margin-bottom: 12px;
}

.exam-jour-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
  padding-bottom: 8px;
  border-bottom: 1px dashed #dde3ea;
}

.exam-jour-header h3 {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: #1a3a5c;
}

.exam-jour-title {
  font-weight: 600;
  font-size: 13px;
  color: #1a3a5c;
}

.exam-jour-actions {
  display: flex;
  gap: 4px;
}

.exam-examen {
  border: 1px solid #e2e8f0;
  border-radius: 5px;
  padding: 8px 10px;
  margin: 6px 0 18px;
  background: #fff;
}

.exam-examen-hdr {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
  padding-bottom: 4px;
  border-bottom: 1px dotted #e2e8f0;
}

.exam-examen-title {
  font-weight: 600;
  font-size: 12px;
  color: #22507e;
}

.exam-seance {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px 6px;
  background: #f0f4fa;
  /* border-radius: 4px; */
  flex-wrap: wrap;
}

.exam-seance:first-child {
  border-top-left-radius: 4px;
  border-top-right-radius: 4px;
}

.exam-seance:last-child {
  border-bottom-left-radius: 4px;
  border-bottom-right-radius: 4px;
}

.exam-seance-num {
  font-size: 11px;
  font-weight: 600;
  color: #555;
  min-width: 60px;
}

.exam-seance .field-row.inline {
  margin: 0;
  gap: 4px;
}

.exam-seance .field-row.inline label {
  font-size: 11px;
  min-width: auto;
}

.unit {
  font-size: 11px;
  color: #888;
}

.btn-sm.btn-danger {
  background: #fef2f2;
  color: #b91c1c;
  border-color: #fecaca;
}

.btn-sm.btn-danger:hover {
  background: #fee2e2;
}

.modal-ftr {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.modal-ftr-info {
  font-size: 10px;
  color: #888;
  font-family: monospace;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.modal-ftr-actions {
  display: flex;
  gap: 8px;
}
</style>
