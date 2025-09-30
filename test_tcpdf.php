<?php
// test_tcpdf.php
require_once(__DIR__ . '/tcpdf/tcpdf.php');

$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'TCPDF Test - Working!', 0, 1, 'C');
$pdf->Output('test.pdf', 'I');
?>
