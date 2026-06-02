<template>
  <div class="overlay" @click.self="$emit('close')">
    <div class="modal" @click.stop>
      <div class="modal-hdr">
        <span>⚙ Paramètres Globaux</span>
        <button @click="$emit('close')" title="Annuler">✕</button>
      </div>
      <div class="modal-body">
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
          <input type="text" :value="s.lycee"
            @input="upd('lycee', $event.target.value)"
            placeholder="Nom du lycée ou centre…" />
        </div>

        <div class="field-row">
          <label>Section</label>
          <select :value="s.section" @change="sectionChg($event.target.value)">
            <option v-for="sec in sectionsList" :key="sec.value" :value="sec.value">{{ sec.label }}</option>
          </select>
        </div>

        <div class="field-row">
          <label>Matière</label>
          <select :value="s.matiere" @change="upd('matiere', $event.target.value)">
            <option v-for="m in matieres" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </div>

        <div class="field-row">
          <label>Séance défaut</label>
          <select :value="s.seance" @change="upd('seance', parseInt($event.target.value))">
            <option v-for="n in seances" :key="n" :value="n">Séance-{{ n }}</option>
          </select>
        </div>

        <div class="field-row">
          <label>Colonnes grille</label>
          <input type="number" min="5" max="30" :value="s.nbQuestions || 15"
            @input="upd('nbQuestions', Math.min(30, Math.max(5, parseInt($event.target.value) || 15)))"
            style="width: 80px" />
          <span style="font-size:10px;color:#888;margin-left:4px">(5 – 30)</span>
        </div>
      </div>
      <div class="modal-ftr">
        <button class="btn-outline" @click="$emit('close')">Annuler</button>
        <button class="btn-primary" @click="$emit('save', { ...s })">✓ Enregistrer</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, computed } from 'vue'

const props = defineProps({
  settings: { type: Object, required: true },
})

const emit = defineEmits(['save', 'close'])

const s = reactive({ ...props.settings })

const sectionsList = [
  { value: 'SI', label: "Section Sciences de l'Informatique (S.I.)" },
  { value: 'Sc-TM', label: 'Sections Scientifiques (Sc • T • M)' },
  { value: 'EcoGest', label: 'Section Économie et Gestion' },
  { value: 'Lettres', label: 'Section Lettres' },
  { value: 'Sport', label: 'Section Sport' },
]

const MATIERES_BY_SECTION = {
  'SI': [
    { value: 'STI', label: "Systèmes & Technologies de l'Informatique" },
    { value: 'AlgoProg', label: 'Algorithmique & Programmation' },
  ],
  'default': [
    { value: 'Info', label: 'Informatique' },
  ],
}

const seances = Array.from({ length: 6 }, (_, i) => i + 1)
const annees = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() + i)

const matieres = computed(() => MATIERES_BY_SECTION[s.section] || MATIERES_BY_SECTION['default'])

const upd = (key, val) => { s[key] = val }

const sectionChg = (newSec) => {
  const mats = MATIERES_BY_SECTION[newSec] || MATIERES_BY_SECTION['default']
  const mat = mats.some(m => m.value === s.matiere) ? s.matiere : mats[0].value
  s.section = newSec
  s.matiere = mat
}
</script>