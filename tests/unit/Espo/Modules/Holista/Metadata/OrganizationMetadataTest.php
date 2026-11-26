<?php
declare(strict_types=1);

namespace tests\unit\Espo\Modules\Holista\Metadata;

use PHPUnit\Framework\TestCase;

/**
 * Guards the HolistaOrganization metadata against the invariants from ADR-002:
 * a chain group is not a legal entity, every declared link resolves on both
 * sides, and layouts and translations never drift from the field list.
 */
class OrganizationMetadataTest extends TestCase
{
    private const ENTITY = 'HolistaOrganization';
    private const MODULE = 'Holista';

    private static function resourcesPath(): string
    {
        return dirname(__DIR__, 6) . '/src/files/custom/Espo/Modules/' . self::MODULE . '/Resources';
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $path): array
    {
        self::assertFileExists($path);

        $decoded = json_decode((string) file_get_contents($path), true);

        self::assertIsArray($decoded, 'Invalid JSON: ' . $path);

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private static function metadata(string $type, string $entity): array
    {
        return self::decode(self::resourcesPath() . '/metadata/' . $type . '/' . $entity . '.json');
    }

    /**
     * @return array<string, mixed>
     */
    private static function translation(string $locale): array
    {
        return self::decode(self::resourcesPath() . '/i18n/' . $locale . '/' . self::ENTITY . '.json');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function fields(): array
    {
        return self::metadata('entityDefs', self::ENTITY)['fields'];
    }

    public function testScopeMakesItARecordEntity(): void
    {
        $scope = self::metadata('scopes', self::ENTITY);

        $this->assertTrue($scope['entity']);
        $this->assertTrue($scope['object']);
        $this->assertTrue($scope['acl']);
        $this->assertTrue($scope['tab']);
        $this->assertSame(self::MODULE, $scope['module']);
    }

    public function testOnlyNameIsRequired(): void
    {
        foreach (self::fields() as $name => $field) {
            if ('name' === $name) {
                $this->assertTrue($field['required']);

                continue;
            }

            $this->assertArrayNotHasKey(
                'required',
                $field,
                'Field "' . $name . '" must stay nullable: missing data is not zero and not an empty string.'
            );
        }
    }

    public function testProvenanceFieldsArePresent(): void
    {
        $fields = self::fields();

        foreach (['legacyId', 'sourceSystem', 'sourceRecordKey', 'migrationBatchId', 'sourceRef', 'qaStatus'] as $name) {
            $this->assertArrayHasKey($name, $fields, 'Missing provenance field: ' . $name);
        }
    }

    public function testNoLegalIdentityFieldIsInvented(): void
    {
        foreach (array_keys(self::fields()) as $name) {
            $this->assertDoesNotMatchRegularExpression(
                '/eik|bulstat|registration|vat|uin/i',
                (string) $name,
                'A chain group is not a legal entity (ADR-002). Legal identity belongs on Account.legalName.'
            );
        }
    }

    public function testEveryLinkFieldHasAMatchingLinkDefinition(): void
    {
        $entityDefs = self::metadata('entityDefs', self::ENTITY);
        $links = $entityDefs['links'] ?? [];

        foreach ($entityDefs['fields'] as $name => $field) {
            if ('link' !== ($field['type'] ?? null)) {
                continue;
            }

            $this->assertArrayHasKey($name, $links, 'Link field "' . $name . '" has no entry in links.');
            $this->assertSame($field['entity'], $links[$name]['entity'] ?? null);
            $this->assertSame($field['foreign'] ?? null, $links[$name]['foreign'] ?? null);
        }
    }

    public function testAccountGroupLinkIsSymmetrical(): void
    {
        $organizationLinks = self::metadata('entityDefs', self::ENTITY)['links'];
        $accountDefs = self::metadata('entityDefs', 'Account');

        $this->assertSame('hasMany', $organizationLinks['accounts']['type']);
        $this->assertSame('Account', $organizationLinks['accounts']['entity']);
        $this->assertSame('group', $organizationLinks['accounts']['foreign']);

        $this->assertSame('belongsTo', $accountDefs['links']['group']['type']);
        $this->assertSame(self::ENTITY, $accountDefs['links']['group']['entity']);
        $this->assertSame('accounts', $accountDefs['links']['group']['foreign']);

        $this->assertSame('link', $accountDefs['fields']['group']['type']);
        $this->assertSame(self::ENTITY, $accountDefs['fields']['group']['entity']);
    }

    /**
     * @dataProvider layoutProvider
     */
    public function testLayoutsOnlyReferenceDeclaredFields(string $layout): void
    {
        $rows = self::metadata('layouts/' . self::ENTITY, $layout);
        $declared = array_merge(array_keys(self::fields()), ['id']);

        foreach ($rows as $row) {
            foreach ((array) $row as $cell) {
                $name = $cell['name'] ?? null;

                $this->assertNotNull($name, 'Layout cell without a name in ' . $layout . '.');
                $this->assertContains(
                    $name,
                    $declared,
                    'Layout "' . $layout . '" references undeclared field "' . $name . '".'
                );
            }
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function layoutProvider(): array
    {
        return [
            'list' => ['list'],
            'detail' => ['detail'],
        ];
    }

    /**
     * @dataProvider localeProvider
     */
    public function testTranslationsCoverEveryField(string $locale): void
    {
        $translation = self::translation($locale);

        foreach (array_keys(self::fields()) as $name) {
            $this->assertArrayHasKey(
                $name,
                $translation['fields'],
                $locale . ' is missing a label for field "' . $name . '".'
            );
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function localeProvider(): array
    {
        return [
            'bg_BG' => ['bg_BG'],
            'en_US' => ['en_US'],
        ];
    }

    /**
     * @dataProvider localeProvider
     */
    public function testEveryKindOptionIsTranslated(string $locale): void
    {
        $translation = self::translation($locale);

        foreach (self::fields()['organizationKind']['options'] as $option) {
            $this->assertArrayHasKey(
                $option,
                $translation['options']['organizationKind'] ?? [],
                $locale . ' is missing a label for option "' . $option . '".'
            );
        }
    }
}
