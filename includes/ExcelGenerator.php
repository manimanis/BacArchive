<?php
/**
 * BacArchive — Générateur Grille Excel (PHP)
 * Utilise PhpSpreadsheet
 */

namespace BacArchive;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Protection;

class ExcelGenerator
{
    private const C = [
        'TITLE_TEXT'  => '1A3A5C',
        'HDR_TEXT'    => '1A3A5C',
        'BORDER'      => '7FA8C8',
        'BORDER_LT'   => 'CDDAEA',
        'BORDER_GRAY' => 'BBBBBB',
        'WHITE'       => 'FFFFFF',
        'TITLE_BG'    => 'EBF3FA',
        'HDR_BG'      => 'D6E8F5',
        'INFO_BG'     => 'FFFFFF',
        'QHD_BG'      => 'FAFCFE',
        'ROW_E'       => 'F4F8FC',
        'ROW_O'       => 'FFFFFF',
        'ABS_BG'      => 'EEEEEE',
        'ABS_TEXT'    => '333333',
        'ABS_NOTE'    => '555555',
        'TOT_BG'      => 'EFF8E8',
        'TOT_TEXT'    => '1B5E20',
    ];

    public static function generate(array $data, string $destFolder): string
    {
        $now = new \DateTime();
        $seance      = $data['seance'] ?? 1;
        $labo        = $data['labo'] ?? 1;
        $annee       = $data['annee'] ?? (int)$now->format('Y');
        $matiere     = $data['matiere'] ?? '';
        $section     = $data['section'] ?? '';
        $lycee       = $data['lycee'] ?? '';
        $folders     = $data['folders'] ?? [];
        $nbQuestions = $data['nbQuestions'] ?? 15;

        $dateStr  = $now->format('Y-m-d');
        $timeStr  = $now->format('H') . 'h' . $now->format('i');
        $dateLong = Labels::formatDateFr($now);
        $ml = Labels::getMatiereLabel($matiere);
        $sl = Labels::getSectionLabel($section);
        $fileName = "GrilleEval_Seance-{$seance}_Labo-{$labo}__{$now->format('Y-m-d')}.xlsx";

        if (!is_dir($destFolder)) {
            mkdir($destFolder, 0755, true);
        }
        $outPath = $destFolder . DIRECTORY_SEPARATOR . $fileName;

        // Trier les candidats
        $candidates = array_filter($folders, fn($f) => !empty($f['candidateNumber']));
        usort($candidates, fn($a, $b) => strcmp($a['candidateNumber'], $b['candidateNumber']));
        $candidates = array_map(fn($f) => [
            'num'    => Labels::formatCandNum($f['candidateNumber']),
            'absent' => !empty($f['absent']),
        ], $candidates);

        $nbCands = count($candidates);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getCreator('BacArchive');
        $spreadsheet->getProperties()
            ->setCreator('BacArchive')
            ->setCreated(new \DateTime());

        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle("Seance-{$seance} Labo-{$labo}");

        // Page setup
        $ws->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(1)
            ->setPaperSize(PageSetup::PAPERSIZE_A4);
        $ws->setShowGridlines(false);

        // Structure colonnes
        $CA = 1; $CB = 2; $CQ = 3;
        $CQL = 2 + $nbQuestions;
        $CT = 3 + $nbQuestions;
        $CD = 4 + $nbQuestions;
        $LAST = $CD;

        $ws->getColumnDimensionByIndex($CA)->setWidth(4);
        $ws->getColumnDimensionByIndex($CB)->setWidth(13);
        for ($c = $CQ; $c <= $CQL; $c++) {
            $ws->getColumnDimensionByIndex($c)->setWidth(7);
        }
        $ws->getColumnDimensionByIndex($CT)->setWidth(9);
        $ws->getColumnDimensionByIndex($CD)->setWidth(13);

        // Helper functions
        $setCell = function ($row, $col, $value, $bold = false, $size = 10, $color = '000000', $italic = false, $align = 'center') use ($ws) {
            $cell = $ws->getCellByRowAndColumn($col, $row);
            $cell->setValue($value);
            $cell->getFont()->setBold($bold)->setSize($size)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($color))->setItalic($italic);
            $cell->getAlignment()->setHorizontal($align)->setVertical('center')->setWrapText(false);
            return $cell;
        };

        $setBorder = function ($row, $col, $style = 'thin', $color = self::C['BORDER']) use ($ws) {
            $cell = $ws->getCellByRowAndColumn($col, $row);
            $border = new \PhpOffice\PhpSpreadsheet\Style\Border();
            $border->setBorderStyle($style)->getColor()->setARGB('FF' . $color);
            $cell->getBorders()->getTop()->copyFrom($border);
            $cell->getBorders()->getBottom()->copyFrom($border);
            $cell->getBorders()->getLeft()->copyFrom($border);
            $cell->getBorders()->getRight()->copyFrom($border);
        };

