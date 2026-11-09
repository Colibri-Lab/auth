<?php

namespace App\Modules\Auth\Ldap;

use InvalidArgumentException;

/**
 * Encodes and decodes the definite-length BER subset used by LDAPv3.
 *
 * @class
 * @memberof App\Modules\Auth\Ldap
 * @extends Object
 */
class Ber
{
    /**
     * Maximum accepted LDAP message size, including its envelope.
     * @const int
     */
    public const MaxMessageSize = 1048576;

    /**
     * Encodes a single-byte BER tag and its contents.
     * @public
     * @static
     * @param int $tag BER tag.
     * @param string $value Encoded contents.
     * @return string Encoded element.
     */
    public static function Encode(int $tag, string $value): string
    {
        $length = strlen($value);
        $encodedLength = '';
        if ($length < 128) {
            $encodedLength = chr($length);
        } else {
            while ($length > 0) {
                $encodedLength = chr($length & 255) . $encodedLength;
                $length >>= 8;
            }
            $encodedLength = chr(128 | strlen($encodedLength)) . $encodedLength;
        }
        return chr($tag) . $encodedLength . $value;
    }

    /**
     * Encodes a nonnegative BER integer or enumeration.
     * @public
     * @static
     * @param int $value Integer value.
     * @param int $tag Integer or enumeration tag.
     * @return string Encoded element.
     */
    public static function Integer(int $value, int $tag = 2): string
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Negative LDAP integer');
        }
        $bytes = '';
        do {
            $bytes = chr($value & 255) . $bytes;
            $value >>= 8;
        } while ($value > 0);
        if (ord($bytes[0]) >= 128) {
            $bytes = "\0" . $bytes;
        }
        return self::Encode($tag, $bytes);
    }

    /**
     * Decodes a nonnegative LDAP integer with a signed 32-bit upper bound.
     * @public
     * @static
     * @param string $bytes Integer contents.
     * @return int Decoded value.
     */
    public static function Number(string $bytes): int
    {
        if ($bytes === '' || strlen($bytes) > 4 || ord($bytes[0]) >= 128) {
            throw new InvalidArgumentException('Invalid LDAP integer');
        }
        $value = 0;
        foreach (str_split($bytes) as $byte) {
            $value = ($value << 8) | ord($byte);
        }
        return $value;
    }

    /**
     * Removes one complete element, retaining incomplete network input.
     * @public
     * @static
     * @param string $buffer Buffered network input.
     * @return string|null Complete element or null for incomplete input.
     */
    public static function TakeFrame(string &$buffer): ?string
    {
        if ($buffer !== '' && ord($buffer[0]) !== 48) {
            throw new InvalidArgumentException('Expected LDAP sequence');
        }
        $header = self::_header($buffer);
        if ($header === null) {
            return null;
        }
        [$offset, $length] = $header;
        if (strlen($buffer) < $offset + $length) {
            return null;
        }
        $frame = substr($buffer, 0, $offset + $length);
        $buffer = substr($buffer, $offset + $length);
        return $frame;
    }

    /**
     * Consumes one complete BER element with an optional expected tag.
     * @public
     * @static
     * @param string $buffer Encoded elements.
     * @param int|null $expected Expected tag.
     * @return array{tag: int, value: string} Decoded element.
     */
    public static function Read(string &$buffer, ?int $expected = null): array
    {
        $header = self::_header($buffer);
        if ($header === null || strlen($buffer) < array_sum($header)) {
            throw new InvalidArgumentException('Truncated BER element');
        }
        [$offset, $length] = $header;
        $tag = ord($buffer[0]);
        if (($tag & 31) === 31 || ($expected !== null && $tag !== $expected)) {
            throw new InvalidArgumentException('Unexpected BER tag');
        }
        $value = substr($buffer, $offset, $length);
        $buffer = substr($buffer, $offset + $length);
        return ['tag' => $tag, 'value' => $value];
    }

    /**
     * Parses a bounded definite-length BER header.
     * @private
     * @ignore
     * @static
     * @param string $buffer Encoded input.
     * @return array{int, int}|null Header size and payload size.
     */
    private static function _header(string $buffer): ?array
    {
        if (strlen($buffer) < 2) {
            return null;
        }
        $length = ord($buffer[1]);
        $offset = 2;
        if ($length >= 128) {
            $count = $length & 127;
            if ($count === 0 || $count > 4) {
                throw new InvalidArgumentException('Unsupported BER length');
            }
            if (strlen($buffer) < 2 + $count) {
                return null;
            }
            $length = 0;
            for ($index = 0; $index < $count; $index++) {
                $length = ($length << 8) | ord($buffer[2 + $index]);
            }
            $offset += $count;
        }
        if ($offset + $length > self::MaxMessageSize) {
            throw new InvalidArgumentException('LDAP message exceeds size limit');
        }
        return [$offset, $length];
    }
}