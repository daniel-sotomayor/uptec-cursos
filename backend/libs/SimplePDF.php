<?php
/**
 * UPTEC Cursos - Generador de PDFs Simple (Repotenciado)
 * Libreria ligera con soporte para Landscape, Imagenes JPG y Tablas
 */

class SimplePDF {
    protected $objects = [];
    protected $pages = [];
    protected $pageBuffers = [];
    protected $pageOrientations = [];
    protected $currentPage = -1;
    protected $fonts = [];
    protected $images = [];
    protected $currentFont = 'Helvetica';
    protected $currentSize = 12;
    protected $width = 215.9;  // mm (Letter)
    protected $height = 279.4; // mm (Letter)
    protected $currentW = 215.9;
    protected $currentH = 279.4;

    public function __construct() {
        $this->init();
    }

    protected function init() {
        $this->objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj";
        $this->objects[2] = "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj";

        // Registrar fuentes estándar de PDF
        $this->fonts['Helvetica'] = $this->addFont('Helvetica');
        $this->fonts['Helvetica-Bold'] = $this->addFont('Helvetica-Bold');
        $this->fonts['Times-Roman'] = $this->addFont('Times-Roman');
        $this->fonts['Times-Italic'] = $this->addFont('Times-Italic');
        $this->fonts['Times-BoldItalic'] = $this->addFont('Times-BoldItalic');
        $this->currentFont = 'Helvetica';
    }

protected function addFont($name) {
        $id = count($this->objects) + 1;
        // Se agrega /Encoding /WinAnsiEncoding para que acepte tildes y ñ correctamente
        $this->objects[$id] = "$id 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /$name /Encoding /WinAnsiEncoding >>\nendobj";
        return $id;
    }

    // Nuevo método para centrar texto exactamente entre dos coordenadas X (ej. entre 120mm y 260mm)
    public function textCenterBox($x1, $x2, $y, $text) {
        $boxW = $x2 - $x1;
        $safeText = iconv('UTF-8', 'windows-1252//TRANSLIT', (string)$text) ?: (string)$text;
        $charWidth = ($this->currentSize * 0.50) / 2.83465;
        $textLen = strlen($safeText) * $charWidth;
        $x = $x1 + max(0, ($boxW - $textLen) / 2);
        $this->text($x, $y, $text);
    }
    
    public function addPage($orientation = 'P') {
        $orientation = strtoupper($orientation);
        $this->pageOrientations[] = $orientation;

        if ($orientation === 'L') {
            $w = 792; $h = 612; // Puntos Letter Horizontal
            $this->currentW = 279.4;
            $this->currentH = 215.9;
        } else {
            $w = 612; $h = 792; // Puntos Letter Vertical
            $this->currentW = 215.9;
            $this->currentH = 279.4;
        }

        $pageId = count($this->objects) + 1;
        $this->pages[] = $pageId;
        $this->currentPage = count($this->pages) - 1;
        $this->pageBuffers[$this->currentPage] = '';

        $this->objects[$pageId] = [
            'type' => 'page',
            'w' => $w,
            'h' => $h
        ];

        return $pageId;
    }

    public function setFont($font, $size = 12) {
        if (isset($this->fonts[$font])) {
            $this->currentFont = $font;
        }
        $this->currentSize = $size;
    }

    protected function append($data) {
        if ($this->currentPage < 0) {
            $this->addPage();
        }
        $this->pageBuffers[$this->currentPage] .= $data;
    }

    public function setColor($r, $g, $b) {
        $this->append(sprintf("%.3f %.3f %.3f rg\n", $r / 255, $g / 255, $b / 255));
    }

    public function setStrokeColor($r, $g, $b) {
        $this->append(sprintf("%.3f %.3f %.3f RG\n", $r / 255, $g / 255, $b / 255));
    }

    public function text($x, $y, $text) {
        $safeText = iconv('UTF-8', 'windows-1252//TRANSLIT', (string)$text) ?: $text;
        $safeText = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $safeText);
        
