<?php

namespace App\Support;

use Illuminate\Support\Str;

/** A one-page text PDF: a title and wrapped lines, for the example board's files. */
class SimplePdf
{
    public static function make(string $title, array $lines): string
    {
        $text = fn (string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], Str::ascii($s));

        $ops = ['BT', '/F2 20 Tf', '72 730 Td', '('.$text($title).') Tj', '/F1 12 Tf', '0 -34 Td'];
        foreach ($lines as $line) {
            // A blank line is a gap; anything longer wraps at about 80 characters.
            foreach ($line === '' ? [''] : explode("\n", wordwrap($line, 80, "\n", true)) as $part) {
                $ops[] = '('.$text($part).') Tj';
                $ops[] = '0 -18 Td';
            }
        }
        $ops[] = 'ET';
        $stream = implode("\n", $ops);

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
