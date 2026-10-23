<?php
class Barcode128
{
    // Tablas de patrones para Code 128
    private $setA = array(
        ' ' => '11011001100', '!' => '11001101100', '"' => '11001100110',
        '#' => '10010011000', '$' => '10010001100', '%' => '10001001100',
        '&' => '10011001000', "'" => '10011000100', '(' => '10001100100',
        ')' => '11001001000', '*' => '11001000100', '+' => '11000100100',
        ',' => '10110011100', '-' => '10011011100', '.' => '10011001110',
        '/' => '10111001100', '0' => '10011101100', '1' => '10011100110',
        '2' => '11001110010', '3' => '11001011100', '4' => '11001001110',
        '5' => '11011100100', '6' => '11001110100', '7' => '11101100100',
        '8' => '11101001100', '9' => '11100101100', ':' => '11100100110',
        ';' => '11011011000', '<' => '11011000110', '=' => '11000110110',
        '>' => '10110110000', '?' => '10110001100', '@' => '10001101100',
        'A' => '10111011000', 'B' => '10001110110', 'C' => '10001101110',
        'D' => '11101110110', 'E' => '11010001000', 'F' => '11000101000',
        'G' => '11000100010', 'H' => '10110111000', 'I' => '10110001110',
        'J' => '10001101110', 'K' => '11101110110', 'L' => '11010001110',
        'M' => '11000101110', 'N' => '11011101000', 'O' => '11011100010',
        'P' => '11011101110', 'Q' => '11101011000', 'R' => '11101000110',
        'S' => '11100010110', 'T' => '11101101000', 'U' => '11101100010',
        'V' => '11100011010', 'W' => '11101111010', 'X' => '11001000010',
        'Y' => '11110001010', 'Z' => '10100001000', '[' => '10000101000',
        '\\' => '10000100010', ']' => '10110000010', '^' => '10011000010',
        '_' => '10001100010', 'NUL' => '11000010010', 'SOH' => '11001010000',
        'STX' => '11110111010', 'ETX' => '11000010100', 'EOT' => '10001111010',
        'ENQ' => '10100111100', 'ACK' => '10010111100', 'BEL' => '10010011110',
        'BS' => '10111100100', 'HT' => '10011110100', 'LF' => '10011110010',
        'VT' => '11110100100', 'FF' => '11110010100', 'CR' => '11110010010',
        'SO' => '11011011110', 'SI' => '11011110110', 'DLE' => '11110110110',
        'DC1' => '10101111000', 'DC2' => '10100011110', 'DC3' => '10001011110',
        'DC4' => '10111101000', 'NAK' => '10111100010', 'SYN' => '11110101000',
        'ETB' => '11110100010', 'CAN' => '10111011110', 'EM' => '10111101110',
        'SUB' => '11101011110', 'ESC' => '11110101110', 'FS' => '11010000100',
        'GS' => '11010010000', 'RS' => '11010011100', 'US' => '10001111010'
    );

    private $startB = '11010010000';
    private $stop = '1100011101011';

    public function draw($code, $width, $height)
    {
        $code = strtoupper($code);
        
        // Calcular el código de barras Code 128
        $binary = $this->generateCode128($code);
        
        if (!$binary) {
            return null;
        }

        // Crear imagen de alta resolución
        $scaleFactor = 2;
        $baseWidth = strlen($binary) * $scaleFactor;
        $baseHeight = $height * $scaleFactor;
        
        $img = imagecreatetruecolor($baseWidth, $baseHeight);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        
        imagefill($img, 0, 0, $white);

        // Dibujar las barras
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

        // Escalar imagen
        $scaled = imagescale($img, $width, $height, IMG_BICUBIC);
        imagedestroy($img);

        // Guardar en archivo temporal
        $tempFile = tempnam(sys_get_temp_dir(), 'barcode128') . '.png';
        if (imagepng($scaled, $tempFile)) {
            imagedestroy($scaled);
            return $tempFile;
        }
        
        imagedestroy($scaled);
        return null;
    }

    private function generateCode128($code)
    {
        // Start B + datos + checksum + stop
        $result = $this->startB;
        $checksum = 104; // Start B
        $position = 1;

        for ($i = 0; $i < strlen($code); $i++) {
            $char = $code[$i];
            
            // Buscar el patrón para el carácter
            $found = false;
            foreach ($this->setA as $c => $pattern) {
                if ($c === $char) {
                    $result .= $pattern;
                    // Calcular valor del carácter para checksum
                    $charValue = array_search($c, array_keys($this->setA));
                    if ($charValue !== false) {
                        $checksum += $charValue * $position;
                        $position++;
                    }
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                // Carácter no soportado, usar espacio
                $result .= $this->setA[' '];
                $checksum += 0 * $position;
                $position++;
            }
        }

        // Calcular checksum
        $checksum = $checksum % 103;
        
        // Obtener patrón del checksum (simplificado)
        $checksumPattern = $this->getChecksumPattern($checksum);
        $result .= $checksumPattern;
        
        // Agregar stop
        $result .= $this->stop;
        
        return $result;
    }

    private function getChecksumPattern($value)
    {
        // Tabla simplificada de patrones para checksum
        $patterns = array(
            0 => '11011001100', 1 => '11001101100', 2 => '11001100110',
            3 => '10010011000', 4 => '10010001100', 5 => '10001001100',
            6 => '10011001000', 7 => '10011000100', 8 => '10001100100',
            9 => '11001001000', 10 => '11001000100', 11 => '11000100100',
            // ... continuar según sea necesario
        );
        
        return $patterns[$value % 12] ?? '11011001100';
    }
}
?>