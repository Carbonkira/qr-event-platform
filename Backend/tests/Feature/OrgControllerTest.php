<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrgControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'user@example.com'): User
    {
        return User::create(['name' => 'Test User', 'email' => $email, 'password' => bcrypt('password123')]);
    }

    public function test_creating_an_organization_makes_the_creator_its_owner(): void
    {
        Sanctum::actingAs($this->makeUser());

        $response = $this->postJson('/api/orgs', ['name' => 'Acme Robotics Club'])->assertCreated();

        $this->assertSame('Acme Robotics Club', $response->json('name'));
        $this->assertSame('acme-robotics-club', $response->json('slug'));
    }

    public function test_creating_two_organizations_with_the_same_name_gets_unique_slugs(): void
    {
        Sanctum::actingAs($this->makeUser());

        $first = $this->postJson('/api/orgs', ['name' => 'Acme Club'])->assertCreated();
        $second = $this->postJson('/api/orgs', ['name' => 'Acme Club'])->assertCreated();

        $this->assertNotSame($first->json('slug'), $second->json('slug'));
    }

    public function test_mine_lists_organizations_the_user_belongs_to_with_their_role(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $this->postJson('/api/orgs', ['name' => 'My Club'])->assertCreated();

        $response = $this->getJson('/api/orgs/mine')->assertOk();

        $this->assertCount(1, $response->json());
        $this->assertSame('owner', $response->json('0.pivot.role'));
    }

    public function test_only_an_owner_can_update_the_organization_profile(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $member = $this->makeUser('member@example.com');
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $org->members()->attach($owner->id, ['role' => 'owner']);
        $org->members()->attach($member->id, ['role' => 'member']);

        Sanctum::actingAs($member);
        $this->putJson("/api/orgs/{$org->id}", ['name' => 'New Name'])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->putJson("/api/orgs/{$org->id}", ['name' => 'New Name'])->assertOk();
        $this->assertSame('New Name', $org->fresh()->name);
    }

    public function test_a_stranger_cannot_update_an_organization_they_do_not_belong_to(): void
    {
        $stranger = $this->makeUser('stranger@example.com');
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);

        Sanctum::actingAs($stranger);
        $this->putJson("/api/orgs/{$org->id}", ['name' => 'Hijacked'])->assertForbidden();
    }

    public function test_only_an_owner_can_upload_the_organization_logo(): void
    {
        Storage::fake('public');
        $owner = $this->makeUser('owner@example.com');
        $member = $this->makeUser('member@example.com');
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $org->members()->attach($owner->id, ['role' => 'owner']);
        $org->members()->attach($member->id, ['role' => 'member']);
        $file = UploadedFile::fake()->image('logo.jpg', 200, 200);

        Sanctum::actingAs($member);
        $this->postJson("/api/orgs/{$org->id}/logo", ['logo' => $file])->assertForbidden();

        Sanctum::actingAs($owner);
        $response = $this->postJson("/api/orgs/{$org->id}/logo", ['logo' => $file])->assertOk();
        $this->assertNotEmpty($response->json('logo'));
    }
}
