<?php
/**
 * Class ReedSolomonEncoder
 *
 * @filesource   ReedSolomonEncoder.php
 * @created      07.01.2021
 * @package      chillerlan\QRCode\Common
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

namespace chillerlan\QRCode\Common;

use chillerlan\QRCode\Helpers\BitBuffer;
use SplFixedArray;

use function array_fill, array_merge, count, max;

/**
 * ISO/IEC 18004:2000 Section 8.5 ff
 *
 * @see http://www.thonky.com/qr-code-tutorial/error-correction-coding
 */
final class ReedSolomonEncoder{

	private SplFixedArray $interleavedData;
	private int           $interleavedDataIndex;

	/**
	 * ECC interleaving
	 *
	 * @return \SplFixedArray<int>
	 */
	public function interleaveEcBytes(BitBuffer $bitBuffer, Version $version, EccLevel $eccLevel):SplFixedArray{
		[$numEccCodewords, [[$l1, $b1], [$l2, $b2]]] = $version->getRSBlocks($eccLevel);

		$rsBlocks = array_fill(0, $l1, [$numEccCodewords + $b1, $b1]);

		if($l2 > 0){
			$rsBlocks = array_merge($rsBlocks, array_fill(0, $l2, [$numEccCodewords + $b2, $b2]));
		}

		$bitBufferData  = $bitBuffer->getBuffer();
		$dataBytes      = [];
		$ecBytes        = [];
		$maxDataBytes   = 0;
		$maxEcBytes     = 0;
		$dataByteOffset = 0;

		foreach($rsBlocks as $key => $block){
			[$rsBlockTotal, $dataByteCount] = $block;

			$dataBytes[$key] = [];

			for($i = 0; $i < $dataByteCount; $i++){
				$dataBytes[$key][$i] = $bitBufferData[$i + $dataByteOffset] & 0xff;
			}

			$ecByteCount    = $rsBlockTotal - $dataByteCount;
			$ecBytes[$key]  = $this->generateEcBytes($dataBytes[$key], $ecByteCount);
			$maxDataBytes   = max($maxDataBytes, $dataByteCount);
			$maxEcBytes     = max($maxEcBytes, $ecByteCount);
			$dataByteOffset += $dataByteCount;
		}

		$this->interleavedData      = new SplFixedArray($version->getTotalCodewords());
		$this->interleavedDataIndex = 0;
		$numRsBlocks                = $l1 + $l2;

		$this->interleave($dataBytes, $maxDataBytes, $numRsBlocks);
		$this->interleave($ecBytes, $maxEcBytes, $numRsBlocks);

		return $this->interleavedData;
	}

	/**
	 *
	 */
	private function generateEcBytes(array $dataBytes, int $ecByteCount):array{
		$rsPoly = new GenericGFPoly([1]);

		for($i = 0; $i < $ecByteCount; $i++){
			$rsPoly = $rsPoly->multiply(new GenericGFPoly([1, GF256::exp($i)]));
		}

		$rsPolyDegree = $rsPoly->getDegree();

		$num = (new GenericGFPoly($dataBytes, $rsPolyDegree))
			->mod($rsPoly)
			->getCoefficients()
		;

		$ecBytes = array_fill(0, $rsPolyDegree, 0);
		$count   = count($num) - count($ecBytes);

		foreach($ecBytes as $i => &$val){
			$modIndex = $i + $count;
			$val      = $modIndex >= 0 ? $num[$modIndex] : 0;
		}

		return $ecBytes;
	}

	/**
	 *
	 */
	private function interleave(array $byteArray, int $maxBytes, int $numRsBlocks):void{
		for($x = 0; $x < $maxBytes; $x++){
			for($y = 0; $y < $numRsBlocks; $y++){
				if($x < count($byteArray[$y])){
					$this->interleavedData[$this->interleavedDataIndex++] = $byteArray[$y][$x];
				}
			}
		}
	}

}
