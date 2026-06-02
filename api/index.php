<?php
/**
 * BacArchive — API Router
 * Point d'entrée pour toutes les requêtes API
 */

require_once __DIR__ . '/../vendor/autoload.php';

use BacArchive\Labels;
use BacArchive\SettingsManager;
use BacArchive\ConfigManager;
use BacArchive\FileProcessor;
use BacArchive\ExamConfigManager;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        // ── Paramètres ──────────────────────────────────────
        case 'load-settings':
            $sm = new SettingsManager();
            echo json_encode(['success' => true, 'data' => $sm->load()]);
            break;

        case 'save-settings':
            $input = json_decode(file_get_contents('php://input'), true);
            $sm = new SettingsManager();
            $sm->save($input['settings'] ?? $input);
            echo json_encode(['success' => true]);
            break;

        // ── Configuration des examens (examconfig.ini) ────
        case 'load-exam-config':
            $ecm = new ExamConfigManager();
            echo json_encode(['success' => true, 'data' => $ecm->load()]);
            break;

        case 'save-exam-config':
            $input = json_decode(file_get_contents('php://input'), true);
            $ecm = new ExamConfigManager();
            $config = $input['config'] ?? $input;
            $ok = $ecm->save($config);
            echo json_encode(['success' => $ok, 'data' => $ecm->load()]);
            break;

        // ── USB Drives ──────────────────────────────────────
        case 'list-drives':
            echo json_encode(['success' => true, 'data' => FileProcessor::listDrives()]);
            break;

        case 'list-drive-subfolders':
            $input = json_decode(file_get_contents('php://input'), true);
            $path = $input['path'] ?? $_GET['path'] ?? '';
            echo json_encode(['success' => true, 'data' => FileProcessor::listDriveSubfolders($path)]);
            break;

        case 'read-bac-config':
            $input = json_decode(file_get_contents('php://input'), true);
            $path = $input['path'] ?? $_GET['path'] ?? '';
            $iniPath = rtrim($path, '\\/') . DIRECTORY_SEPARATOR . 'BacArchive.ini';
            if (file_exists($iniPath)) {
                echo json_encode(['success' => true, 'data' => ConfigManager::readBacConfig($iniPath)]);
            } else {
                // Essayer de lire le label du volume (Windows)
                $laboFromLabel = null;
                if (PHP_OS_FAMILY === 'Windows') {
                    $driveLetter = preg_replace('/[:\\\\].*/', '', strtoupper($path));
                    try {
                        $psOut = @shell_exec(
                            "powershell -NoProfile -Command \"(Get-Volume -DriveLetter {$driveLetter}).FileSystemLabel\""
                        );
                        $label = trim($psOut ?: '');
                        if (preg_match('/[Ll][Aa][Bb][Oo][-_\s]?(\d{1,2})/', $label, $m)) {
                            $laboFromLabel = (int)$m[1];
                        }
                    } catch (\Throwable $e) {}
                }
                echo json_encode(['success' => true, 'data' => [
                    'Matiere'        => 'Unknown',
                    'Bac'            => (int)(new \DateTime())->format('Y'),
                    'Labo'           => $laboFromLabel ?? 1,
                    'Seance'         => 1,
                    'GuiPart3Extend' => 0,
                    '_fromLabel'     => $laboFromLabel !== null,
                ]]);
            }
            break;

        case 'update-bac-labo':
            $input = json_decode(file_get_contents('php://input'), true);
            $drivePath = $input['path'] ?? '';
            $labo = $input['labo'] ?? 1;
            $iniPath = rtrim($drivePath, '\\/') . DIRECTORY_SEPARATOR . 'BacArchive.ini';
            if (!file_exists($iniPath)) {
                echo json_encode(['success' => false, 'error' => 'BacArchive.ini non trouvé']);
                break;
            }
            $content = file_get_contents($iniPath);
            if (preg_match('/^Labo=/m', $content)) {
                $content = preg_replace('/^(Labo=).*/m', '$1' . $labo, $content);
            } else {
                $content = preg_replace('/^(\[Params\])/m', "$1\r\nLabo=$labo", $content);
            }
            file_put_contents($iniPath, $content);
            echo json_encode(['success' => true]);
            break;

        // ── Scan / Copie ────────────────────────────────────
        case 'scan-folders':
            $input = json_decode(file_get_contents('php://input'), true);
            $path = $input['path'] ?? $_GET['path'] ?? '';
            echo json_encode(['success' => true, 'data' => FileProcessor::scanFolders($path)]);
            break;

        case 'copy-files':
            $input = json_decode(file_get_contents('php://input'), true);
            $folders = $input['folders'] ?? [];
            $destPath = $input['destPath'] ?? '';
            if (file_exists($destPath)) {
                echo json_encode(['success' => false, 'error' => "Le dossier de destination existe déjà :\n$destPath\n\nModifiez la Séance ou le Labo."]);
                break;
            }
            $result = FileProcessor::copyFilesWithMD5($folders, $destPath);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        // ── Archive ─────────────────────────────────────────
        case 'scan-archive':
            $input = json_decode(file_get_contents('php://input'), true);
            $path = $input['path'] ?? $_GET['path'] ?? '';
            echo json_encode(['success' => true, 'data' => FileProcessor::scanArchive($path)]);
            break;

        // ── Génération PDF ──────────────────────────────────
        case 'generate-pdf':
            $input = json_decode(file_get_contents('php://input'), true);
            require_once __DIR__ . '/../includes/PDFGenerator.php';
            $pdfPath = \BacArchive\PDFGenerator::generate($input['data'], $input['destFolder']);
            echo json_encode(['success' => true, 'data' => ['pdfPath' => $pdfPath]]);
            break;

        // ── Génération Excel ────────────────────────────────
        case 'generate-excel':
            $input = json_decode(file_get_contents('php://input'), true);
            require_once __DIR__ . '/../includes/ExcelGenerator.php';
            $xlsxPath = \BacArchive\ExcelGenerator::generate($input['data'], $input['destFolder']);
            echo json_encode(['success' => true, 'data' => ['xlsxPath' => $xlsxPath]]);
            break;

        // ── Rapport journalier ──────────────────────────────
        case 'generate-daily-report':
            $input = json_decode(file_get_contents('php://input'), true);
            $destBase = $input['destBase'] ?? '';
            if (empty($destBase) || !is_dir($destBase)) {
                echo json_encode(['success' => false, 'error' => 'Dossier de sauvegarde introuvable.']);
                break;
            }
            $seances = FileProcessor::scanArchive($destBase);
            if (empty($seances)) {
                echo json_encode(['success' => false, 'error' => 'Aucune séance trouvée dans le dossier de sauvegarde.']);
                break;
            }
            $sm = new SettingsManager();
            $s = $sm->load();
            require_once __DIR__ . '/../includes/PDFGenerator.php';
            $pdfPath = \BacArchive\PDFGenerator::generateDailyReport([
                'seances'  => $seances,
                'matiere'  => $s['matiere'],
                'section'  => $s['section'],
                'annee'    => $s['annee'],
                'lycee'    => $s['lycee'],
            ], $destBase);
            echo json_encode(['success' => true, 'data' => ['pdfPath' => $pdfPath]]);
            break;

        // ── Suppression dossiers USB ────────────────────────
        case 'delete-folders':
            $input = json_decode(file_get_contents('php://input'), true);
            echo json_encode(['success' => true, 'data' => FileProcessor::deleteSourceFolders(
                $input['drivePath'] ?? '',
                $input['folders'] ?? []
            )]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Action inconnue: $action"]);
            break;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}