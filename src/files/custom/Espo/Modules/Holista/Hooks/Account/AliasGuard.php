<?php
declare(strict_types=1);

namespace Espo\Modules\Holista\Hooks\Account;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\Holista\Classes\Alias\AliasChainPolicy;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Refuses an alias link that would point at itself, at another alias, or back at the record
 * through a second hop. The decision itself lives in AliasChainPolicy; this class only does the
 * lookups and turns a refusal into a 400 with a message the operator can act on.
 *
 * @implements BeforeSave<Entity>
 */
final class AliasGuard implements BeforeSave
{
    private const ENTITY_TYPE = 'Account';

    public function __construct(private readonly EntityManager $entityManager)
    {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $canonicalId = self::normaliseId($entity->get('canonicalSiteId'));

        if (null === $canonicalId) {
            return;
        }

        $canonical = $this->entityManager->getEntityById(self::ENTITY_TYPE, $canonicalId);

        if (null === $canonical) {
            // A dangling reference is the relation's problem, not this guard's.
            return;
        }

        $reason = AliasChainPolicy::check(
            self::normaliseId($entity->getId()),
            $canonicalId,
            self::normaliseId($canonical->get('canonicalSiteId'))
        );

        if (null === $reason) {
            return;
        }

        throw new BadRequest(self::message($reason));
    }

    private static function normaliseId(mixed $value): ?string
    {
        if (!is_string($value) || '' === $value) {
            return null;
        }

        return $value;
    }

    private static function message(string $reason): string
    {
        return match ($reason) {
            AliasChainPolicy::REASON_SELF_REFERENCE =>
                'A site cannot be its own canonical record.',
            AliasChainPolicy::REASON_CYCLE =>
                'This link would create a loop between two sites.',
            default =>
                'The target is itself an alias. Link to its canonical record instead.',
        };
    }
}
