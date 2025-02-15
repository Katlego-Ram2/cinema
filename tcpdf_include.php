<?php
// Include TCPDF library
require_once('tcpdf.php');

// Extend TCPDF class to create custom header and footer
class EMS_PDF extends TCPDF {
    
    // Page header
    public function Header() {
        // Set font
        $this->SetFont('helvetica', 'B', 12);
        
        // Title
        $this->Cell(0, 10, 'Employee Management System Report', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        
        // Line break
        $this->Ln(10);
    }

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}
?>
