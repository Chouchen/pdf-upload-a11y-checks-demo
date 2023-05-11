<?php

use Smalot\PdfParser\Parser;
use Smalot\PdfParser\PDFObject;

$outputFields = [ 'BrokenFile', 'TaggedTest', 'EmptyTextTest', 'ProtectedTest', '_log', 'fonts', 'numTxtObjects'];
$debug = false;

function initAnalysis(): array
{
    return [
        'numTxt' => 0,
        'fontNames' => [],
    ];
}

function mergeAnalyses($a, $b): array
{
    $res = [];
    foreach (array_keys($a) as $i) {
        if ($i === 'fontNames') {
            $res['fontNames'] = array_merge($a, $b);
        } else {
            $res[$i] = $a[$i] + $b[$i];
        }
    }
    return $res;
}

function analyseContent($content, bool $isXObject = false) {
    $res = initAnalysis();
    if (!empty($content . get('/Resources'))) {
        $xobject = $content . $Resources . get('/XObject');
        if (!empty($xobject)) {
            foreach ($xobject as $i => $t) {
                if ((string)$t . get('/Subtype') === '/Form' && empty($t . get('/Ref'))) {
                    $res = mergeAnalyses($res, analyseContent($t, true));
                }
            }
        }
    }
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
# code extracted from https://github.com/accessibility-luxembourg/simplA11yPDFCrawler



/*
def analyseContent(content, isXObject:bool = False):
    res = initAnalysis()
    if (content.get('/Resources') is not None):
        xobject = content.Resources.get('/XObject')
        if (xobject is not None):
            for i in xobject:
                if (str(xobject[i].get('/Subtype')) == '/Form' and xobject[i].get('/Ref') == None):
                    res = mergeAnalyses(res, analyseContent(xobject[i], True))

        if (content.Resources.get('/Font') is not None):
            # get all font names
            for i in content.Resources.Font:
                font = content.Resources.Font[i]
                fontName = None
                if (font.get('/FontDescriptor') is not None):
                    fontName = str(content.Resources.Font[i].FontDescriptor.FontName)
                else:
                    fontName = str(content.Resources.Font[i].get('/BaseFont'))
                res['fontNames'].add(fontName)

            # count the number of text objects
            for operands, operator in pikepdf.parse_content_stream(content, "Tf"):
                res['numTxt'] += 1

    return res
*/
function checkFile(string $file, bool $debug = false) {
    global $outputFields;

    $result = [];

    foreach ($outputFields as $f => $outputField) {
        $result[$f] = null;
    }

    $result['_log'] = '';

    $parser = new Parser();
    try {
        $pdf = $parser->parseFile($file);
        $types = array_map(static fn (PDFObject $o) => array_keys($o->getHeader()?->getElements()), $pdf->getObjects());
        if (in_array('StructTreeRoot', array_merge(...array_values($types)), true)) {
            $markInfoFound = false;
            foreach ($types as $id => $type) {
                if (in_array('MarkInfo', $type, true)) {
                    $markInfoFound = true;
                    $markInfoChildren = $pdf->getObjectById($id)?->getHeader()?->getElements()['MarkInfo'];
                    if ($markInfoChildren === null || !array_key_exists('Marked', $markInfoChildren->getElements()) || $markInfoChildren->getElements()['Marked']->getContent() === false) {
                        $result['TaggedTest'] = 'Fail';
                        $result['_log'] .= 'tagged, ';
                    } else {
                        $result['TaggedTest'] = 'Pass';
                    }
                }
            }

            if (!$markInfoFound) {
                $result['TaggedTest'] = 'Fail';
                $result['_log'] .= 'tagged, ';
            }
        } else {
            $result['TaggedTest'] = 'Fail';
            $result['_log'] .= 'tagged, ';
        }

        // check if not protected
        $result['ProtectedTest'] = 'Pass';
        $test = $pdf->getDetails();
        var_dump($test);

    } catch (Exception $e) {
    }

    return $result;
}
/*def checkFile(file, debug: bool = False):
    try:

        # check if not protected
        result['ProtectedTest'] = 'Pass'
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

        # try to detect if this PDF contains no text (ex: scanned document)
        # - if the document is not tagged and has no text, it will be inaccessible
        # - if the document is tagged and has no text, it can be accessible

        res = initAnalysis()
        for p in pdf.pages:
            res = mergeAnalyses(res, analyseContent(p))

        result['fonts'] = len(res['fontNames'])
        if (result['fonts'] != 0):
            result['_log'] += "fonts:" + ", ".join(res['fontNames'])
        result['numTxtObjects'] = res['numTxt']

        result['EmptyTextTest'] = 'Fail' if (len(res['fontNames']) == 0 or res['numTxt'] == 0) else 'Pass'

    except _qpdf.PdfError as err:
        result['BrokenFile'] = True
        result['_log'] += 'PdfError: {0}'.format(err)
    except _qpdf.PasswordError as err:
        result['BrokenFile'] = True
        result['_log'] += 'Password protected file: {0}'.format(err)
    except ValueError as err:
        result['BrokenFile'] = True
        result['_log'] += 'ValueError: {0}'.format(err)

    return result
*/

function toJSON($inputfile, bool $debug = false) {
    $result = checkFile($inputfile);
    if (!$debug) {
        unset($result['_log']);
        unset($result['fonts']);
        unset($result['numTxtObjects']);
    }

    return $result;
}