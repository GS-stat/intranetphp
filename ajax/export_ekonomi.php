<?php
require_once '../includes/config.php';
kravInloggning();

$typ   = $_GET['typ']   ?? 'ekonomi';   // ekonomi | projekt | utgifter
$ar    = isset($_GET['ar'])    ? (int)$_GET['ar']    : (int)date('Y');
$manad = isset($_GET['manad']) ? (int)$_GET['manad'] : 0;

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="gs_' . $typ . '_' . $ar . '.csv"');
header('Cache-Control: no-cache');

// BOM för Excel att läsa UTF-8 korrekt
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

if ($typ === 'ekonomi') {
    // Månadsöversikt
    fputcsv($out, ['Månad', 'Intäkter (kr)', 'Projektkostnader (kr)', 'Allmänna utgifter (kr)', 'Totala kostnader (kr)', 'Netto (kr)'], ';');
    $manadsNamn = ['', 'Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni', 'Juli', 'Augusti', 'September', 'Oktober', 'November', 'December'];
    $data = getEkonomiManadsoversikt($pdo, $ar);
    foreach ($data as $m) {
        fputcsv($out, [
            $manadsNamn[$m['manad']],
            number_format($m['intakter'], 2, ',', ''),
            number_format($m['proj_kostnader'], 2, ',', ''),
            number_format($m['allm_kostnader'], 2, ',', ''),
            number_format($m['totala_kostnader'], 2, ',', ''),
            number_format($m['netto'], 2, ',', ''),
        ], ';');
    }
    $summa = getEkonomiArssummering($pdo, $ar);
    fputcsv($out, ['TOTALT',
        number_format($summa['intakter'], 2, ',', ''),
        number_format($summa['proj_kostnader'], 2, ',', ''),
        number_format($summa['allm_kostnader'], 2, ',', ''),
        number_format($summa['totala_kostnader'], 2, ',', ''),
        number_format($summa['netto'], 2, ',', ''),
    ], ';');

} elseif ($typ === 'projekt') {
    // Projekt med vinst
    fputcsv($out, ['ID', 'Regnummer', 'Uppdrag', 'Kund', 'Status', 'Intäkt (kr)', 'Projektkostnader (kr)', 'Vinst (kr)', 'Marginal (%)'], ';');
    $data = getProjektMedVinst($pdo, $ar);
    foreach ($data as $p) {
        $intakt    = (float)$p['intakt'];
        $kostnader = (float)$p['kostnader'];
        $vinst     = (float)$p['vinst'];
        $marginal  = $intakt > 0 ? round($vinst / $intakt * 100) : '';
        fputcsv($out, [
            $p['id'],
            $p['regnummer'],
            $p['rubrik'],
            $p['kontakt_person_namn'],
            $p['status'],
            number_format($intakt, 2, ',', ''),
            number_format($kostnader, 2, ',', ''),
            number_format($vinst, 2, ',', ''),
            $marginal,
        ], ';');
    }

} elseif ($typ === 'utgifter') {
    // Allmänna utgifter
    fputcsv($out, ['Datum', 'Kategori', 'Beskrivning', 'Ex. moms (kr)', 'Momssats (%)', 'Ink. moms (kr)', 'Återkommande'], ';');
    $data = hamtaUtgifter($pdo, $ar, $manad > 0 ? $manad : null);
    foreach ($data as $u) {
        $inkMoms = $u['belopp'] * (1 + $u['moms_procent'] / 100);
        fputcsv($out, [
            $u['datum'],
            $u['kategori'],
            $u['beskrivning'],
            number_format($u['belopp'], 2, ',', ''),
            $u['moms_procent'],
            number_format($inkMoms, 2, ',', ''),
            $u['aterkommande'] ? 'Ja' : 'Nej',
        ], ';');
    }
}

fclose($out);
exit;