        $setFill = function ($row, $col, $color) use ($ws) {
            $cell = $ws->getCellByRowAndColumn($col, $row);
            $fill = new Fill();
            $fill->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . $color);
            $cell->setFill($fill);
        };

        $LAST = $CD;

        // Ligne 1 : Titre
        $ws->mergeCellsByRowAndColumn(1, $CA, 1, $LAST);
        $cTitle = $setCell(1, $CA, "Bac Pratique {$annee}  —  {$ml}" . ($section ? "  /  {$sl}" : ''), true, 13, self::C['TITLE_TEXT']);
        $setFill(1, $CA, self::C['TITLE_BG']);
        $setBorder(1, $CA);

        // Ligne 2 : Info
        $setCell(2, $CB, 'Séance :', true, 9, self::C['HDR_TEXT'], false, 'right');
        $setCell(2, $CQ, $seance, true, 10, self::C['TITLE_TEXT']);
        $setCell(2, $CQ + 1, 'Labo :', true, 9, self::C['HDR_TEXT'], false, 'right');
        $setCell(2, $CQ + 2, $labo, true, 10, self::C['TITLE_TEXT']);
        $setCell(2, $CQ + 3, 'Date :', true, 9, self::C['HDR_TEXT'], false, 'right');
        $ws->mergeCellsByRowAndColumn(2, $CQ + 4, 2, min($CQ + 7, $CT - 1));
        $setCell(2, $CQ + 4, $dateLong, true, 10, self::C['TITLE_TEXT'], false, 'left');
        if ($lycee && $CT <= $LAST) {
            $ws->mergeCellsByRowAndColumn(2, $CT, 2, $LAST);
            $setCell(2, $CT, $lycee, false, 9, '555555', true, 'right');
        }

        // Ligne 4 : En-tête colonnes
        $hdrRow = function ($row) use ($CA, $CB, $CQ, $CQL, $CT, $CD, $LAST, $nbQuestions, $ws, $setCell, $setBorder, $setFill) {
            $setCell($row, $CA, '#', true, 9, self::C['HDR_TEXT']);
            $setCell($row, $CB, 'N° Inscription', true, 9, self::C['HDR_TEXT']);
            for ($q = 0; $q < $nbQuestions; $q++) {
                $setCell($row, $CQ + $q, 'Q' . ($q + 1), true, 9, self::C['HDR_TEXT']);
            }
            $setCell($row, $CT, 'Total /20', true, 8, self::C['HDR_TEXT']);
            $setCell($row, $CD, 'N° Inscription', true, 9, self::C['HDR_TEXT']);
            for ($c = $CA; $c <= $LAST; $c++) {
                $setFill($row, $c, self::C['HDR_BG']);
                $setBorder($row, $c);
            }
        };

        $hdrRow(4);

        // Ligne 5 : Intitulé
        for ($c = $CA; $c <= $LAST; $c++) {
            $setFill($row = 5, $c, self::C['QHD_BG']);
            $setBorder($row, $c);
        }
        $setCell(5, $CB, 'Intitulé de la question', false, 8, '555555', true, 'center');

        // Ligne 6 : Barème
        for ($c = $CA; $c <= $LAST; $c++) {
            $setFill($row = 6, $c, self::C['QHD_BG']);
            $setBorder($row, $c);
        }
        $setCell(6, $CB, 'Barème', true, 9, self::C['HDR_TEXT'], false, 'left');

        // Ligne 8 : En-tête candidats
        $hdrRow(8);

        // Lignes candidats
        $RS = 9;
        foreach ($candidates as $i => $cand) {
            $r = $RS + $i;
            $ia = $cand['absent'];
            $bg = $ia ? self::C['ABS_BG'] : ($i % 2 === 0 ? self::C['ROW_E'] : self::C['ROW_O']);
            $textColor = $ia ? self::C['ABS_TEXT'] : '222222';

            $ws->getRowDimension($r)->setRowHeight(24);

            for ($c = $CA; $c <= $LAST; $c++) {
                $setFill($r, $c, $bg);
                $setBorder($r, $c);
                $setCell($r, $c, '', false, 10, $textColor);
            }

            // # numéro
            $setCell($r, $CA, $i + 1, false, 8, $ia ? 'AAAAAA' : '999999');

            // N° Inscription
            $setCell($r, $CB, $cand['num'], true, 10, $ia ? self::C['ABS_TEXT'] : self::C['TITLE_TEXT']);

            // Cases notes Q1..Qn
            for ($q = 0; $q < $nbQuestions; $q++) {
                $setCell($r, $CQ + $q, $ia ? '' : null, false, $ia ? 8 : 10, $ia ? 'DDDDDD' : '222222');
            }

            // Total / Note
            if ($ia) {
                $setCell($r, $CT, 88.88, true, 10, self::C['ABS_NOTE'], true);
                $ws->getCellByRowAndColumn($CT, $r)->getNumberFormat()->setFormatCode('0.00');
                $setFill($r, $CT, self::C['ABS_BG']);
            } else {
                $setCell($r, $CT, null, true, 11, '000000');
                $setFill($r, $CT, self::C['TOT_BG']);
            }

            // N° Inscription droite
            $setCell($r, $CD, $cand['num'], true, 10, $ia ? self::C['ABS_TEXT'] : self::C['TITLE_TEXT']);
        }

        // Impression
        $ws->getPageSetup()->setScale(75);
        $ws->getPageMargins()->setLeft(0.3)->setRight(0.3)->setTop(0.4)->setBottom(0.4);

        // Sauvegarder
        $writer = new Xlsx($spreadsheet);
        $writer->save($outPath);

        return $outPath;
    }
}