        $buf = "BT\n";
        $buf .= "/{$this->currentFont} {$this->currentSize} Tf\n";
        $buf .= sprintf("%.2f %.2f Td\n", $x * 2.83465, ($this->currentH - $y) * 2.83465);
        $buf .= "($safeText) Tj\n";
        $buf .= "ET\n";
        $this->append($buf);
    }

    public function textCenter($y, $text, $boxWidth = null) {
        $w = $boxWidth ?: $this->currentW;
        $safeText = (string)$text;
        // Estimación promedio de ancho por caracter
        $charWidth = ($this->currentSize * 0.50) / 2.83465;
        $textLen = strlen($safeText) * $charWidth;
        $x = max(0, ($w - $textLen) / 2);
        $this->text($x, $y, $safeText);
    }

    public function textRight($xRight, $y, $text) {
        $safeText = (string)$text;
        $charWidth = ($this->currentSize * 0.50) / 2.83465;
        $textLen = strlen($safeText) * $charWidth;
        $x = max(0, $xRight - $textLen);
        $this->text($x, $y, $safeText);
    }

    public function line($x1, $y1, $x2, $y2) {
        $ptX1 = $x1 * 2.83465;
        $ptY1 = ($this->currentH - $y1) * 2.83465;
        $ptX2 = $x2 * 2.83465;
        $ptY2 = ($this->currentH - $y2) * 2.83465;
        $this->append(sprintf("%.2f %.2f m %.2f %.2f l S\n", $ptX1, $ptY1, $ptX2, $ptY2));
    }

    public function setLineWidth($width) {
        $this->append(sprintf("%.2f w\n", $width * 2.83465));
    }

    public function rect($x, $y, $w, $h, $fill = false, $stroke = true) {
        $ptX = $x * 2.83465;
        $ptY = ($this->currentH - $y - $h) * 2.83465;
        $ptW = $w * 2.83465;
        $ptH = $h * 2.83465;

        $op = ($fill && $stroke) ? 'B' : ($fill ? 'f' : 'S');
        $this->append(sprintf("%.2f %.2f %.2f %.2f re %s\n", $ptX, $ptY, $ptW, $ptH, $op));
    }

    public function image($file, $x, $y, $w, $h) {
        if (!file_exists($file)) return false;

        $info = @getimagesize($file);
        if (!$info || $info[2] !== IMAGETYPE_JPEG) {
            // Solo soporta JPG nativamente
            return false;
        }

        $imgKey = md5($file);
        if (!isset($this->images[$imgKey])) {
            $data = file_get_contents($file);
            $imgId = count($this->objects) + 1;
            
            $colspace = ($info['channels'] ?? 3) == 4 ? '/DeviceCMYK' : '/DeviceRGB';
            $objContent = "$imgId 0 obj\n<< /Type /XObject /Subtype /Image /Width {$info[0]} /Height {$info[1]} /ColorSpace $colspace /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($data) . " >>\nstream\n" . $data . "\nendstream\nendobj";
            
            $this->objects[$imgId] = $objContent;
            $this->images[$imgKey] = [
                'id' => $imgId,
                'name' => 'Img' . (count($this->images) + 1)
            ];
        }

        $imgRef = $this->images[$imgKey];
        $ptX = $x * 2.83465;
        $ptY = ($this->currentH - $y - $h) * 2.83465;
        $ptW = $w * 2.83465;
        $ptH = $h * 2.83465;

        $this->append(sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q\n", $ptW, $ptH, $ptX, $ptY, $imgRef['name']));
        return true;
    }

    public function output($filename = 'certificado.pdf', $dest = 'D') {
        // Recursos de Fuentes
        $fontRes = "";
        foreach ($this->fonts as $name => $id) {
            $fontRes .= "/$name $id 0 R ";
        }

        // Recursos de Imagenes
        $xobjRes = "";
        foreach ($this->images as $img) {
            $xobjRes .= "/{$img['name']} {$img['id']} 0 R ";
        }

        // Generar páginas y streams de contenido
        foreach ($this->pages as $i => $pageId) {
            $contentId = count($this->objects) + 1;
            $stream = "q\n" . ($this->pageBuffers[$i] ?? '') . "Q";
            $this->objects[$contentId] = "$contentId 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n$stream\nendstream\nendobj";

            $pInfo = $this->objects[$pageId];
            $this->objects[$pageId] = "$pageId 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pInfo['w']} {$pInfo['h']}] /Contents $contentId 0 R /Resources << /Font << $fontRes >> /XObject << $xobjRes >> >> >>\nendobj";
        }

        // Ensamblar catálogo
        $kids = implode(' ', array_map(fn($p) => "$p 0 R", $this->pages));
        $this->objects[2] = "2 0 obj\n<< /Type /Pages /Kids [$kids] /Count " . count($this->pages) . " >>\nendobj";

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        $offset = strlen($pdf);

        foreach ($this->objects as $id => $obj) {
            $offsets[$id] = $offset;
            $pdf .= "$obj\n";
            $offset = strlen($pdf);
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($this->objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }

        $pdf .= "trailer\n<< /Size " . (count($this->objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xrefOffset\n%%EOF";

        if ($dest === 'D' || $dest === 'I') {
            $disposition = ($dest === 'I') ? 'inline' : 'attachment';
            header('Content-Type: application/pdf');
            header("Content-Disposition: $disposition; filename=\"$filename\"");
            header('Content-Length: ' . strlen($pdf));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $pdf;
            exit;
        }

        return $pdf;
    }
}
