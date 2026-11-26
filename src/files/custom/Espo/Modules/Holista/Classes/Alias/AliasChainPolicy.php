<?php
declare(strict_types=1);

namespace Espo\Modules\Holista\Classes\Alias;

/**
 * Decides whether a site may point at another site as its canonical record.
 *
 * A site that was entered twice is kept as its own row and linked to the canonical one, so that
 * history and source references survive. The relation contract allows exactly one level of that
 * indirection: an alias must point at a record that is not itself an alias. That single rule also
 * makes cycles unrepresentable, which is why the check needs only three inputs.
 */
final class AliasChainPolicy
{
    public const REASON_SELF_REFERENCE = 'selfReference';
    public const REASON_TARGET_IS_ALIAS = 'targetIsAlias';
    public const REASON_CYCLE = 'cycle';

    /**
     * @param ?string $selfId The id of the record being saved; null for a record that does not exist yet.
     * @param ?string $canonicalId The canonical record the saved record would point at.
     * @param ?string $canonicalOfCanonicalId The canonical record's own canonical, if it has one.
     * @return ?string A reason code when the link must be refused, null when it is acceptable.
     */
    public static function check(
        ?string $selfId,
        ?string $canonicalId,
        ?string $canonicalOfCanonicalId
    ): ?string {
        if ($canonicalId === null) {
            return null;
        }

        if ($selfId !== null && $selfId === $canonicalId) {
            return self::REASON_SELF_REFERENCE;
        }

        if ($canonicalOfCanonicalId === null) {
            return null;
        }

        if ($selfId !== null && $canonicalOfCanonicalId === $selfId) {
            return self::REASON_CYCLE;
        }

        return self::REASON_TARGET_IS_ALIAS;
    }

    public static function isAllowed(
        ?string $selfId,
        ?string $canonicalId,
        ?string $canonicalOfCanonicalId
    ): bool {
        return self::check($selfId, $canonicalId, $canonicalOfCanonicalId) === null;
    }
}
