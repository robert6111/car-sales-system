<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../includes/business_helpers.php';
use setasign\Fpdi\Fpdi;

class CarSalesReportPdf extends Fpdi {
    public string $reportTitle = 'Report';
    public array $company = [];
    public string $logoFile = '';
    public function __construct() { parent::__construct('L','mm','A4'); $this->SetAutoPageBreak(true,18); }
    function Header() {
        $left=12;
        if($this->logoFile && is_file($this->logoFile) && preg_match('/\.(png|jpe?g)$/i',$this->logoFile)){
            try{$this->Image($this->logoFile,$left,8,0,18);$left=42;}catch(Throwable $e){}
        }
        $this->SetXY($left,8);$this->SetFont('Arial','B',14);$this->SetTextColor(15,23,42);$this->Cell(240,6,pdf_cell_text($this->company['company_name']??'Car Sales System'),0,1,'L');
        $this->SetX($left);$this->SetFont('Arial','',8);$contact=trim(($this->company['address']??'').(($this->company['phone']??'')?' | '.$this->company['phone']:'').(($this->company['email']??'')?' | '.$this->company['email']:''));if($contact!=='')$this->Cell(240,5,pdf_cell_text($contact),0,1,'L');
        $reg='';if(!empty($this->company['tin_number']))$reg.='TIN: '.$this->company['tin_number'];if(!empty($this->company['registration_number']))$reg.=($reg?' | ':'').'Reg: '.$this->company['registration_number'];if($reg!==''){$this->SetX($left);$this->Cell(240,5,pdf_cell_text($reg),0,1,'L');}
        $this->SetY(31);$this->SetFont('Arial','B',16);$this->Cell(0,8,$this->reportTitle,0,1,'C');$this->SetFont('Arial','',8);$this->Cell(0,5,'Generated: '.date('Y-m-d H:i:s'),0,1,'C');$this->Ln(3);
    }
    function Footer() { $this->SetY(-14);$this->SetFont('Arial','I',8);$this->SetTextColor(100,116,139);$this->Cell(0,10,'Page '.$this->PageNo(),0,0,'C'); }
}
function pdf_cell_text($text): string { return substr(preg_replace('/[^\x20-\x7E]/',' ',(string)$text),0,55); }
function output_report_pdf(string $title, array $headers, array $rows, array $widths, string $filename): void {
    global $conn;
    $settings=(isset($conn)&&$conn instanceof mysqli)?car_sales_company_settings($conn):['company_name'=>'Car Sales System'];
    $pdf=new CarSalesReportPdf();$pdf->company=$settings;$logoRel=$settings['logo_path']??'';$logo=$logoRel?dirname(__DIR__,2).'/'.ltrim($logoRel,'/'):'';$pdf->logoFile=$logo;
    $pdf->reportTitle=$title;$pdf->AddPage();$pdf->SetFont('Arial','B',8);$pdf->SetFillColor(15,23,42);$pdf->SetTextColor(255,255,255);
    foreach($headers as $i=>$h){$pdf->Cell($widths[$i],8,pdf_cell_text($h),1,0,'L',true);} $pdf->Ln();
    $pdf->SetFont('Arial','',8);$pdf->SetTextColor(15,23,42);
    foreach($rows as $row){if($pdf->GetY()>180){$pdf->AddPage();$pdf->SetFont('Arial','B',8);$pdf->SetFillColor(15,23,42);$pdf->SetTextColor(255,255,255);foreach($headers as $i=>$h){$pdf->Cell($widths[$i],8,pdf_cell_text($h),1,0,'L',true);} $pdf->Ln();$pdf->SetFont('Arial','',8);$pdf->SetTextColor(15,23,42);}foreach($row as $i=>$cell){$pdf->Cell($widths[$i],7,pdf_cell_text($cell),1,0,'L');}$pdf->Ln();}
    $pdf->Output('D',$filename);exit;
}
?>
