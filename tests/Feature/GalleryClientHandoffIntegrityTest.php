<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Album;
use App\Models\Gallery;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GalleryClientHandoffIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_gallery_honors_album_visibility_accessible_text_and_deterministic_priority(): void
    {
        $zeta = $this->album('Zeta programs', true);
        $alpha = $this->album('Alpha programs', true);
        $hidden = $this->album('Internal review', false);

        $this->photo('Low priority', $zeta, 10, true, 'A lower-priority community photo.');
        $this->photo('Tied priority first', $zeta, 50, true, '<strong>People</strong> working together.');
        $this->photo('Tied priority newest', $zeta, 50, true, '  Volunteers   preparing supplies. ');
        $this->photo('Caption fallback', $alpha, 40, true, null);
        $this->photo('Draft photo', $zeta, 100, false, 'This must stay private.');
        $this->photo('Photo in draft album', $hidden, 200, true, 'This must stay private too.');
        $this->photo('Legacy unfiled photo', null, null, true, 'Legacy photo description.');

        $this->get(route('frontend.gallery'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gallery')
                ->where('properties.total_count', 5)
                ->where('data.albums.0.name', 'Alpha programs')
                ->where('data.albums.1.name', 'Zeta programs')
                ->where('data.items.0.name', 'Tied priority newest')
                ->where('data.items.0.alt_text', 'Volunteers preparing supplies.')
                ->where('data.items.1.name', 'Tied priority first')
                ->where('data.items.1.alt_text', 'People working together.')
                ->where('data.items.2.name', 'Caption fallback')
                ->where('data.items.2.alt_text', 'Caption fallback')
                ->where('data.items.3.name', 'Low priority')
                ->where('data.items.4.name', 'Legacy unfiled photo')
                ->missing('data.items.5'));
    }

    public function test_admin_gallery_and_album_screens_explain_preview_visibility_and_ordering(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->owner(), 'admin');

        $publishedAlbum = $this->album('Published programs', true);
        $draftAlbum = $this->album('Draft programs', false);
        $visible = $this->photo('Community workshop', $publishedAlbum, 70, true, 'Participants working around a table.');
        $hidden = $this->photo('Awaiting approval', $draftAlbum, 60, true, null);
        $this->photo('Album draft photo', $publishedAlbum, 50, false, null);

        $this->get(route('gallery.index'))
            ->assertOk()
            ->assertSee('View live gallery')
            ->assertSee('Photo caption')
            ->assertSee('Display priority')
            ->assertSee('Public visibility')
            ->assertSee('Preview live item')
            ->assertSee('Hidden — album draft')
            ->assertSee('data-album-published="0"', false)
            ->assertSee('70');

        $this->get(route('gallery.edit', $hidden->uuid))
            ->assertOk()
            ->assertSee('Hidden because its album is not published.')
            ->assertSee('Image description (alternative text)')
            ->assertSee('If blank, the photo caption is used.')
            ->assertSee('value="'.$draftAlbum->id.'" selected', false)
            ->assertSee('value="60"', false);

        $this->get(route('gallery.create'))
            ->assertOk()
            ->assertSee('New photos start as drafts.')
            ->assertSee('data-e2e="gallery-order-by"', false)
            ->assertSee('Photo caption');

        $this->get(route('album.index'))
            ->assertOk()
            ->assertSee('Publishing an album makes its published photos eligible')
            ->assertSee('Preview live album')
            ->assertSee('1 of 2 published')
            ->assertSee('Draft — photos hidden')
            ->assertSee(route('frontend.gallery', ['album_id' => $publishedAlbum->id]), false);

        $this->put(route('gallery.status', $visible->uuid), [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('status', false);
    }

    public function test_gallery_priority_is_shared_across_translations_and_omitted_fields_are_preserved(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->owner('gallery-translation-owner'), 'admin');

        $uuid = (string) Str::uuid();
        $englishAlbum = $this->album('English album', true, 'en', (string) Str::uuid());
        $banglaAlbum = $this->album('Bangla album', true, 'bn', $englishAlbum->uuid);
        $english = $this->photo('English caption', $englishAlbum, 20, true, 'Original English description.', 'en', $uuid);
        $bangla = $this->photo('Bangla caption', $banglaAlbum, 20, true, 'Original Bangla description.', 'bn', $uuid);

        $this->put(route('gallery.update'), [
            'uuid' => $uuid,
            'language' => ['en', 'bn'],
            'id' => ['en' => (string) $english->id, 'bn' => (string) $bangla->id],
            'name' => ['en' => 'Updated English caption', 'bn' => 'Updated Bangla caption'],
            'description' => ['en' => 'Updated English description.', 'bn' => 'Updated Bangla description.'],
            'album_id' => ['en' => $englishAlbum->id, 'bn' => $banglaAlbum->id],
            'order_by' => 85,
        ])->assertRedirect(route('gallery.index'))
            ->assertSessionHas('alert-type', 'success');

        $this->assertSame(85, (int) $english->fresh()->order_by);
        $this->assertSame(85, (int) $bangla->fresh()->order_by);

        $this->put(route('gallery.update'), [
            'uuid' => $uuid,
            'language' => ['en'],
            'id' => ['en' => (string) $english->id],
            'name' => ['en' => 'Compatible English caption'],
            'album_id' => ['en' => $englishAlbum->id],
        ])->assertRedirect(route('gallery.index'))
            ->assertSessionHas('alert-type', 'success');

        $this->assertSame(85, (int) $english->fresh()->order_by);
        $this->assertSame('Updated English description.', $english->fresh()->description);
    }

    private function album(string $name, bool $published, string $language = 'en', ?string $uuid = null): Album
    {
        return Album::create([
            'uuid' => $uuid ?: (string) Str::uuid(),
            'name' => $name,
            'language' => $language,
            'status' => $published ? 1 : 0,
        ]);
    }

    private function photo(
        string $name,
        ?Album $album,
        ?int $priority,
        bool $published,
        ?string $description,
        string $language = 'en',
        ?string $uuid = null,
    ): Gallery {
        return Gallery::create([
            'uuid' => $uuid ?: (string) Str::uuid(),
            'name' => $name,
            'description' => $description,
            'type' => 'gallery',
            'path' => Str::slug($name).'.jpg',
            'language' => $language,
            'album_id' => $album?->id,
            'order_by' => $priority,
            'status' => $published ? 1 : 0,
        ]);
    }

    private function owner(string $username = 'gallery-handoff-owner'): Admin
    {
        $role = Role::query()->where('is_owner', true)->firstOrFail();
        $role->forceFill(['status' => 1, 'security_rank' => 0])->save();

        return Admin::create([
            'name' => 'Gallery handoff owner',
            'username' => $username,
            'email' => $username.'@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('Strong-Test-Password!23'),
            'must_change_password' => false,
        ]);
    }
}
