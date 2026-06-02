/**
 * BacArchive — Service API (Vue.js 3)
 * Communique avec le backend PHP via fetch
 */

const API_BASE = './api/index.php'

async function request(action, data = null, method = 'POST') {
  const url = `${API_BASE}?action=${action}`
  const options = {
    method,
    headers: { 'Content-Type': 'application/json' },
  }
  if (data && method === 'POST') {
    options.body = JSON.stringify(data)
  }
  const response = await fetch(url, options)
  return response.json()
}

// ── Paramètres ──────────────────────────────────────────────
export const loadSettings = () => request('load-settings', null, 'GET')
export const saveSettings = (settings) => request('save-settings', { settings })

// ── USB Drives ──────────────────────────────────────────────
export const listDrives = () => request('list-drives', null, 'GET')
export const listDriveSubfolders = (path) => request('list-drive-subfolders', { path })
export const readBacConfig = (path) => request('read-bac-config', { path })
export const updateBacLabo = (path, labo) => request('update-bac-labo', { path, labo })

// ── Scan / Copie ────────────────────────────────────────────
export const scanFolders = (path) => request('scan-folders', { path })
export const copyFiles = (folders, destPath) => request('copy-files', { folders, destPath })

// ── Archive ─────────────────────────────────────────────────
export const scanArchive = (path) => request('scan-archive', { path })

// ── Génération ──────────────────────────────────────────────
export const generatePDF = (data, destFolder) => request('generate-pdf', { data, destFolder })
export const generateExcel = (data, destFolder) => request('generate-excel', { data, destFolder })
export const generateDailyReport = (destBase) => request('generate-daily-report', { destBase })

// ── Suppression ─────────────────────────────────────────────
export const deleteFolders = (drivePath, folders) => request('delete-folders', { drivePath, folders })