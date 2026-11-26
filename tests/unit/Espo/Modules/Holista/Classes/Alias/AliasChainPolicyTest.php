<?php
declare(strict_types=1);

namespace tests\unit\Espo\Modules\Holista\Classes\Alias;

use Espo\Modules\Holista\Classes\Alias\AliasChainPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The alias relation allows exactly one level of indirection. Every case below is a link that the
 * operator could actually produce in the UI, including the two that must be refused.
 */
class AliasChainPolicyTest extends TestCase
{
    public function testPlainRecordIsAlwaysAllowed(): void
    {
        $this->assertNull(AliasChainPolicy::check('a', null, null));
        $this->assertTrue(AliasChainPolicy::isAllowed('a', null, null));
    }

    public function testAliasOfNonAliasIsAllowed(): void
    {
        $this->assertNull(AliasChainPolicy::check('a', 'b', null));
        $this->assertTrue(AliasChainPolicy::isAllowed('a', 'b', null));
    }

    public function testNewRecordMayBecomeAnAlias(): void
    {
        $this->assertNull(AliasChainPolicy::check(null, 'b', null));
    }

    public function testSelfReferenceIsRefused(): void
    {
        $this->assertSame(
            AliasChainPolicy::REASON_SELF_REFERENCE,
            AliasChainPolicy::check('a', 'a', null)
        );
        $this->assertFalse(AliasChainPolicy::isAllowed('a', 'a', null));
    }

    public function testAliasOfAliasIsRefused(): void
    {
        $this->assertSame(
            AliasChainPolicy::REASON_TARGET_IS_ALIAS,
            AliasChainPolicy::check('a', 'b', 'c')
        );
    }

    public function testCycleIsReportedAsACycle(): void
    {
        $this->assertSame(
            AliasChainPolicy::REASON_CYCLE,
            AliasChainPolicy::check('a', 'b', 'a')
        );
    }

    /**
     * @return array<string, array{?string, ?string, ?string, ?string}>
     */
    public static function decisionProvider(): array
    {
        return [
            'no canonical' => ['a', null, null, null],
            'flat alias' => ['a', 'b', null, null],
            'self' => ['a', 'a', null, AliasChainPolicy::REASON_SELF_REFERENCE],
            'chain' => ['a', 'b', 'c', AliasChainPolicy::REASON_TARGET_IS_ALIAS],
            'cycle' => ['a', 'b', 'a', AliasChainPolicy::REASON_CYCLE],
            'new record' => [null, 'b', null, null],
        ];
    }

    #[DataProvider('decisionProvider')]
    public function testDecisionTable(
        ?string $selfId,
        ?string $canonicalId,
        ?string $canonicalOfCanonicalId,
        ?string $expected
    ): void {
        $this->assertSame(
            $expected,
            AliasChainPolicy::check($selfId, $canonicalId, $canonicalOfCanonicalId)
        );
    }
}
