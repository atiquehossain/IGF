<?php

namespace Database\Seeders;

use App\Models\User;
use File;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Safe production bootstrap data only. This creates the permission
        // registry and roles, never a user or a default credential.
        foreach ([
            'auth_menus' => 'auth_menus.seed-data.json',
            'menu_actions' => 'menu_actions.seed-data.json',
            'roles' => 'roles.seed-data.json',
        ] as $table => $filename) {
            $records = json_decode(
                File::get(database_path('seeders/seed-data/' . $filename)),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            // Bootstrap is additive: rerunning the safe seeder must never
            // replace administrator-managed labels, status or role grants.
            DB::table($table)->insertOrIgnore($records);
        }

        $this->call(AdminPermissionRegistrySeeder::class);

        // \App\Models\User::factory(10)->create();
        // DB::table('users')->delete();
        // User::factory(1)->create();

        // $sequencesJson = File::get("database/seeders/seed-data/sequences.seed-data.json");
        // $sequences = json_decode($sequencesJson, true);
        // DB::table('sequences')->delete();
        // DB::table('sequences')->insert($sequences);

        // $pageMenusJson = File::get("database/seeders/seed-data/page_menus.seed-data.json");
        // $pageMenus = json_decode($pageMenusJson, true);
        // DB::table('page_menus')->delete();
        // DB::table('page_menus')->insert($pageMenus);

        // $rolesJson = File::get("database/seeders/seed-data/roles.seed-data.json");
        // $roles = json_decode($rolesJson, true);
        // DB::table('roles')->delete();
        // DB::table('roles')->insert($roles);

        // $authMenusJson = File::get("database/seeders/seed-data/auth_menus.seed-data.json");
        // $authMenus = json_decode($authMenusJson, true);
        // DB::table('auth_menus')->delete();
        // DB::table('auth_menus')->insert($authMenus);

        // $authMenuActionsJson = File::get("database/seeders/seed-data/menu_actions.seed-data.json");
        // $authMenuActions = json_decode($authMenuActionsJson, true);
        // DB::table('menu_actions')->delete();
        // DB::table('menu_actions')->insert($authMenuActions);

        // $adminsJson = File::get("database/seeders/seed-data/admins.seed-data.json");
        // $admins = json_decode($adminsJson, true);
        // DB::table('admins')->delete();
        // DB::table('admins')->insert($admins);

        // $subjectsJson = File::get("database/seeders/seed-data/subjects.seed-data.json");
        // $subjects = json_decode($subjectsJson, true);
        // DB::table('subjects')->delete();
        // DB::table('subjects')->insert($subjects);

        // $classesJson = File::get("database/seeders/seed-data/classes.seed-data.json");
        // $classes = json_decode($classesJson, true);
        // DB::table('ecw_classes')->delete();
        // DB::table('ecw_classes')->insert($classes);

        // $packagesJson = File::get("database/seeders/seed-data/packages.seed-data.json");
        // $packages = json_decode($packagesJson, true);
        // DB::table('packages')->delete();
        // DB::table('packages')->insert($packages);

        // $levelsJson = File::get("database/seeders/seed-data/levels.seed-data.json");
        // $levels = json_decode($levelsJson, true);
        // DB::table('levels')->delete();
        // DB::table('levels')->insert($levels);

        // $youtubesJson = File::get("database/seeders/seed-data/youtubes.seed-data.json");
        // $youtubes = json_decode($youtubesJson, true);
        // DB::table('you_tubes')->delete();
        // DB::table('you_tubes')->insert($youtubes);

        // $audioMusicJson = File::get("database/seeders/seed-data/audio_music.seed-data.json");
        // $audioMusice = json_decode($audioMusicJson, true);
        // DB::table('audio_music')->delete();
        // DB::table('audio_music')->insert($audioMusice);

        // $skillsJson = File::get("database/seeders/seed-data/skills.seed-data.json");
        // $skills = json_decode($skillsJson, true);
        // DB::table('skills')->delete();
        // DB::table('skills')->insert($skills);

        // $categoriesJson = File::get("database/seeders/seed-data/categories.seed-data.json");
        // $categories = json_decode($categoriesJson, true);
        // DB::table('categories')->delete();
        // DB::table('categories')->insert($categories);

        // $albumsJson = File::get("database/seeders/seed-data/albums.seed-data.json");
        // $albums = json_decode($albumsJson, true);
        // DB::table('albums')->delete();
        // DB::table('albums')->insert($albums);

        // $bannersJson = File::get("database/seeders/seed-data/banners.seed-data.json");
        // $banners = json_decode($bannersJson, true);
        // DB::table('banners')->delete();
        // DB::table('banners')->insert($banners);

        // $pagesJson = File::get("database/seeders/seed-data/pages.seed-data.json");
        // $pages = json_decode($pagesJson, true);
        // DB::table('pages')->delete();
        // DB::table('pages')->insert($pages);

    }
}
