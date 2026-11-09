<?php

namespace Tests\Feature;

use App\Models\User\AccessGroup;
use App\Models\User\AccessGroupKey;
use App\Models\User\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PackageCreateAccessGroupTest extends TestCase
{
    private const PACKAGE_CREATE_ROUTE_NAME = 'v4_bible_filesets_download.package_create';
    private const KNOWN_FILESET_ID = 'ENGKJVN1DA';
    private const ENCRYPTION_TYPE = 2;
    private const FORBIDDEN_MESSAGE = 'This API key is not allowed to create packages.';
    private const NON_EXISTENT_GROUP_ID = 99999991;

    private int $existingUserId;
    private int $allowedGroupId;
    private int $outsideGroupId;
    private string $allowedKey;
    private string $outsideKey;
    private string $keyWithoutGroups;
    /** @var int[] */
    private array $createdKeyIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->existingUserId = (int) Key::query()->value('user_id');
        if ($this->existingUserId === 0) {
            $this->markTestSkipped('Needs at least one existing user key in dbp_users.user_keys.');
        }

        $this->allowedGroupId = (int) AccessGroup::create([
            'name' => 'Package Create Test Allowed ' . Str::random(8),
            'description' => 'Temporary access group for package-create feature tests.',
        ])->id;

        $this->outsideGroupId = (int) AccessGroup::create([
            'name' => 'Package Create Test Outside ' . Str::random(8),
            'description' => 'Temporary outside-group access group for package-create feature tests.',
        ])->id;

        $this->allowedKey = $this->createApiKeyForTests();
        $this->outsideKey = $this->createApiKeyForTests();
        $this->keyWithoutGroups = $this->createApiKeyForTests();

        AccessGroupKey::create([
            'access_group_id' => $this->allowedGroupId,
            'key_id' => $this->getKeyIdByKeyValue($this->allowedKey),
        ]);

        AccessGroupKey::create([
            'access_group_id' => $this->outsideGroupId,
            'key_id' => $this->getKeyIdByKeyValue($this->outsideKey),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdKeyIds as $keyId) {
            AccessGroupKey::where('key_id', $keyId)->delete();
        }

        Key::whereIn('id', $this->createdKeyIds)->delete();
        AccessGroup::whereIn('id', [$this->allowedGroupId, $this->outsideGroupId])->delete();

        parent::tearDown();
    }

    /**
     * @group package_create
     * @test
     */
    public function package_create_refuses_when_setting_is_empty(): void
    {
        config(['settings.package_create_access_group_ids' => '']);

        Http::fake();
        $response = $this->callPackageCreate($this->allowedKey);

        $response->assertStatus(403)->assertJson([
            'error' => [
                'message' => self::FORBIDDEN_MESSAGE,
                'status_code' => 403,
            ],
        ]);
        Http::assertNothingSent();
    }

    /**
     * @group package_create
     * @test
     */
    public function package_create_refuses_when_setting_contains_no_numeric_group_ids(): void
    {
        config(['settings.package_create_access_group_ids' => 'abc,not_a_number']);

        Http::fake();
        $response = $this->callPackageCreate($this->allowedKey);

        $response->assertStatus(403)->assertJson([
            'error' => [
                'message' => self::FORBIDDEN_MESSAGE,
                'status_code' => 403,
            ],
        ]);
        Http::assertNothingSent();
    }

    /**
     * @group package_create
     * @test
     */
    public function package_create_calls_hub_when_key_belongs_to_allowed_group(): void
    {
        config(['settings.package_create_access_group_ids' => (string) $this->allowedGroupId]);

        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->callPackageCreate($this->allowedKey);

        $response->assertStatus(200)->assertJson(['success' => true]);
        Http::assertSentCount(1);
    }

    /**
     * @group package_create
     * @test
     */
    public function package_create_refuses_when_key_is_outside_allowed_group(): void
    {
        config(['settings.package_create_access_group_ids' => (string) $this->allowedGroupId]);

        Http::fake();
        $response = $this->callPackageCreate($this->outsideKey);

        $response->assertStatus(403)->assertJson([
            'error' => [
                'message' => self::FORBIDDEN_MESSAGE,
                'status_code' => 403,
            ],
        ]);
        Http::assertNothingSent();
    }

    /**
     * @group package_create
     * @test
     */
    public function package_create_refuses_key_with_no_groups_even_if_request_spoofs_middleware_access_group_ids(): void
    {
        config(['settings.package_create_access_group_ids' => (string) $this->allowedGroupId]);

        Http::fake();
        $response = $this->callPackageCreate($this->keyWithoutGroups, [
            'middleware_access_group_ids' => [$this->allowedGroupId],
        ]);

        $response->assertStatus(403)->assertJson([
            'error' => [
                'message' => self::FORBIDDEN_MESSAGE,
                'status_code' => 403,
            ],
        ]);
        Http::assertNothingSent();
    }

    /**
     * @group package_create
     * @test
     */
    public function package_create_refuses_when_allowed_group_is_listed_but_does_not_exist_in_content_database(): void
    {
        config(['settings.package_create_access_group_ids' => (string) self::NON_EXISTENT_GROUP_ID]);

        $this->seedAccessControlCacheForKey($this->allowedKey, [self::NON_EXISTENT_GROUP_ID]);

        Http::fake();
        $response = $this->callPackageCreate($this->allowedKey);

        $response->assertStatus(403)->assertJson([
            'error' => [
                'message' => self::FORBIDDEN_MESSAGE,
                'status_code' => 403,
            ],
        ]);
        Http::assertNothingSent();
    }

    private function callPackageCreate(string $apiKey, array $extraPayload = [])
    {
        $this->clearAccessControlCacheForKey($apiKey);

        $payload = array_merge([
            'filesets' => [self::KNOWN_FILESET_ID],
            'encryptionType' => self::ENCRYPTION_TYPE,
        ], $extraPayload);

        return $this->postJson(
            route(self::PACKAGE_CREATE_ROUTE_NAME, ['v' => 4, 'key' => $apiKey]),
            $payload
        );
    }

    private function createApiKeyForTests(): string
    {
        $keyValue = 'pkg-create-test-' . Str::lower(Str::random(28));

        $key = Key::create([
            'user_id' => $this->existingUserId,
            'key' => $keyValue,
            'name' => 'Package create test key',
            'description' => 'Temporary API key for package-create feature tests.',
        ]);

        $this->createdKeyIds[] = (int) $key->id;
        return $keyValue;
    }

    private function getKeyIdByKeyValue(string $apiKey): int
    {
        return (int) Key::where('key', $apiKey)->firstOrFail()->id;
    }

    private function clearAccessControlCacheForKey(string $apiKey): void
    {
        $cacheKey = generateCacheString(
            'access_control_middleware',
            removeSpaceAndCntrlFromCacheParameters([$apiKey])
        );

        Cache::store('array')->forget($cacheKey);
    }

    private function seedAccessControlCacheForKey(string $apiKey, array $groupIds): void
    {
        $cacheKey = generateCacheString(
            'access_control_middleware',
            removeSpaceAndCntrlFromCacheParameters([$apiKey])
        );

        Cache::store('array')->put($cacheKey, $groupIds, 60);
    }
}
