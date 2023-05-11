<?php

use Smalot\PdfParser\Parser;

include __DIR__.'/../src/PDFChecker.php';
include __DIR__.'/../src/Result.php';

class PDFTest extends \PHPUnit\Framework\TestCase
{
    public function testFirst(): void
    {
        $file = __DIR__ . '/resources/ressources-rapdf1/Document-modele.pdf';

        $checker = new Shikiryu\PDFChecker\PDFChecker($file);
        $checker->checkFile();

        var_dump(json_encode($checker->result, JSON_PRETTY_PRINT));
    }

    public function testUnknownFile(): void
    {
        $file = __DIR__ . '/resources/ressources-rapdf1/test.pdf';

        $checker = new Shikiryu\PDFChecker\PDFChecker($file);
        $checker->checkFile();

        var_dump(json_encode($checker->result, JSON_PRETTY_PRINT));
    }
}