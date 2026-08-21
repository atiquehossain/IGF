<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PageController;
use App\Models\Page;
use App\Models\PageTagModule;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class PageTrashRelationshipIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_trash_and_restore_preserve_tag_relationships(): void
    {
        $page = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tagged story',
            'sub_title' => 'Relationship recovery test',
            'slug' => 'tagged-story',
            'status' => 1,
            'language' => 'en',
        ]);
        $tag = Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Education',
            'slug' => 'education-' . Str::lower(Str::random(6)),
            'status' => 1,
        ]);
        $link = PageTagModule::create([
            'uuid' => (string) Str::uuid(),
            'page_id' => $page->id,
            'tag_id' => $tag->id,
        ]);

        $controller = app(PageController::class);
        $deleteRequest = Request::create('/admin/page/' . $page->uuid, 'DELETE');
        $deleteRequest->Lang = $this->adminLanguage();
        $this->assertSame(200, $controller->destroy($page->uuid, $deleteRequest)->getStatusCode());

        $this->assertSoftDeleted('pages', ['id' => $page->id]);
        $this->assertDatabaseHas('page_tag_modules', ['id' => $link->id, 'page_id' => $page->id, 'tag_id' => $tag->id]);

        $restoreRequest = Request::create('/admin/page-trash/' . $page->uuid . '/restore', 'POST');
        $restoreRequest->Lang = $this->adminLanguage();
        $this->assertSame(200, $controller->restore($page->uuid, $restoreRequest)->getStatusCode());

        $this->assertNotSoftDeleted('pages', ['id' => $page->id]);
        $this->assertDatabaseHas('page_tag_modules', ['id' => $link->id, 'page_id' => $page->id, 'tag_id' => $tag->id]);
    }

    private function adminLanguage(): object
    {
        return (object) ['Common' => (object) ['Form' => (object) [
            'DeleteSuccessfully' => 'Deleted',
            'NotDelete' => 'Delete failed',
        ]]];
    }
}
