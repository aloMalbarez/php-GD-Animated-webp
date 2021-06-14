<?php

class animatedwebp {
	
	public $outputWidth;
	public $outputHeight;

	public $frameArray = [];
	
	public function toUint32($n){
	  $ar = unpack("C*", pack("L", $n));
	  return $ar;
	}
	
	public function toUint24($n){
	  $ar = unpack("C*", pack("L", $n));
	  array_pop($ar);
	  return $ar;
	}
	
	public function toUint16($n){
	  $ar = unpack("C*", pack("S", $n));
	  return $ar;
	}

	public function bytesToString($bytes){
	  return implode(array_map("chr", $bytes));
	}

	public function binaryToBytes($bits){
	  $octets = explode(' ', $bits);
	  return array_map("bindec", $octets);
	}

	public function getFrameData($image, $msec){
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
		"duration" => $this->bytesToString($this->toUint24($msec)),
		"width" => $this->bytesToString($this->toUint24($w - 1)),
		"height" => $this->bytesToString($this->toUint24($h -1 )),
	  );
	}

	
	public function insert_image_frame($im, $duration)
	{
		$this->frameArray[] = $this->getFrameData($im, $duration);
	}

	public function generate_webp_image_rawdata()
	{

		// create new WEBP
		$fileWEBP = "";
		$fileHeader = "";
		$fileContents = "";

		// Chunk HEADER VP8X
		$fileContents .="VP8X";
		$headChunkSize = $this->bytesToString($this->toUint32(10));
		// bit flags Rsv|I|L|E|X|A|R|                   Reserved
		$oVP8XflagsBin = "00010010 00000000 00000000 00000000";
		$oVP8Xflags = $this->bytesToString($this->binaryToBytes($oVP8XflagsBin));
		$oCanvasSize = $this->bytesToString($this->toUint24($this->outputWidth-1)).$this->bytesToString($this->toUint24($this->outputHeight-1));
		$fileContents .= $headChunkSize. $oVP8Xflags. $oCanvasSize;

		// Chunk HEADER ANIM
		$fileContents .="ANIM";
		$animChunkSize = $this->bytesToString($this->toUint32(6));
		// loop count 16bits, 0 = infinito
		// bytesToString(toUint16(0));
		$oLoopCount = str_repeat(chr(0), 2);
		// 32bits BGRA, Blue Green Red Alpha (0,0,0,0)
		$oBackGround = str_repeat(chr(0), 4);
		$fileContents .= $animChunkSize . $oBackGround . $oLoopCount;

		foreach ($this->frameArray as $frame) :
		  // Chunk HEADER ANMF
		  $fileContents .="ANMF";
		  $frameDataChunkSize = $this->bytesToString($this->toUint32(strlen($frame['frameData'])+16));
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
		  $fFlags = $this->bytesToString($this->binaryToBytes($fFlagsBin));
		  // chunk payload
		  $fileContents .= $frameDataChunkSize.$fOrigin.$fSize.$fDuration.$fFlags.$frame['frameData'];
		endforeach;

		// calculate Size and build file header
		$fileSize = $this->bytesToString($this->toUint32(strlen($fileContents)+4));
		$fileHeader = "RIFF".$fileSize."WEBP";
		$fileWEBP = $fileHeader.$fileContents;

		return $fileWEBP;
		
	}
	
}

