<?php
/**
 * BacArchive — Gestionnaire de paramètres (PHP)
 */

namespace BacArchive;

class SettingsManager
{
    private string $settingsDir;
    private string $settingsFile;

    public const DEFAULTS = [
        'annee'       => 0, // set in constructor
        'lycee'       => '',
        'matiere'     => 'STI',
        'section'     => 'SI',
        'seance'      => 1,
        'labo'        => 1,
        'destBase'    => '',
        'nbQuestions' => 15,
    ];

    public function __construct()
    {
        $this->settingsDir = $this->getSettingsDir();
        $this->settingsFile = $this->settingsDir . DIRECTORY_SEPARATOR . 'settings.ini';
    }

    private function getSettingsDir(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $appData = $_ENV['APPDATA'] ?? getenv('APPDATA');
            return $appData . DIRECTORY_SEPARATOR . 'BacArchive';
        }
        return $_SERVER['HOME'] . DIRECTORY_SEPARATOR . '.BacArchive';
    }

    public function load(): array
    {
        $defaults = self::DEFAULTS;
        $defaults['annee'] = (int)(new \DateTime())->format('Y');

        if (!file_exists($this->settingsFile)) {
            return $defaults;
        }

        $raw = $this->fromIni(file_get_contents($this->settingsFile));

        return [
            'annee'       => (int)($raw['annee'] ?? 0) ?: $defaults['annee'],
            'lycee'       => $raw['lycee'] ?? $defaults['lycee'],
            'matiere'     => $raw['matiere'] ?? $defaults['matiere'],
            'section'     => $raw['section'] ?? $defaults['section'],
            'seance'      => (int)($raw['seance'] ?? 0) ?: $defaults['seance'],
            'labo'        => (int)($raw['labo'] ?? 0) ?: $defaults['labo'],
            'destBase'    => $raw['destBase'] ?? $defaults['destBase'],
            'nbQuestions' => (int)($raw['nbQuestions'] ?? 0) ?: $defaults['nbQuestions'],
        ];
    }

    public function save(array $settings): void
    {
        if (!is_dir($this->settingsDir)) {
            mkdir($this->settingsDir, 0755, true);
        }
        file_put_contents($this->settingsFile, $this->toIni($settings));
    }

    private function toIni(array $obj): string
    {
        $lines = ['[Params]'];
        foreach ($obj as $k => $v) {
            if ($k === 'fontSize') continue;
            $lines[] = "$k=$v";
        }
        return implode("\r\n", $lines) . "\r\n";
    }

    private function fromIni(string $text): array
    {
        $result = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim($line, "\r\n");
            if (preg_match('/^([^=;#\[]+)=(.*)$/', $line, $m)) {
                $result[trim($m[1])] = trim($m[2]);
            }
        }
        return $result;
    }

    public function getSettingsPath(): string
    {
        return $this->settingsFile;
    }
}