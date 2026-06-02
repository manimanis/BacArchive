<?php

/**
 * BacArchive — Processeur de fichiers (PHP)
 * Détection lecteurs amovibles, scan dossiers candidats, copie MD5
 */

namespace BacArchive;

class FileProcessor
{
  /**
   * Calculer le MD5 d'un fichier
   */
  public static function calculateMD5(string $filePath): string
  {
    return md5_file($filePath) ?: '';
  }

  /**
   * Lister les lecteurs amovibles (USB)
   */
  public static function listDrives(): array
  {
    $drives = [];

    if (PHP_OS_FAMILY === 'Windows') {
      // Essayer wmic d'abord
      $output = @shell_exec(
        'wmic logicaldisk where DriveType=2 get DeviceID,VolumeName,Size,FreeSpace /format:csv 2>nul'
      );
      if ($output) {
        foreach (explode("\n", $output) as $line) {
          $line = trim($line);
          if (empty($line)) continue;
          $parts = str_getcsv($line);
          if (count($parts) < 5) continue;
          $deviceID = $parts[1] ?? '';
          $free     = (int)($parts[2] ?? 0);
          $size     = (int)($parts[3] ?? 0);
          $volName  = trim($parts[4] ?? '');
          if (!str_contains($deviceID, ':')) continue;
          $drivePath = $deviceID . '\\';
          if (is_dir($drivePath)) {
            $drives[] = [
              'path'      => $drivePath,
              'name'      => $volName ? "$deviceID — $volName" : $deviceID,
              'size'      => $size,
              'available' => $free,
            ];
          }
        }
      }
      // Fallback : tester les lettres C-Z
      if (empty($drives)) {
        for ($i = ord('C'); $i <= ord('Z'); $i++) {
          $letter = chr($i);
          $dp = "$letter:\\";
          if (is_dir($dp)) {
            $drives[] = [
              'path'      => $dp,
              'name'      => "$letter:",
              'size'      => 0,
              'available' => 0,
            ];
          }
        }
      }
    } elseif (PHP_OS_FAMILY === 'Darwin') {
      if (is_dir('/Volumes')) {
        foreach (scandir('/Volumes') as $d) {
          if ($d === 'Macintosh HD') continue;
          $p = '/Volumes/' . $d;
          if (is_dir($p)) {
            $drives[] = ['path' => $p, 'name' => $d, 'size' => 0, 'available' => 0];
          }
        }
      }
    } else {
      foreach (['/media', '/run/media'] as $base) {
        if (!is_dir($base)) continue;
        foreach (scandir($base) as $user) {
          $userPath = $base . '/' . $user;
          if (!is_dir($userPath)) continue;
          foreach (scandir($userPath) as $d) {
            $p = $userPath . '/' . $d;
            if (is_dir($p)) {
              $drives[] = ['path' => $p, 'name' => $d, 'size' => 0, 'available' => 0];
            }
          }
        }
      }
    }

    return $drives;
  }

  /**
   * Scanner les dossiers candidats (format NNNNNN) sur un lecteur
   */
  public static function scanFolders(string $drivePath): array
  {
    $folders = [];
    $entries = @scandir($drivePath);
    if ($entries === false) return [];

    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..') continue;
      $fullPath = $drivePath . DIRECTORY_SEPARATOR . $entry;
      if (!is_dir($fullPath)) continue;
      // if (!preg_match('/^\d{6}$/', $entry)) continue;

      $items = @scandir($fullPath) ?: [];
      $subDirs = array_filter($items, fn($i) => is_dir($fullPath . DIRECTORY_SEPARATOR . $i));
      $subLow = array_map('strtolower', $subDirs);

      // Compter fichiers récursivement
      $fileCount = 0;
      $totalSize = 0;
      $extCount = [];
      self::countFilesRecursive($fullPath, $fileCount, $totalSize, $extCount);

      $hasFiles = $fileCount > 0;
      $absSubDir = null;
      foreach ($subDirs as $sd) {
        if (preg_match('/^abs/i', $sd)) {
          $absSubDir = $sd;
          break;
        }
      }
      $isAbsent = !$hasFiles && $absSubDir !== null;
      $isNonConforme = !$hasFiles && $absSubDir === null;
      $hasFraud = in_array('_usbwatcher', $subLow);

      if ($isAbsent || $isNonConforme) {
        $folders[] = [
          'candidateNumber' => Labels::formatCandNum($entry),
          'rawNumber'       => $entry,
          'absent'          => $isAbsent,
          'nonConforme'     => $isNonConforme,
          'absentNonConforme' => false,
          'fraud'           => false,
          'sizeAlert'       => false,
          'fileCount'       => 0,
          'totalSize'       => 0,
          'topExtensions'   => [],
          'path'            => $fullPath,
          'note'            => '',
        ];
        continue;
      }

      arsort($extCount);
      $topExtensions = array_slice($extCount, 0, 6, true);
      $topExtensions = array_map(fn($ext, $count) => ['ext' => $ext, 'count' => $count], array_keys($topExtensions), array_values($topExtensions));

      $sizeAlert = $totalSize > 5 * 1024 * 1024;
      $note = $hasFraud ? '/!\ Risque de fraude' : ($sizeAlert ? '/!\ Taille suspecte' : '');

      $folders[] = [
        'candidateNumber' => Labels::formatCandNum($entry),
        'rawNumber'       => $entry,
        'absent'          => false,
        'nonConforme'     => false,
        'absentNonConforme' => false,
        'fraud'           => $hasFraud,
        'sizeAlert'       => $sizeAlert,
        'fileCount'       => $fileCount,
        'totalSize'       => $totalSize,
        'topExtensions'   => $topExtensions,
        'path'            => $fullPath,
        'note'            => $note,
      ];
    }

