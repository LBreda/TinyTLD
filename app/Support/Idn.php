<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Helpers to normalise IDN host names between Unicode and A-label (Punycode) form.
 */
final class Idn
{
    /**
     * Convert a host name to its lowercase ASCII (A-label) form.
     *
     * @throws InvalidArgumentException
     */
    public static function toAscii(string $name): string
    {
        $name = rtrim(trim($name), '.');

        if ($name === '') {
            throw new InvalidArgumentException('Empty host name.');
        }

        $ascii = idn_to_ascii(mb_strtolower($name), IDNA_NONTRANSITIONAL_TO_ASCII | IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ, INTL_IDNA_VARIANT_UTS46, $info);

        if ($ascii === false || ($info['errors'] ?? 0) !== 0) {
            throw new InvalidArgumentException("Invalid host name [{$name}].");
        }

        return strtolower($ascii);
    }

    /**
     * Convert a host name to its Unicode (U-label) form.
     */
    public static function toUnicode(string $name): string
    {
        $unicode = idn_to_utf8(strtolower(rtrim(trim($name), '.')), IDNA_NONTRANSITIONAL_TO_UNICODE, INTL_IDNA_VARIANT_UTS46);

        return $unicode === false ? $name : $unicode;
    }

    /**
     * Whether the given name is a syntactically valid host name.
     */
    public static function isValidHostName(string $name): bool
    {
        try {
            $ascii = self::toAscii($name);
        } catch (InvalidArgumentException) {
            return false;
        }

        if (strlen($ascii) > 253) {
            return false;
        }

        foreach (explode('.', $ascii) as $label) {
            if (! preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)$/', $label)) {
                return false;
            }
        }

        return true;
    }
}
