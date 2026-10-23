<?php
class Barcode39
{
    private $table = array(
        '0' => '101001101101', '1' => '110100101011', '2' => '101100101011',
        '3' => '110110010101', '4' => '101001101011', '5' => '110100110101',
        '6' => '101100110101', '7' => '101001011011', '8' => '110100101101',
        '9' => '101100101101', 'A' => '110101001011', 'B' => '101101001011',
        'C' => '110110100101', 'D' => '101011001011', 'E' => '110101100101',
        'F' => '101101100101', 'G' => '101010011011', 'H' => '110101001101',
        'I' => '101101001101', 'J' => '101011001101', 'K' => '110101010011',
        'L' => '101101010011', 'M' => '110110101001', 'N' => '101011010011',
        'O' => '110101101001', 'P' => '101101101001', 'Q' => '101010110011',
        'R' => '110101011001', 'S' => '101101011001', 'T' => '101011011001',
        'U' => '110010101011', 'V' => '100110101011', 'W' => '110011010101',
        'X' => '100101101011', 'Y' => '110010110101', 'Z' => '100110110101',
        '-' => '100101011011', '.' => '110010101101', ' ' => '100110101101',
        '*' => '100101101101'
    );

    public function draw($code, $width, $height)
    {
        $code = strtoupper($code);
        $fullCode = '*' . $code . '*';
        
        // Convertir a binario
        $binary = '';
        for ($i = 0; $i < strlen($fullCode); $i++) {
            $char = $fullCode[$i];
            if (isset($this->table[$char])) {
                $binary .= $this->table[$char];
            }
        }

        // 🔥 ALTA RESOLUCIÓN: Generar 4x más grande para evitar borrosidad
        $scaleFactor = 10;
        $baseWidth = strlen($binary) * $scaleFactor;
        $baseHeight = $height * $scaleFactor;

        $img = imagecreatetruecolor($baseWidth, $baseHeight);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        
        imagefill($img, 0, 0, $white);

        // Dibujar rectángulos sólidos en lugar de líneas
        for ($x = 0; $x < strlen($binary); $x++) {
            if ($binary[$x] == '1') {
                imagefilledrectangle(
                    $img,
                    $x * $scaleFactor, 0,
                    ($x * $scaleFactor) + $scaleFactor - 1, $baseHeight,
                    $black
                );
            }
        }

        // Escalar al tamaño final
        $scaled = imagescale($img, $width, $height, IMG_BICUBIC);
        imagedestroy($img);

        // Guardar en archivo temporal
        $tempFile = tempnam(sys_get_temp_dir(), 'barcode') . '.png';
        if (imagepng($scaled, $tempFile)) {
            imagedestroy($scaled);
            return $tempFile;
        }
        
        imagedestroy($scaled);
        return null;
    }
}
?>