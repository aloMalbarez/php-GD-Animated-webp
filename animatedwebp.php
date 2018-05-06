#!/usr/bin/env php
<?php

function toUint32($n){
  $ar = unpack("C*", pack("L", $n));
  return $ar;
}
function toUint24($n){
  $ar = unpack("C*", pack("L", $n));
  array_pop($ar);
  return $ar;
}
function toUint16($n){
  $ar = unpack("C*", pack("S", $n));
  return $ar;
}

function bytesToString($bytes){
  return implode(array_map("chr", $bytes));
}

function binaryToBytes($bits){
  $octets = explode(' ', $bits);
  return array_map("bindec", $octets);
}

$oWidth=120;
$oHeight=20;

$frameArray = [];

function getFrameData($image, $msec){
  $w = imagesx($image);
  $h = imagesy($image);

  ob_start();
  imagewebp($image);
  if (ob_get_length() % 2 == 1) :
    echo "\0";
  endif;
  $image_data = ob_get_contents();
  ob_end_clean();
  $frameData = substr($image_data, strpos($image_data, "VP8 "));
  return Array(
    "frameData" => $frameData,
    "duration" => bytesToString(toUint24($msec)),
    "width" => bytesToString(toUint24($w - 1)),
    "height" => bytesToString(toUint24($h -1 )),
  );
}

// Create a blank image and add some text
$im = imagecreatetruecolor($oWidth, $oHeight);
$text_color = imagecolorallocate($im, 233, 14, 91);
imagestring($im, 1, 5, 5,  'WebP with PHP', $text_color);

$frameArray[] = getFrameData($im, 70);
imagedestroy($im);

// Create a blank image and add some text
$im = imagecreatetruecolor($oWidth, $oHeight);
$text_color = imagecolorallocate($im, 14, 233, 91);
imagestring($im, 1, 5, 5,  'WebP with PHP', $text_color);

$frameArray[] = getFrameData($im, 70);
imagedestroy($im);

// create new WEBP
$fileWEBP = "";
$fileHeader = "";
$fileContents = "";

$zeroPadding4 = str_repeat(chr(0), 4);
//$zeroPadding4 = "";
// Chunk HEADER VP8X
$fileContents .="VP8X";
$headChunkSize = bytesToString(toUint32(10));
// bit flags Rsv|I|L|E|X|A|R|                   Reserved
$oVP8XflagsBin = "00010010 00000000 00000000 00000000";
$oVP8Xflags = bytesToString(binaryToBytes($oVP8XflagsBin));
$oCanvasSize = bytesToString(toUint24($oWidth-1)).bytesToString(toUint24($oHeight-1));
$fileContents .= $headChunkSize. $oVP8Xflags. $oCanvasSize;
// zeropadding ?

// Chunk HEADER ANIM
$fileContents .="ANIM";
$animChunkSize = bytesToString(toUint32(6));
// loop count 16bits, 0 = infinito
// bytesToString(toUint16(0));
$oLoopCount = str_repeat(chr(0), 2);
// 32bits BGRA, Blue Green Red Alpha (0,0,0,0)
$oBackGround = str_repeat(chr(0), 4);
$fileContents .= $animChunkSize . $oBackGround . $oLoopCount;
// zeropadding ?

foreach ($frameArray as $frame) :
  // Chunk HEADER ANMF
  $fileContents .="ANMF";
  $frameDataChunkSize = bytesToString(toUint32(strlen($frame['frameData'])+16));
  // frame origin X Y
  // bytesToString(toUint24(originX)) . bytesToString(toUint24(originY))
  // (0,0)
  $fOrigin = str_repeat(chr(0), 6);
  // frame size (uint24) width-1 , (uint24) height-1
  $fSize = $frame['width'].$frame['height'];
  // frame duration in miliseconds (uint24)
  $fDuration = $frame['duration'];
  // frame options bits
  // reserved (6 bits) + alpha blending (1 bit) + descartar frame (1 bit)
  $fFlagsBin = "00000010";
  $fFlags = bytesToString(binaryToBytes($fFlagsBin));
  // chunk payload
  $fileContents .= $frameDataChunkSize.$fOrigin.$fSize.$fDuration.$fFlags.$frame['frameData'];
endforeach;

// calculate Size and build file header
$fileSize = bytesToString(toUint32(strlen($fileContents)+4));
$fileHeader = "RIFF".$fileSize."WEBP";
$fileWEBP = $fileHeader.$fileContents;

file_put_contents('animated.webp',$fileWEBP);
