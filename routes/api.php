<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

Route::group(['prefix' => 'v1', 'middleware' => ['cors', 'json.response', 'XSS', 'api.share']], function () {
    Route::post('auth/social', 'Auth\ApiAuthController@social')->middleware('throttle:10,1');
    Route::post('auth/login', 'Auth\ApiAuthController@login')->middleware('throttle:5,1');

    Route::post('auth/login-2fa', 'Auth\ApiAuthController@login2fa')->middleware('throttle:5,1');
    Route::post('auth/verify2fa', 'Auth\ApiAuthController@verify2FA')->middleware('throttle:6,1');

    // asset Url
    Route::get('profile/avatar/{id}/{size}/{img}', 'Auth\ApiAuthController@image')
        ->whereNumber('id')
        ->where('size', '350X350')
        ->where('img', '[a-f0-9]{48}\.(?:jpg|png|webp)')
        ->name('api.avatar');

    //asset end
    Route::get('menu', 'Api\MenuController@index');
    Route::get('/', 'Api\CmsController@index')->name('api.frontend.home');
    Route::get('category/{slug?}', 'Api\CmsController@category')->name('api.frontend.category');
    Route::get('page/{slug?}', 'Api\CmsController@page')->name('api.frontend.page');
    Route::get('gallery', 'Api\CmsController@gallery')->name('api.frontend.gallery');
    Route::get('story/{slug?}', 'Api\CmsController@story')->name('api.frontend.story');
    Route::get('recent-post/{type?}', 'Api\CmsController@recentPost')->name('api.frontend.recentPost');
    Route::post('page-like', 'CommentController@like')->middleware('throttle:60,1')->name('api.frontend.like');
    Route::post('page-comment', 'CommentController@comment')->middleware('throttle:5,1')->name('api.frontend.comment');
    Route::get('members', 'Api\CmsController@members')->name('api.frontend.members');
    Route::get('events', 'Api\CmsController@events')->name('api.frontend.events');

    Route::get('resources', 'Api\AllRecourcesController@index')->name('api.frontend.resources');
    Route::get('resources/alp-packages', 'Api\PackagesController@index')->name('api.frontend.resources.alp-filter');
    Route::get('resources/training-types', 'Api\TeacherTrainingTypeController@index')->name('api.frontend.resources.training-type-filter');
    Route::get('activities', 'Api\ActivitiesController@index')->name('api.frontend.activities');
    Route::get('interactive-audio', 'Api\InteractiveAudioController@index')->name('api.frontend.interactiveAudio');
});

Route::group(['prefix' => 'v1', 'middleware' => ['cors', 'json.response', 'auth:api', 'member.active', 'api.share']], function () {
    Route::post('auth/logout', 'Auth\ApiAuthController@logout');

    // YouTube
    Route::post('youtube', 'Api\YouTubeController@index')->middleware('throttle:30,1')->name('api.youtube.index');
    Route::post('youtube-meta', 'Api\YouTubeController@store')->middleware('throttle:60,1')->name('api.youtube.progress');

    // User
    Route::post('user/geo', 'Api\UserController@geo');
    Route::post('user/category', 'Api\UserController@category');
    Route::get('user/profile', 'Api\UserController@index');
    Route::post('user/profile', 'Api\UserController@store');
    Route::post('user/profile/picture-upload', 'Api\UserController@pictureUpload')->middleware('throttle:6,1')->name('api.pictureUpload');
});

Route::any('/{any}', function ($any) {
    return response()->json(['status' => false, 'message' => 'Page Not Found'], 404);
})->where('any', '.*');
