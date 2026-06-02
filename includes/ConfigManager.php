<?php
/**
 * BacArchive — Gestionnaire de config INI USB (PHP)
 */

namespace BacArchive;

class ConfigManager
{
    public static function readBacConfig(string $configPath): array
    {
        if (!file_exists($configPath)) {
            return [
                'Matiere'          => 'Unknown',
                'Bac'              => (int)(new \DateTime())->format('Y'),
                'Labo'             => 1,
                'Seance'           => 1,
                'GuiPart3Extend'   => 0,
            ];
        }

        $ini = parse_ini_file($configPath, true);
        if ($ini === false) {
            return ['Labo' => 1, 'Seance' => 1];
        }

        return $ini['Params'] ?? $ini;
    }

    public static function saveBacConfig(string $configPath, array $config): void
    {
        $content = "[Params]\n";
        foreach ($config as $k => $v) {
            $content .= "$k=$v\n";
        }
        file_put_contents($configPath, $content);
    }
}