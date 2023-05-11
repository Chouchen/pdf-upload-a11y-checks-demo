<?php

include 'vendor/autoload.php';

// TODO check if POST

$pdf = $_FILES['pdf'];

$checker = new Shikiryu\PDFChecker\PDFChecker($pdf['tmp_name']);
$checker->checkFile();

$json = json_encode($checker->result, JSON_THROW_ON_ERROR);

echo $json; exit;