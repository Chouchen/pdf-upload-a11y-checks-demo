<?php

namespace Shikiryu\PDFChecker;

use Exception;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Font;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\PDFObject;
use Smalot\PdfParser\XObject\Form;

class PDFChecker
{
    public ?Document $pdf = null;

    public ?Result $result = null;

    /**
     * @param string $filepath
     */
    public function __construct(string $filepath)
    {
        $parser = new Parser();
        $this->result = new Result();
        try {
            $this->pdf = $parser->parseFile($filepath);
        } catch (Exception $e) {
            $this->result->BrokenFile = sprintf('Fail : %s', $e->getMessage());
        }
    }

    public function checkFile(): void
    {
        if ($this->pdf instanceof Document) {
            $this->_checkTag();
            $this->_checkProtection();
            $this->_checkContent();
        }
    }

    private function _checkTag()
    {
        $types = array_map(static fn (PDFObject $o) => array_keys($o->getHeader()?->getElements()), $this->pdf->getObjects());
        if (in_array('StructTreeRoot', array_merge(...array_values($types)), true)) {
            $markInfoFound = false;
            foreach ($types as $id => $type) {
                if (in_array('MarkInfo', $type, true)) {
                    $markInfoFound = true;
                    $markInfoChildren = $this->pdf->getObjectById($id)?->getHeader()?->getElements()['MarkInfo'];
                    if ($markInfoChildren === null || !array_key_exists('Marked', $markInfoChildren->getElements()) || $markInfoChildren->getElements()['Marked']->getContent() === false) {
                        $this->result->TaggedTest = 'Fail';
                    } else {
                        $this->result->TaggedTest = 'Pass';
                    }
                }
            }

            if (!$markInfoFound) {
                $this->result->TaggedTest = 'Fail';
            }
        } else {
            $this->result->TaggedTest = 'Fail';
        }
    }

    private function _checkProtection(): void
    {
        // check if not protected
        $result['ProtectedTest'] = 'Pass';
        $test = $this->pdf->getDetails();
        var_dump($test);
        /**
         * result['ProtectedTest'] = 'Pass'
        if (pdf.is_encrypted): # Matterhorn 26-001
        if (pdf.encryption.P is None):
        result['Accessible'] = False
        result['ProtectedTest'] = 'Fail'

        if (pdf.allow is None):
        result['_log'] += 'permissions not found, should not happen'
        else:
        # according to the Matterhorn test 26-002 we should only test the 10th bit of P
        # but according to our tests, in Acrobat, the 5th bit and the R field are also used to give permissions to screen readers.
        # The algorithm behind pdf.allow.accessibility is here https://github.com/qpdf/qpdf/blob/8971443e4680fc1c0babe56da58cc9070a9dae2e/libqpdf/QPDF_encryption.cc#L1486
        # This algorithm works in most cases, except when the 10th bit is not set and the 5th bit is set. In this case Acrobat is considering that the 5th bit overrides the 10th bit and gives access.
        # I was able to test this only with a file where R=3. To be tested with R<3, but this case seems to be rare.
        bits = BitArray(intbe=pdf.encryption.P, length=16)
        bit10 = bits[16-10]
        bit5 = bits[16-5]
        if ((not bit10) and bit5):
        result['ProtectedTest'] = 'Pass'
        result['_log'] += 'P[10]='+str(bit10)+ ' P[5]='+str(bit5)+' R='+str(pdf.encryption.R)+', '
        else:
        result['ProtectedTest'] = 'Pass' if pdf.allow.accessibility else 'Fail'
         */
    }

    private function _checkContent(): void
    {
        # try to detect if this PDF contains no text (ex: scanned document)
        # - if the document is not tagged and has no text, it will be inaccessible
        # - if the document is tagged and has no text, it can be accessible

        $result = [
            'numTxt' => 0,
            'fontNames' => [],
        ];

        foreach ($this->pdf->getPages() as $page) {
            $result = $this->_mergeAnalyses($result, $this->_analyseContent($page));
        }

        $this->result->fonts = count($result['fontNames']);

        $this->result->numTxtObjects = $result['numTxt'];
        $this->result->EmptyTextTest = (count($result['fontNames']) === 0 || $result['numTxt'] === 0) ? 'Fail' : 'Pass';
    }

    private function _mergeAnalyses($a, $b): array
    {
        $res = [];

        foreach (array_keys($a) as $i) {
            if ($i === 'fontNames') {
                $res['fontNames'] = array_merge($a['fontNames'], $b['fontNames']);
            } else {
                $res[$i] = $a[$i] + $b[$i];
            }
        }

        return $res;
    }
    private function _analyseContent(Page $content, bool $isXObject = false): array
    {
        $res = [
            'numTxt' => 0,
            'fontNames' => [],
        ];

        $xObjects = $content->getXObjects();
        if (!empty($xObjects)) {
            foreach ($xObjects as $xObject) {
                if ($xObject instanceof Form /* && empty($t . get('/Ref'))*/) {
                    $res = $this->_mergeAnalyses($res, $this->_analyseContent($xObject, true));
                }
            }
        }

        $fonts = $content->getFonts();
        $res['fontNames'] = array_map(static fn (Font $font) => $font->getName(), $fonts);

        return $res;
        /*
                if (content . Resources . get('/Font') is not None):
                    # get all font names
                    for i in content . Resources . Font:
                        font = content . Resources . Font[i]
                        fontName = None
                        if (font . get('/FontDescriptor') is not None):
                            fontName = str(content . Resources . Font[i] . FontDescriptor . FontName)
                        else:
                            fontName = str(content . Resources . Font[i] . get('/BaseFont'))
                        res['fontNames'] . add(fontName)

                    # count the number of text objects
                    for operands, operator in pikepdf . parse_content_stream(content, "Tf"):
                        res['numTxt'] += 1
            }

            return $res;*/
    }
}
