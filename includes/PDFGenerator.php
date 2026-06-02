<?php
/**
 * BacArchive — Générateur PDF (PHP)
 * Utilise mPDF pour la génération de rapports PDF
 */

namespace BacArchive;

class PDFGenerator
{
    private const C = [
        'hdrBorder'  => '#668EBD',
        'hdrBg'      => '#4483B6',
        'hdrText'    => '#FFFFFF',
        'rowStroke'  => '#80AACC',
        'rowEven'    => '#F0F4F8',
        'rowOdd'     => '#FFFFFF',
        'rowAbsent'  => '#FFF8E1',
        'rowFraud'   => '#FDECEC',
        'textNormal' => '#111111',
        'textAbsent' => '#888888',
        'textFraud'  => '#AA0000',
        'textAlert'  => '#CC4400',
        'secBg1'     => '#E8EFF7',
        'secBg2'     => '#F5F8FC',
        'footerText' => '#999999',
        'statText'   => '#223355',
    ];

    public static function generate(array $data, string $destFolder): string
    {
        $now = new \DateTime();
        $matiere  = $data['matiere'] ?? '';
        $section  = $data['section'] ?? '';
        $annee    = $data['annee'] ?? (int)$now->format('Y');
        $labo     = $data['labo'] ?? 1;
        $seance   = $data['seance'] ?? 1;
        $lycee    = $data['lycee'] ?? '';
        $folders  = $data['folders'] ?? [];

        $dateStr  = $now->format('Y-m-d');
        $dateLong = Labels::formatDateFr($now);
        $timeStr  = $now->format('H') . 'h' . $now->format('i');
        $fileName = "Rapport__Seance-{$seance}_Labo-{$labo}__{$now->format('Y-m-d')}.pdf";

        if (!is_dir($destFolder)) {
            mkdir($destFolder, 0755, true);
        }
        $pdfPath = $destFolder . DIRECTORY_SEPARATOR . $fileName;

        $matiereLabel = Labels::getMatiereLabel($matiere);
        $sectionLabel = Labels::getSectionLabel($section);
        $subtitle = $section ? "$matiereLabel - $sectionLabel" : $matiereLabel;

        // Construire le HTML pour mPDF
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<style>
            body { font-family: sans-serif; font-size: 10pt; margin: 0; padding: 0; }
            .header-box { border: 1.5px solid ' . self::C['hdrBorder'] . '; border-radius: 8px; padding: 12px; margin: 20px 42px; text-align: center; }
            .header-title { font-size: 16pt; font-weight: bold; color: ' . self::C['textNormal'] . '; }
            .header-subtitle { font-size: 11pt; color: ' . self::C['textNormal'] . '; margin-top: 4px; }
            .header-date { font-size: 10pt; color: ' . self::C['textNormal'] . '; margin-top: 4px; }
            .header-session { font-size: 13pt; font-weight: bold; color: ' . self::C['textNormal'] . '; margin-top: 6px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10pt; }
            th { background: ' . self::C['hdrBg'] . '; color: ' . self::C['hdrText'] . '; padding: 5px 6px; text-align: left; font-size: 9pt; border: 0.5px solid ' . self::C['hdrBorder'] . '; }
            td { padding: 4px 6px; border-bottom: 0.3px solid ' . self::C['rowStroke'] . '; }
            tr.even td { background: ' . self::C['rowEven'] . '; }
            tr.odd td { background: ' . self::C['rowOdd'] . '; }
            tr.absent td { background: ' . self::C['rowAbsent'] . '; color: ' . self::C['textAbsent'] . '; }
            tr.fraud td { background: ' . self::C['rowFraud'] . '; color: ' . self::C['textFraud'] . '; }
            .nb { text-align: center; }
            .right { text-align: right; }
            .num { font-weight: bold; font-family: monospace; color: ' . self::C['textNormal'] . '; }
            .exts { font-size: 9pt; color: #555; }
            .alert { color: ' . self::C['textAlert'] . '; font-weight: bold; }
            .footer { text-align: center; font-size: 7.5pt; color: ' . self::C['footerText'] . '; margin-top: 20px; }
            .stats { text-align: center; font-size: 9.5pt; font-weight: bold; color: ' . self::C['statText'] . '; margin-top: 10px; }
            .stats-detail { text-align: center; font-size: 9pt; color: ' . self::C['statText'] . '; margin-top: 4px; }
            .note-text { font-style: italic; font-size: 9pt; color: #333; margin-top: 10px; }
            .sec-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .sec-table td { padding: 3px 6px; font-size: 8pt; border: 0.3px solid ' . self::C['rowStroke'] . '; }
            .sec-table .sec-label { font-weight: bold; width: 35%; }
            .sec-table tr.bg1 td { background: ' . self::C['secBg1'] . '; }
            .sec-table tr.bg2 td { background: ' . self::C['secBg2'] . '; }
            .disclaimer { text-align: center; font-style: italic; font-size: 8pt; color: #666; margin-top: 6px; }
        </style></head><body>';

        // En-tête
        $html .= '<div class="header-box">';
        $html .= '<div class="header-title">Bac Pratique ' . $annee . '</div>';
        $html .= '<div class="header-subtitle">' . htmlspecialchars($subtitle) . '</div>';
        $dateLine = $lycee ? htmlspecialchars($lycee) . '  —  ' . $dateLong : $dateLong;
        $html .= '<div class="header-date">' . $dateLine . '</div>';
        $html .= '<div class="header-session">SÉANCE-' . $seance . ' • LABO-' . $labo . '</div>';
        $html .= '</div>';

        // Tableau
        $html .= '<table>';
        $html .= '<thead><tr>';
        $html .= '<th style="width:30px">#</th>';
        $html .= '<th style="width:80px">N° Ins.</th>';
        $html .= '<th style="width:40px" class="nb">Files</th>';
        $html .= '<th>Nombre de Fichiers / Type (top 6)</th>';
        $html .= '<th style="width:70px" class="right">Taille</th>';
        $html .= '<th style="width:80px">Remarques</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($folders as $i => $f) {
            $isEven = $i % 2 === 0;
            $isAbsent = !empty($f['absent']);
            $isFraud = !empty($f['fraud']);
            $isNC = !empty($f['nonConforme']);
            $rowClass = $isFraud ? 'fraud' : ($isAbsent ? 'absent' : ($isEven ? 'even' : 'odd'));

            $candNum = Labels::formatCandNum($f['candidateNumber'] ?? '');

            $html .= '<tr class="' . $rowClass . '">';
            $html .= '<td class="nb">' . ($i + 1) . '</td>';
            $html .= '<td class="num">' . htmlspecialchars($candNum) . '</td>';

            if ($isNC) {
                $html .= '<td colspan="3" class="nb">⚠ Dossier vide</td>';
            } elseif ($isAbsent) {
                $html .= '<td colspan="3" class="nb" style="font-style:italic">Absent</td>';
            } else {
                $html .= '<td class="nb">' . ($f['fileCount'] ?? 0) . '</td>';
                $extStr = '';
                foreach ($f['topExtensions'] ?? [] as $e) {
                    if ($extStr) $extStr .= ' • ';
                    $extStr .= $e['count'] . ' ' . $e['ext'];
                }
                $html .= '<td class="exts">' . htmlspecialchars($extStr) . '</td>';
                $sizeClass = !empty($f['sizeAlert']) ? ' alert' : '';
                $html .= '<td class="right' . $sizeClass . '">' . Labels::formatBytes($f['totalSize'] ?? 0) . '</td>';
            }

            $note = '';
            if ($isFraud) $note = '! Cle USB';
            $html .= '<td>' . $note . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        // Statistiques
        $present = count(array_filter($folders, fn($f) => empty($f['absent'])));
        $absent = count(array_filter($folders, fn($f) => !empty($f['absent']) && empty($f['nonConforme'])));
        $nc = count(array_filter($folders, fn($f) => !empty($f['nonConforme'])));
        $fraud = count(array_filter($folders, fn($f) => !empty($f['fraud'])));
        $totF = array_sum(array_map(fn($f) => $f['fileCount'] ?? 0, $folders));
        $totS = array_sum(array_map(fn($f) => $f['totalSize'] ?? 0, $folders));

        $html .= '<p class="note-text">N.B. : Assurez-vous que les fichiers manquants ne se trouvent pas déjà sur l\'ordinateur du candidat.</p>';
        $html .= '<div class="stats">Présents : ' . $present . '   •   Absents : ' . $absent;
        if ($fraud > 0) $html .= '   •   Alertes fraude : ' . $fraud;
        $html .= '</div>';
        $html .= '<div class="stats-detail">Total fichiers : ' . $totF . '   •   Taille totale : ' . Labels::formatBytes($totS) . '   •   Heure : ' . $timeStr . '</div>';

        // Types de fichiers attendus
        $html .= '<div style="margin-top: 16px;">';
        $html .= '<div style="text-align:center; font-weight:bold; font-size:10pt; margin-bottom:6px;">Types de fichiers attendus par section</div>';
        $html .= '<table class="sec-table"><tbody>';
        $entries = Labels::SECTIONS_EXT;
        $i = 0;
        foreach ($entries as $sec => $ext) {
            $bgClass = ($i % 2 === 0) ? 'bg1' : 'bg2';
            if ($i % 2 === 0) $html .= '<tr class="' . $bgClass . '">';
            $html .= '<td class="sec-label">' . htmlspecialchars($sec) . '</td><td>' . htmlspecialchars($ext) . '</td>';
            if ($i % 2 === 1 || $i === count($entries) - 1) {
                if ($i % 2 === 0) $html .= '<td></td><td></td>';
                $html .= '</tr>';
            }
            $i++;
        }
        $html .= '</tbody></table>';
        $html .= '<p class="disclaimer">Veuillez noter que cette liste est indicative et sujette à modification.</p>';
        $html .= '</div>';

        // Pied de page
        $html .= '<div class="footer">Généré par BacArchive  —  ' . $dateLong . '  —  ' . $timeStr . '</div>';

        $html .= '</body></html>';

        // Générer le PDF avec mPDF
        $mpdf = new \Mpdf\Mpdf([
            'format'    => 'A4',
            'margin_left'   => 42,
            'margin_right'  => 42,
            'margin_top'    => 30,
            'margin_bottom' => 25,
        ]);
        $mpdf->SetTitle("Rapport Seance-{$seance} Labo-{$labo}");
        $mpdf->SetSubject("Bac Pratique $annee");
        $mpdf->SetAuthor('BacArchive');
        $mpdf->WriteHTML($html);
        $mpdf->Output($pdfPath, 'F');

        return $pdfPath;
    }

    public static function generateDailyReport(array $data, string $destFolder): string
    {
        $now = new \DateTime();
        $seances = $data['seances'] ?? [];
        $matiere = $data['matiere'] ?? '';
        $section = $data['section'] ?? '';
        $annee   = $data['annee'] ?? (int)$now->format('Y');
        $lycee   = $data['lycee'] ?? '';

        $dateLong = Labels::formatDateFr($now);
        $timeStr  = $now->format('H') . 'h' . $now->format('i');
        $fileName = "RapportJournee__{$now->format('Y-m-d')}.pdf";

        if (!is_dir($destFolder)) {
            mkdir($destFolder, 0755, true);
        }
        $pdfPath = $destFolder . DIRECTORY_SEPARATOR . $fileName;

        $matiereLabel = Labels::getMatiereLabel($matiere);
        $sectionLabel = Labels::getSectionLabel($section);
        $subtitle = $section ? "$matiereLabel  —  $sectionLabel" : $matiereLabel;

        // Totaux globaux
        $gPresent = 0;
        $gAbsent = 0;
        $gFraud = 0;
        foreach ($seances as $se) {
            foreach ($se['labos'] ?? [] as $la) {
                $gPresent += count(array_filter($la['candidates'] ?? [], fn($c) => empty($c['absent'])));
                $gAbsent += $la['absent'] ?? 0;
                $gFraud += $la['fraud'] ?? 0;
            }
        }
        $gTotal = $gPresent + $gAbsent;
        $totalLabos = array_sum(array_map(fn($se) => count($se['labos'] ?? []), $seances));

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<style>
            body { font-family: sans-serif; font-size: 10pt; margin: 0; }
            .header-box { border: 1.5px solid ' . self::C['hdrBorder'] . '; border-radius: 8px; padding: 12px; margin: 20px 42px; text-align: center; }
            .header-title { font-size: 16pt; font-weight: bold; color: ' . self::C['textNormal'] . '; }
            .header-subtitle { font-size: 11pt; color: ' . self::C['textNormal'] . '; margin-top: 4px; }
            .header-date { font-size: 10pt; color: ' . self::C['textNormal'] . '; margin-top: 4px; }
            .header-session { font-size: 13pt; font-weight: bold; color: ' . self::C['textNormal'] . '; margin-top: 6px; }
            .stats-grid { display: flex; justify-content: center; gap: 8px; margin: 14px 42px; }
            .stat-box { flex: 1; max-width: 120px; border: 0.8px solid ' . self::C['hdrBorder'] . '; border-radius: 5px; padding: 8px; text-align: center; background: #f8fafc; }
            .stat-box .val { font-size: 22pt; font-weight: bold; }
            .stat-box .lbl { font-size: 8pt; color: #555; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9pt; }
            th { background: ' . self::C['hdrBg'] . '; color: ' . self::C['hdrText'] . '; padding: 4px 6px; text-align: left; border: 0.5px solid ' . self::C['hdrBorder'] . '; }
            td { padding: 4px 6px; border-bottom: 0.3px solid ' . self::C['rowStroke'] . '; }
            tr.even td { background: ' . self::C['rowEven'] . '; }
            .footer { text-align: center; font-size: 7.5pt; color: ' . self::C['footerText'] . '; margin-top: 20px; }
            .totals td { background: ' . self::C['secBg1'] . '; font-weight: bold; border: 0.5px solid ' . self::C['hdrBorder'] . '; }
        </style></head><body>';

        // En-tête
        $titleText = "Bac Pratique {$annee}  —  Rapport de Fin de Journée";
        $html .= '<div class="header-box">';
        $html .= '<div class="header-title">' . $titleText . '</div>';
        $html .= '<div class="header-subtitle">' . htmlspecialchars($subtitle) . '</div>';
        $dateLine = $lycee ? htmlspecialchars($lycee) . '  —  ' . $dateLong : $dateLong;
        $html .= '<div class="header-date">' . $dateLine . '</div>';
        $html .= '<div class="header-session">' . count($seances) . ' séance(s)  •  ' . $totalLabos . ' labo(s)</div>';
        $html .= '</div>';

        // Stats globales
        $statDefs = [
            ['label' => 'Candidats', 'val' => $gTotal, 'color' => self::C['hdrBg']],
            ['label' => 'Présents', 'val' => $gPresent, 'color' => '#27ae60'],
            ['label' => 'Absents', 'val' => $gAbsent, 'color' => '#e67e22'],
            ['label' => 'Fraudes', 'val' => $gFraud, 'color' => '#e74c3c'],
        ];
        foreach ($statDefs as $st) {
            $html .= '<div class="stat-box"><div class="val" style="color:' . $st['color'] . '">' . $st['val'] . '</div><div class="lbl">' . $st['label'] . '</div></div>';
        }

        // Tableau récap
        $html .= '<table style="margin-top:16px;">';
        $html .= '<thead><tr><th>Séance</th><th>Labo</th><th>Cand.</th><th>Présents</th><th>Absents</th><th>Fraudes</th><th>PDF</th><th>Excel</th></tr></thead>';
        $html .= '<tbody>';
        $rowIdx = 0;
        foreach ($seances as $se) {
            foreach ($se['labos'] ?? [] as $la) {
                $present = count(array_filter($la['candidates'] ?? [], fn($c) => empty($c['absent'])));
                $nbCands = count($la['candidates'] ?? []);
                $hasPDF = !empty($la['pdfFiles']);
                $hasXLSX = !empty($la['xlsxFiles']);
                $rowClass = $rowIdx % 2 === 0 ? 'even' : 'odd';
                $html .= '<tr class="' . $rowClass . '">';
                $html .= '<td><strong>' . htmlspecialchars($se['name']) . '</strong></td>';
                $html .= '<td>' . htmlspecialchars($la['name']) . '</td>';
                $html .= '<td style="text-align:center;color:' . self::C['hdrBg'] . ';font-weight:bold">' . $nbCands . '</td>';
                $html .= '<td style="text-align:center;color:#27ae60;font-weight:bold">' . $present . '</td>';
                $html .= '<td style="text-align:center;color:' . ($la['absent'] > 0 ? '#e67e22' : '#aaa') . '">' . ($la['absent'] ?? 0) . '</td>';
                $html .= '<td style="text-align:center;color:' . ($la['fraud'] > 0 ? '#e74c3c' : '#aaa') . '">' . ($la['fraud'] ?? 0) . '</td>';
                $html .= '<td style="text-align:center;color:' . ($hasPDF ? '#27ae60' : '#bbb') . '">' . ($hasPDF ? 'x' : '—') . '</td>';
                $html .= '<td style="text-align:center;color:' . ($hasXLSX ? '#27ae60' : '#bbb') . '">' . ($hasXLSX ? 'x' : '—') . '</td>';
                $html .= '</tr>';
                $rowIdx++;
            }
        }
        // Totaux
        $html .= '<tr class="totals">';
        $html .= '<td colspan="2">TOTAUX</td>';
        $html .= '<td style="text-align:center;color:' . self::C['hdrBg'] . '">' . $gTotal . '</td>';
        $html .= '<td style="text-align:center;color:#27ae60">' . $gPresent . '</td>';
        $html .= '<td style="text-align:center;color:' . ($gAbsent > 0 ? '#e67e22' : '#aaa') . '">' . $gAbsent . '</td>';
        $html .= '<td style="text-align:center;color:' . ($gFraud > 0 ? '#e74c3c' : '#aaa') . '">' . $gFraud . '</td>';
        $html .= '<td></td><td></td>';
        $html .= '</tr>';
        $html .= '</tbody></table>';

        $html .= '<div class="footer">Généré par BacArchive  —  ' . $dateLong . '  —  ' . $timeStr . '</div>';
        $html .= '</body></html>';

        $mpdf = new \Mpdf\Mpdf([
            'format' => 'A4',
            'margin_left' => 42,
            'margin_right' => 42,
            'margin_top' => 30,
            'margin_bottom' => 25,
        ]);
        $mpdf->SetTitle('Rapport de journée');
        $mpdf->SetSubject("Bac Pratique $annee");
        $mpdf->SetAuthor('BacArchive');
        $mpdf->WriteHTML($html);
        $mpdf->Output($pdfPath, 'F');

        return $pdfPath;
    }
}