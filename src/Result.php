<?php

namespace Shikiryu\PDFChecker;

class Result implements \JsonSerializable
{

    public $BrokenFile = false;
    public $TaggedTest;
    public $EmptyTextTest;
    public $ProtectedTest;
    public $fonts;
    public $numTxtObjects;

    public bool $debug = false;

    public function jsonSerialize()
    {
        $data = get_class_vars(get_class($this));

        unset($data['debug']);

        if (!$this->debug) {
            unset($data['_log'], $data['fonts'], $data['numTxtObjects']);
        }

        return $data;
    }
}