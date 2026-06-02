<?php
/**
 * BacArchive — Gestionnaire de la configuration des examens
 * Lit/écrit le fichier examconfig.ini qui décrit les jours, examens et séances
 *
 * Format du fichier examconfig.ini :
 *   [examens]
 *   nbre_jours=N
 *   [examens.jour_D]
 *   nb_examens=N
 *   [examens.jour_D.examen_E]
 *   section=...
 *   examen=...
 *   date=YYYY-MM-DD
 *   nb_seances=N
 *   [examens.jour_D.examen_E.seance_S]
 *   date_deb=HH:MM
 *   duree=N
 */

namespace BacArchive;

class ExamConfigManager
{
  private string $configFile;

  public function __construct(?string $configFile = null)
  {
    $this->configFile = $configFile ?? (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'examconfig.ini');
  }

  public function getConfigFile(): string
  {
    return $this->configFile;
  }

  /**
   * Charge la configuration et la retourne sous forme de tableau hiérarchique.
   * Si le fichier n'existe pas, retourne une configuration par défaut vide.
   */
  public function load(): array
  {
    if (!file_exists($this->configFile)) {
      return $this->defaultConfig();
    }

    $content = file_get_contents($this->configFile);
    $sections = $this->parseSections($content);

    $nbreJours = (int) ($sections['examens']['nbre_jours'] ?? 0);
    $jours = [];

    for ($d = 1; $d <= $nbreJours; $d++) {
      $jourKey = "examens.jour_{$d}";
      $nbExamens = (int) ($sections[$jourKey]['nb_examens'] ?? 0);
      $examens = [];

      for ($e = 1; $e <= $nbExamens; $e++) {
        $examKey = "{$jourKey}.examen_{$e}";
        $nbSeances = (int) ($sections[$examKey]['nb_seances'] ?? 0);
        $seances = [];

        for ($s = 1; $s <= $nbSeances; $s++) {
          $seanceKey = "{$examKey}.seance_{$s}";
          $seances[] = [
            'date_deb' => $sections[$seanceKey]['date_deb'] ?? '08:00',
            'duree' => (int) ($sections[$seanceKey]['duree'] ?? 60),
          ];
        }

        $examens[] = [
          'section' => $sections[$examKey]['section'] ?? '',
          'examen' => $sections[$examKey]['examen'] ?? '',
          'date' => $sections[$examKey]['date'] ?? '',
          'nb_seances' => $nbSeances,
          'seances' => $seances,
        ];
      }

      $jours[] = [
        'nb_examens' => $nbExamens,
        'examens' => $examens,
      ];
    }

    return [
      'nbre_jours' => $nbreJours,
      'jours' => $jours,
    ];
  }

  /**
   * Sauvegarde la configuration dans le fichier examconfig.ini
   */
  public function save(array $config): bool
  {
    $nbreJours = max(0, (int) ($config['nbre_jours'] ?? 0));
    $jours = $config['jours'] ?? [];

    $lines = [];
    $lines[] = '[examens]';
    $lines[] = "nbre_jours={$nbreJours}";
    $lines[] = '';

    for ($d = 0; $d < $nbreJours; $d++) {
      $jour = $jours[$d] ?? ['nb_examens' => 0, 'examens' => []];
      $nbExamens = max(0, (int) ($jour['nb_examens'] ?? 0));
      $examens = $jour['examens'] ?? [];

      $lines[] = "[examens.jour_" . ($d + 1) . "]";
      $lines[] = "nb_examens={$nbExamens}";
      $lines[] = '';

      for ($e = 0; $e < $nbExamens; $e++) {
        $exam = $examens[$e] ?? [];
        $nbSeances = max(0, (int) ($exam['nb_seances'] ?? 0));
        $seances = $exam['seances'] ?? [];

        $lines[] = "[examens.jour_" . ($d + 1) . ".examen_" . ($e + 1) . "]";
        $lines[] = 'section=' . $this->escapeValue((string) ($exam['section'] ?? ''));
        $lines[] = 'examen=' . $this->escapeValue((string) ($exam['examen'] ?? ''));
        $lines[] = 'date=' . $this->escapeValue((string) ($exam['date'] ?? ''));
        $lines[] = "nb_seances={$nbSeances}";
        $lines[] = '';

        for ($s = 0; $s < $nbSeances; $s++) {
          $seance = $seances[$s] ?? [];
          $lines[] = "[examens.jour_" . ($d + 1) . ".examen_" . ($e + 1) . ".seance_" . ($s + 1) . "]";
          $lines[] = 'date_deb=' . $this->escapeValue((string) ($seance['date_deb'] ?? '08:00'));
          $lines[] = 'duree=' . max(0, (int) ($seance['duree'] ?? 60));
          $lines[] = '';
        }
      }
    }

    // Nettoyer les lignes vides en fin de fichier
    $content = rtrim(implode("\n", $lines), "\n") . "\n";
    return file_put_contents($this->configFile, $content) !== false;
  }

  /**
   * Configuration par défaut (un seul jour avec un examen exemple).
   */
  public function defaultConfig(): array
  {
    return [
      'file' => $this->configFile,
      'exists' => false,
      'nbre_jours' => 0,
      'jours' => [],
    ];
  }

  /**
   * Échappe une valeur pour le format INI.
   */
  private function escapeValue(string $v): string
  {
    return str_replace(["\n", "\r"], ' ', $v);
  }

  /**
   * Parseur INI personnalisé qui gère les noms de sections avec des points.
   * Retourne [sectionKey => [key => value, ...]]
   */
  private function parseSections(string $content): array
  {
    $result = [];
    $current = null;
    $lines = preg_split('/\r\n|\r|\n/', $content);

    foreach ($lines as $rawLine) {
      $line = trim($rawLine);
      if ($line === '' || str_starts_with($line, ';') || str_starts_with($line, '#')) {
        continue;
      }
      if (preg_match('/^\[(.+)\]$/', $line, $m)) {
        $current = trim($m[1]);
        if (!isset($result[$current])) {
          $result[$current] = [];
        }
        continue;
      }
      if ($current === null) {
        continue;
      }
      if (preg_match('/^([^=]+)=(.*)$/', $line, $m)) {
        $key = trim($m[1]);
        $val = trim($m[2]);
        // Retirer les commentaires en fin de ligne
        if (preg_match('/^(.*?)\s*[;#].*$/', $val, $m2)) {
          $val = trim($m2[1]);
        }
        $result[$current][$key] = $val;
      }
    }

    return $result;
  }
}