    usort($folders, fn($a, $b) => strcmp($a['rawNumber'] ?? $a['candidateNumber'], $b['rawNumber'] ?? $b['candidateNumber']));
    return $folders;
  }

  /**
   * Compter les fichiers récursivement
   */
  private static function countFilesRecursive(string $dir, int &$fileCount, int &$totalSize, array &$extCount): void
  {
    $items = @scandir($dir);
    if ($items === false) return;

    foreach ($items as $item) {
      if (strtolower($item) === '_usbwatcher') continue;
      $path = $dir . DIRECTORY_SEPARATOR . $item;
      if (is_dir($path)) {
        self::countFilesRecursive($path, $fileCount, $totalSize, $extCount);
      } elseif (is_file($path)) {
        $fileCount++;
        $totalSize += filesize($path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'no-ext';
        $extCount[$ext] = ($extCount[$ext] ?? 0) + 1;
      }
    }
  }

  /**
   * Copie sécurisée avec vérification MD5
   */
  public static function copyFilesWithMD5(array $sourceFolders, string $destPath, ?callable $onProgress = null): array
  {
    $results = [];
    $totalFiles = 0;
    foreach ($sourceFolders as $f) {
      $totalFiles += $f['fileCount'] ?? 0;
    }
    $copiedFiles = 0;
    $errors = [];

    $parentDir = dirname($destPath);
    $tmpPath = $parentDir . DIRECTORY_SEPARATOR . '.tmp_bcm_' . time();

    try {
      if (!is_dir($tmpPath)) {
        mkdir($tmpPath, 0755, true);
      }

      foreach ($sourceFolders as $folder) {
        $candNum = $folder['rawNumber'] ?? $folder['candidateNumber'];
        $candTmp = $tmpPath . DIRECTORY_SEPARATOR . $candNum;

        if (!is_dir($candTmp)) {
          mkdir($candTmp, 0755, true);
        }

        $results[$candNum] = [
          'ok'      => true,
          'absent'  => $folder['absent'] ?? false,
          'nonConforme' => $folder['nonConforme'] ?? false,
          'files'   => [],
          'errors'  => [],
        ];

        self::copyDirRecursive($folder['path'], $candTmp, $candNum, $results, $totalFiles, $copiedFiles, $errors, $onProgress);

        // Marker fraude
        if (!empty($folder['fraud'])) {
          try {
            file_put_contents($candTmp . DIRECTORY_SEPARATOR . '.bcm_fraud', 'fraude_detectee');
          } catch (\Throwable $e) {
          }
        }
      }

      if (!empty($errors)) {
        self::removeDir($tmpPath);
        throw new \Exception("Erreurs MD5 — copie annulée :\n" . implode("\n", $errors));
      }

      // Renommage atomique
      $destParent = dirname($destPath);
      if (!is_dir($destParent)) {
        mkdir($destParent, 0755, true);
      }
      rename($tmpPath, $destPath);
    } catch (\Throwable $e) {
      try {
        self::removeDir($tmpPath);
      } catch (\Throwable $ex) {
      }
      throw $e;
    }

    return $results;
  }

  private static function copyDirRecursive(
    string $src,
    string $dst,
    string $candNum,
    array &$results,
    int $totalFiles,
    int &$copiedFiles,
    array &$errors,
    ?callable $onProgress
  ): void {
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    $items = @scandir($src);
    if ($items === false) return;

    foreach ($items as $item) {
      $srcPath = $src . DIRECTORY_SEPARATOR . $item;
      $dstPath = $dst . DIRECTORY_SEPARATOR . $item;
      if (is_dir($srcPath)) {
        self::copyDirRecursive($srcPath, $dstPath, $candNum, $results, $totalFiles, $copiedFiles, $errors, $onProgress);
      } elseif (is_file($srcPath)) {
        copy($srcPath, $dstPath);
        $md5src = md5_file($srcPath);
        $md5dst = md5_file($dstPath);
        if ($md5src !== $md5dst) {
          copy($srcPath, $dstPath);
          $md5retry = md5_file($dstPath);
          if ($md5src !== $md5retry) {
            $results[$candNum]['ok'] = false;
            $results[$candNum]['errors'][] = $item;
            $errors[] = "MD5 mismatch: $item ($candNum)";
          }
        }
        $results[$candNum]['files'][] = $item;
        $copiedFiles++;

        if ($onProgress && $totalFiles > 0) {
          $onProgress([
            'copied'    => $copiedFiles,
            'total'     => $totalFiles,
            'percentage' => (int)round($copiedFiles / $totalFiles * 100),
            'current'   => $item,
          ]);
        }
      }
    }
  }

  /**
   * Scanner le dossier archive (séances/labos)
   */
  public static function scanArchive(string $basePath): array
  {
    if (empty($basePath) || !is_dir($basePath)) return [];

    $seances = [];
    $seanceDirs = @scandir($basePath);
    if ($seanceDirs === false) return [];

    $seanceDirs = array_filter($seanceDirs, fn($d) => preg_match('/^S[eé]ance[-_]\d+$/i', $d));
    usort($seanceDirs, fn($a, $b) => (int)preg_replace('/\D/', '', $a) - (int)preg_replace('/\D/', '', $b));

    foreach ($seanceDirs as $sd) {
      $sPath = $basePath . DIRECTORY_SEPARATOR . $sd;
      if (!is_dir($sPath)) continue;
      $sNum = (int)preg_replace('/\D/', '', $sd);

      $labos = [];
      $laboDirs = @scandir($sPath);
      if ($laboDirs === false) continue;
      $laboDirs = array_filter($laboDirs, fn($d) => preg_match('/^Labo[-_]\d+$/i', $d));
      usort($laboDirs, fn($a, $b) => (int)preg_replace('/\D/', '', $a) - (int)preg_replace('/\D/', '', $b));

      foreach ($laboDirs as $ld) {
        $lPath = $sPath . DIRECTORY_SEPARATOR . $ld;
        if (!is_dir($lPath)) continue;
        $lNum = (int)preg_replace('/\D/', '', $ld);

        $labItems = @scandir($lPath) ?: [];
        $candDirs = array_filter($labItems, function ($d) use ($lPath) {
          if (!preg_match('/^\d{6}$/', $d)) return false;
          return is_dir($lPath . DIRECTORY_SEPARATOR . $d);
        });
        sort($candDirs);

        $totalFiles = 0;
        $fraudCount = 0;
        $absentCount = 0;
        $candidates = [];

        foreach ($candDirs as $cd) {
          $cPath = $lPath . DIRECTORY_SEPARATOR . $cd;
          $cItems = @scandir($cPath) ?: [];
          $subDirs = array_filter($cItems, fn($i) => is_dir($cPath . DIRECTORY_SEPARATOR . $i));
          $subLow = array_map('strtolower', $subDirs);

          $cFileCount = 0;
          self::countFilesRecursive($cPath, $cFileCount, $tmpSize = 0, $tmpExt = []);

          $absSubDir = null;
          foreach ($subDirs as $sd2) {
            if (preg_match('/^abs/i', $sd2)) {
              $absSubDir = $sd2;
              break;
            }
          }
          $hasFiles = $cFileCount > 0;
          $isAbsent = !$hasFiles && $absSubDir !== null;
          $isNonConforme = !$hasFiles && $absSubDir === null;
          $hasFraud = in_array('_usbwatcher', $subLow) || in_array('.bcm_fraud', $cItems);

          $fc = 0;
          $fSize = 0;
          $extCount = [];
          if (!$isAbsent) {
            self::countFilesRecursive($cPath, $fc, $fSize, $extCount);
          }
          arsort($extCount);
          $topExtensions = array_slice($extCount, 0, 6, true);
          $topExtensions = array_map(fn($ext, $count) => ['ext' => $ext, 'count' => $count], array_keys($topExtensions), array_values($topExtensions));

          $totalFiles += $fc;
          if ($isAbsent) $absentCount++;
          if ($hasFraud) $fraudCount++;

          $candidates[] = [
            'candidateNumber' => Labels::formatCandNum($cd),
            'rawNumber'       => $cd,
            'absent'          => $isAbsent,
            'nonConforme'     => $isNonConforme,
            'absentNonConforme' => false,
            'fraud'           => $hasFraud,
            'fileCount'       => $fc,
            'totalSize'       => $fSize,
            'topExtensions'   => $isAbsent ? [] : $topExtensions,
          ];
        }

        $pdfFiles  = array_values(array_filter($labItems, fn($f) => strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'pdf'));
        $xlsxFiles = array_values(array_filter($labItems, fn($f) => strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'xlsx'));

        $labos[] = [
          'name'        => $ld,
          'labo'        => $lNum,
          'path'        => $lPath,
          'candidates'  => $candidates,
          'totalFiles'  => $totalFiles,
          'absent'      => $absentCount,
          'fraud'       => $fraudCount,
          'pdfFiles'    => $pdfFiles,
          'xlsxFiles'   => $xlsxFiles,
        ];
      }

      $seances[] = [
        'name'    => $sd,
        'seance'  => $sNum,
        'path'    => $sPath,
        'labos'   => $labos,
      ];
    }

    return $seances;
  }

  /**
   * Lister les sous-dossiers d'un lecteur contenant des candidats
   */
  public static function listDriveSubfolders(string $drivePath): array
  {
    $CAND_RE = '/^\d{6}$/';
    $SKIP = ['system volume information', '$recycle.bin', '_usbwatcher', 'recycler', 'recovery', 'boot', 'efi'];

    $hasCandidates = function (string $dir) use ($CAND_RE) {
      $entries = @scandir($dir);
      if ($entries === false) return false;
      foreach ($entries as $e) {
        if (!preg_match($CAND_RE, $e)) continue;
        if (is_dir($dir . DIRECTORY_SEPARATOR . $e)) return true;
      }
      return false;
    };

    $countCandidates = function (string $dir) use ($CAND_RE) {
      $count = 0;
      $entries = @scandir($dir);
      if ($entries === false) return 0;
      foreach ($entries as $e) {
        if (!preg_match($CAND_RE, $e)) continue;
        if (is_dir($dir . DIRECTORY_SEPARATOR . $e)) $count++;
      }
      return $count;
    };

    $entries = @scandir($drivePath) ?: [];
    $subs = [];

    foreach ($entries as $e) {
      if (preg_match('/^\./', $e)) continue;
      if (in_array(strtolower($e), $SKIP)) continue;
      if (preg_match($CAND_RE, $e)) continue;
      $fullPath = $drivePath . DIRECTORY_SEPARATOR . $e;
      if (!is_dir($fullPath)) continue;
      if (!$hasCandidates($fullPath)) continue;

      $subs[] = [
        'name'  => $e,
        'count' => $countCandidates($fullPath),
      ];
    }

    $rootCount = $countCandidates($drivePath);

    return ['subs' => $subs, 'rootCount' => $rootCount];
  }

  /**
   * Supprimer des dossiers source sur la clé USB
   */
  public static function deleteSourceFolders(string $drivePath, array $folderNumbers): array
  {
    $deleted = [];
    $failed = [];
    foreach ($folderNumbers as $num) {
      try {
        $path = $drivePath . DIRECTORY_SEPARATOR . $num;
        self::removeDir($path);
        $deleted[] = $num;
      } catch (\Throwable $e) {
        $failed[] = ['folder' => $num, 'error' => $e->getMessage()];
      }
    }
    return ['deleted' => $deleted, 'failed' => $failed];
  }

  /**
   * Suppression récursive d'un dossier
   */
  private static function removeDir(string $dir): void
  {
    if (!is_dir($dir)) return;
    $items = @scandir($dir) ?: [];
    foreach ($items as $item) {
      if ($item === '.' || $item === '..') continue;
      $path = $dir . DIRECTORY_SEPARATOR . $item;
      if (is_dir($path)) {
        self::removeDir($path);
      } else {
        unlink($path);
      }
    }
    rmdir($dir);
  }
}
