/**
 * BacArchive — Helpers partagés (Vue.js 3)
 */

/** Taille en notation humaine: 2048 → "2.0 Ko" */
export const fmt = (b) => {
  if (!b) return '0 o'
  const k = 1024, units = ['o', 'Ko', 'Mo', 'Go']
  const i = Math.floor(Math.log(b) / Math.log(k))
  return (b / Math.pow(k, i)).toFixed(1) + ' ' + units[i]
}

/** Pad numbers: 5 → "05" */
export const pad2 = (n) => String(n).padStart(2, '0')

/** Constantes UI */
export const SEANCES = Array.from({ length: 6 }, (_, i) => i + 1)
export const LABOS = Array.from({ length: 8 }, (_, i) => i + 1)