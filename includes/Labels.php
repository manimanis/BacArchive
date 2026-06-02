<?php
/**
 * BacArchive — Constantes et helpers partagés (PHP)
 */

namespace BacArchive;

class Labels
{
    public const MATIERE_LABELS = [
        'STI'      => "Systèmes & Technologies de l'Informatique",
        'AlgoProg' => 'Algorithmique & Programmation',
        'Info'     => 'Informatique',
    ];

    public const SECTION_LABELS = [
        'SI'      => "Section Sciences de l'Informatique (S.I.)",
        'Sc-TM'   => 'Sections Scientifiques (Sc . T . M)',
        'EcoGest' => 'Section Économie et Gestion',
        'Lettres' => 'Section Lettres',
        'Sport'   => 'Section Sport',
    ];

    public const SECTIONS_EXT = [
        'SI - STI'   => 'sql • html • css • js • php',
        'SI - Algo'  => 'py ou ipynb • ui • dat • txt',
        'Sc • T • M' => 'py ou ipynb • ui',
        'Éco & Gest' => 'accdb • xlsx • csv • py ou ipynb',
        'Lettres'    => 'xlsx • docx',
        'Sport'      => 'xlsx • pptx',
    ];

    public const DAYS_FR = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    public const MONTHS_FR = [
        'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
        'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
    ];

    public static function getMatiereLabel(string $v): string
    {
        return self::MATIERE_LABELS[$v] ?? $v;
    }

    public static function getSectionLabel(string $v): string
    {
        return self::SECTION_LABELS[$v] ?? $v;
    }

    public static function pad2(int $n): string
    {
        return str_pad((string)$n, 2, '0', STR_PAD_LEFT);
    }

    public static function formatCandNum(string $n): string
    {
        $s = str_replace('-', '', $n);
        if (strlen($s) === 6) {
            return substr($s, 0, 3) . '-' . substr($s, 3);
        }
        return $n;
    }

    public static function formatBytes(int $bytes): string
    {
        if (!$bytes) return '0 o';
        $k = 1024;
        $units = ['o', 'Ko', 'Mo', 'Go'];
        $i = (int)floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 1) . ' ' . $units[$i];
    }

    public static function formatDateFr(?\DateTime $date = null): string
    {
        $date ??= new \DateTime();
        return self::DAYS_FR[(int)$date->format('w')] . ' '
            . $date->format('d') . ' '
            . self::MONTHS_FR[(int)$date->format('n') - 1] . ' '
            . $date->format('Y');
    }
}