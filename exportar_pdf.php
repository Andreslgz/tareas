<?php
require 'db.php';
require 'fpdf181/fpdf.php';

class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial','B',10);
        $this->Cell(0,8,'Reporte de Tareas - CESCOCONLINE',0,1,'C');
        $this->SetFont('Arial','',8);
        $this->Cell(0,6,'Fecha de Exportacion: ' . date('d/m/Y H:i'), 0, 1, 'C');
        $this->Ln(1);
    }

    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        $sep = -1;
        $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep == -1) { if ($i == $j) $i++; }
                else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }

    function Row($data, $widths, $height = 4) {
        $nb = 0;
        foreach ($data as $i => $txt)
            $nb = max($nb, $this->NbLines($widths[$i], $txt));
        $h = $height * $nb;
        $this->CheckPageBreak($h);
        for ($i = 0; $i < count($data); $i++) {
            $w = $widths[$i];
            $x = $this->GetX();
            $y = $this->GetY();
            $this->Rect($x, $y, $w, $h);
            $this->MultiCell($w, $height, $data[$i], 0, 'L');
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }

    function CheckPageBreak($h) {
        if ($this->GetY() + $h > $this->PageBreakTrigger)
            $this->AddPage($this->CurOrientation);
    }
}

// Obtener datos
$tareas = $pdo->query("SELECT * FROM tareas ORDER BY fecha DESC")->fetchAll(PDO::FETCH_ASSOC);

$pdf = new PDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial','B',8);

$widths = [30, 65, 28, 20, 65, 22];
$headers = ["Modulo", "Detalle", "Estado", "Revisado", "Observaciones", "Fecha"];

foreach ($headers as $i => $header) {
    $pdf->Cell($widths[$i], 7, $header, 1, 0, 'C');
}
$pdf->Ln();

$pdf->SetFont('Arial','',7);

foreach ($tareas as $row) {
    $pdf->Row([
        $row['modulo'],
        $row['detalle'],
        $row['estado'] === 'SI' ? 'Terminado' : 'En Proceso',
        $row['revisado'],
        $row['observaciones'] ?: '--',
        $row['fecha'] ? date('d/m/Y', strtotime($row['fecha'])) : '--'
    ], $widths, 4);
}

$pdf->Output("I", "tareas_cesco.pdf");
exit();
?>
