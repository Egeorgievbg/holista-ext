<?php
declare(strict_types=1);

namespace tests\unit\Espo\Modules\Holista\Module;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every entity this module declares in scopes needs three things to be usable: the scope itself,
 * an entity class carrying ENTITY_TYPE, and a controller. EspoCRM generates the latter two for
 * entities created through the Entity Manager, but not for ones shipped by a module — and without
 * the controller the REST endpoint answers 404 "Controller does not exist" while the metadata and
 * the database table look perfectly healthy. That failure is silent, so it gets a test.
 */
class EntityClassPresenceTest extends TestCase
{
    private static function modulePath(): string
    {
        return dirname(__DIR__, 6) . '/src/files/custom/Espo/Modules/Holista';
    }

    /**
     * @return array<string, string>
     */
    private static function entityScopes(): array
    {
        $dir = self::modulePath() . '/Resources/metadata/scopes';
        self::assertDirectoryExists($dir);

        $result = [];

        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);
            self::assertIsArray($decoded, 'Invalid scope JSON: ' . $file);

            if (true === ($decoded['entity'] ?? false)) {
                $result[basename($file, '.json')] = self::modulePath();
            }
        }

        self::assertNotEmpty($result, 'No entity scopes found.');

        return $result;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function entityProvider(): array
    {
        $dir = dirname(__DIR__, 6) . '/src/files/custom/Espo/Modules/Holista/Resources/metadata/scopes';
        $names = [];

        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);

            if (true === ($decoded['entity'] ?? false)) {
                $name = basename($file, '.json');
                $names[$name] = [$name];
            }
        }

        return $names;
    }

    #[DataProvider('entityProvider')]
    public function testEntityClassExistsAndDeclaresItsType(string $entityType): void
    {
        $file = self::modulePath() . '/Entities/' . $entityType . '.php';

        $this->assertFileExists(
            $file,
            'Missing entity class. The ORM will not resolve ' . $entityType . '.'
        );

        $code = (string) file_get_contents($file);

        $this->assertMatchesRegularExpression(
            '/namespace\s+Espo\\\\Modules\\\\Holista\\\\Entities\s*;/',
            $code,
            'Entity class must live in the module namespace.'
        );

        $this->assertMatchesRegularExpression(
            "/ENTITY_TYPE\s*=\s*'" . preg_quote($entityType, '/') . "'/",
            $code,
            'Entity class must declare ENTITY_TYPE = ' . $entityType . '.'
        );

        $this->assertMatchesRegularExpression(
            '/extends\s+(\\\\?[A-Za-z\\\\]*\\\\)?Entity\b/',
            $code,
            'Entity class must extend the ORM Entity.'
        );
    }

    #[DataProvider('entityProvider')]
    public function testControllerExists(string $entityType): void
    {
        $file = self::modulePath() . '/Controllers/' . $entityType . '.php';

        $this->assertFileExists(
            $file,
            'Missing controller. /api/v1/' . $entityType . ' would answer 404 "Controller does not exist".'
        );

        $code = (string) file_get_contents($file);

        $this->assertMatchesRegularExpression(
            '/namespace\s+Espo\\\\Modules\\\\Holista\\\\Controllers\s*;/',
            $code,
            'Controller must live in the module namespace.'
        );

        $this->assertMatchesRegularExpression(
            '/class\s+' . preg_quote($entityType, '/') . '\s+extends\s+(\\\\?[A-Za-z\\\\]*\\\\)?Record\b/',
            $code,
            'Controller must extend the core Record controller.'
        );
    }

    public function testScopeNamesMatchTheirFileNames(): void
    {
        foreach (self::entityScopes() as $entityType => $path) {
            $decoded = json_decode(
                (string) file_get_contents($path . '/Resources/metadata/scopes/' . $entityType . '.json'),
                true
            );

            $this->assertSame(
                'Holista',
                $decoded['module'],
                'Scope "' . $entityType . '" must declare module Holista, otherwise its metadata is never merged.'
            );
        }
    }

    /**
     * Tab labels come from Global.json scopeNames, not from the entity i18n labels. Without them the
     * menu shows the raw entity type, e.g. "HolistaOrganization" next to "Клиенти".
     *
     * @dataProvider localeProvider
     */
    #[DataProvider('localeProvider')]
    public function testScopeNamesAreTranslated(string $locale): void
    {
        $file = self::modulePath() . '/Resources/i18n/' . $locale . '/Global.json';

        $this->assertFileExists($file, $locale . ' is missing the scope name translations.');

        $decoded = json_decode((string) file_get_contents($file), true);

        $this->assertIsArray($decoded);

        foreach (array_keys(self::entityScopes()) as $entityType) {
            $this->assertArrayHasKey(
                $entityType,
                $decoded['scopeNames'] ?? [],
                $locale . ' is missing scopeNames.' . $entityType . ' - the menu would show the raw name.'
            );

            $this->assertArrayHasKey(
                $entityType,
                $decoded['scopeNamesPlural'] ?? [],
                $locale . ' is missing scopeNamesPlural.' . $entityType . ' - list titles would show the raw name.'
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
}
