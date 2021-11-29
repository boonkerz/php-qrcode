<?php
/**
 * Class MaskPattern
 *
 * @created      19.01.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

namespace chillerlan\QRCode\Common;

use chillerlan\QRCode\QRCodeException;
use Closure;

/**
 * ISO/IEC 18004:2000 Section 8.8.1
 */
final class MaskPattern{

	public const PATTERN_000 = 0b000;
	public const PATTERN_001 = 0b001;
	public const PATTERN_010 = 0b010;
	public const PATTERN_011 = 0b011;
	public const PATTERN_100 = 0b100;
	public const PATTERN_101 = 0b101;
	public const PATTERN_110 = 0b110;
	public const PATTERN_111 = 0b111;

	/**
	 * @var int[]
	 */
	public const PATTERNS = [
		self::PATTERN_000,
		self::PATTERN_001,
		self::PATTERN_010,
		self::PATTERN_011,
		self::PATTERN_100,
		self::PATTERN_101,
		self::PATTERN_110,
		self::PATTERN_111,
	];

	/**
	 * The current mask pattern value (0-7)
	 */
	private int $maskPattern;

	/**
	 * MaskPattern constructor.
	 *
	 * @throws \chillerlan\QRCode\QRCodeException
	 */
	public function __construct(int $maskPattern){

		if((0b111 & $maskPattern) !== $maskPattern){
			throw new QRCodeException('invalid mask pattern');
		}

		$this->maskPattern = $maskPattern;
	}

	/**
	 * Returns the current mask pattern
	 */
	public function getPattern():int{
		return $this->maskPattern;
	}

	/**
	 * Returns a closure that applies the mask for the chosen mask pattern.
	 *
	 * Encapsulates data masks for the data bits in a QR code, per ISO 18004:2006 6.8. Implementations
	 * of this class can un-mask a raw BitMatrix. For simplicity, they will unmask the entire BitMatrix,
	 * including areas used for finder patterns, timing patterns, etc. These areas should be unused
	 * after the point they are unmasked anyway.
	 *
	 * Note that the diagram in section 6.8.1 is misleading since it indicates that i is column position
	 * and j is row position. In fact, as the text says, i is row position and j is column position.
	 *
	 * @see https://www.thonky.com/qr-code-tutorial/mask-patterns
	 * @see https://github.com/zxing/zxing/blob/e9e2bd280bcaeabd59d0f955798384fe6c018a6c/core/src/main/java/com/google/zxing/qrcode/decoder/DataMask.java#L32-L117
	 */
	public function getMask():Closure{
		// $x = column (width), $y = row (height)
		return [
			self::PATTERN_000 => fn(int $x, int $y):bool => (($x + $y) % 2) === 0,
			self::PATTERN_001 => fn(int $x, int $y):bool => ($y % 2) === 0,
			self::PATTERN_010 => fn(int $x, int $y):bool => ($x % 3) === 0,
			self::PATTERN_011 => fn(int $x, int $y):bool => (($x + $y) % 3) === 0,
			self::PATTERN_100 => fn(int $x, int $y):bool => (((int)($y / 2) + (int)($x / 3)) % 2) === 0,
			self::PATTERN_101 => fn(int $x, int $y):bool => ($x * $y) % 6 === 0, // ((($x * $y) % 2) + (($x * $y) % 3)) === 0,
			self::PATTERN_110 => fn(int $x, int $y):bool => (($x * $y) % 6) < 3, // (((($x * $y) % 2) + (($x * $y) % 3)) % 2) === 0,
			self::PATTERN_111 => fn(int $x, int $y):bool => (($x + $y + (($x * $y) % 3)) % 2) === 0, // (((($x * $y) % 3) + (($x + $y) % 2)) % 2) === 0,
		][$this->maskPattern];
	}

}