# BacArchive

**Collecte et gestion centralisée des travaux du Bac pratique**

Application web PHP + Vue.js 3 pour la collecte et la gestion des travaux pratiques du Bac depuis des clés USB.

## 🎯 Fonctionnalités

- **Mode Récupération** : Détection automatique des clés USB, scan des dossiers candidats (format NNNNNN), copie avec vérification MD5, génération de rapports PDF et grilles Excel
- **Mode Archive** : Navigation dans l'historique des séances et labos, régénération de documents
- **Rapport journalier** : Synthèse globale de toutes les séances de la journée
- **Multi-sections** : STI, AlgoProg, Sc-TM, EcoGest, Lettres, Sport
- **Anti-fraude** : Détection automatique des clés USB piégées (dossiers `_UsbWatcher`)
- **Statistiques** : Présents, absents, non-conformes, fraudes

## 🛠️ Stack technique

- **Backend** : PHP 8.2+ (pas de framework, classes PSR-4 sous `BacArchive\`)
- **Frontend** : Vue.js 3 + Vite
- **PDF** : [mPDF](https://mpdf.github.io/)
- **Excel** : [PhpSpreadsheet](https://phpspreadsheet.readthedocs.io/)

## 📁 Structure

```
BacArchive/
├── api/                    # Routeur API RESTful
│   └── index.php
├── includes/               # Classes PHP (PSR-4)
│   ├── Labels.php
│   ├── SettingsManager.php
│   ├── ConfigManager.php
│   ├── FileProcessor.php
│   ├── PDFGenerator.php
│   └── ExcelGenerator.php
├── frontend/               # Application Vue.js 3
│   ├── src/
│   │   ├── App.vue
│   │   ├── api.js
│   │   ├── utils.js
│   │   ├── statsHelpers.js
│   │   ├── App.css
│   │   ├── composables/
│   │   │   └── useToast.js
│   │   └── components/
│   │       ├── ModeRecup.vue
│   │       ├── ModeArchive.vue
│   │       ├── ModalParams.vue
│   │       ├── Stats.vue
│   │       ├── Icons.vue
│   │       └── ToastManager.vue
│   ├── index.html
│   ├── vite.config.js
│   └── package.json
├── dist/                   # Build de production (généré)
├── vendor/                 # Dépendances PHP (Composer)
├── assets/                 # Icônes
├── help/                   # Documentation utilisateur
├── screenshots/            # Captures d'écran
├── index.php               # Point d'entrée
├── .htaccess               # Réécriture d'URL
├── composer.json           # Dépendances PHP
└── README.md
```

## 🚀 Installation

### Prérequis

- PHP 8.1+ avec extensions : `gd`, `zip`, `mbstring`
- Composer
- Node.js 18+ et npm
- Serveur web (Apache avec mod_rewrite, ou PHP built-in server)

### Étapes

```bash
# 1. Cloner ou télécharger le projet
cd BacArchive/

# 2. Installer les dépendances PHP
composer install

# 3. Installer et construire le frontend
cd frontend
npm install
npm run build
cd ..

# 4. Configurer le serveur web pour pointer sur ce dossier
#    Ou utiliser le serveur PHP built-in :
php -S localhost:8080
```

### Configuration PHP

Activer les extensions nécessaires dans `php.ini` :
```ini
extension=gd
extension=zip
extension=mbstring
```

## 📖 Utilisation

1. Ouvrir l'application dans le navigateur : `http://localhost/BacArchive/`
2. Cliquer sur **⚙ Paramètres** pour configurer le dossier de sauvegarde
3. Renseigner l'année, la section, la matière, le lycée
4. Brancher une clé USB : elle apparaît automatiquement
5. Cliquer sur la clé pour scanner les dossiers candidats
6. Cliquer sur **▶ Copier & Vérifier (MD5)** pour lancer la copie
7. Les rapports PDF et grilles Excel sont générés automatiquement

## 📜 Licence

GPL-3.0 © 2026 La Communauté Tunisienne des Enseignants d'Informatique