<?php

include __DIR__.'/../check.php';

class PDFTest extends \PHPUnit\Framework\TestCase
{
    public function testFirst(): void
    {
        $file = __DIR__ . '/resources/ressources-rapdf1/Document-modele.pdf';

        checkFile($file, true);
    }
}