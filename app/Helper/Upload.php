<?php

namespace App\Helper;

use App\Helper\Str as CustomStr;
use Illuminate\Support\Str;
use File;
use Image;

class Upload
{

    public static function _fileUpload($file = null, $id = null, $tb_name = null)
    {
        $publicpath = storage_path('app');
        $imgSize = getimagesize($file);
        $imageName = Str::snake($file->getClientOriginalName());
        $dr = $publicpath . '/uploads/' . $tb_name . '/' . $id . '/';
        if (!file_exists($dr)) {
            File::makeDirectory($dr, 0775, true, true);
        }
        $dr = $dr . $imageName;
        $img = Image::make($file);
        $img->save($dr, 75);
        return $imageName;
    }

    public static function _image($file = null, $id = null, $tb_name = null, $size = [
        array('w' => 100, 'h' => 100),
        array('w' => 119, 'h' => 100),
        array('w' => 150, 'h' => 150)
    ])
    {
        try {
            $imgSize = getimagesize($file);
            $width = $imgSize[0];
            $height = $imgSize[1];
            $imageName = Str::snake($file->getClientOriginalName());
            foreach ($size as $data) {
                Upload::_resize($file, $id, $tb_name, $imageName, $data['w'], $data['h']);
            }
            return $imageName;
        } catch (\Exception $e) {
            return '';
        }
    }

    protected static function _resize($file = null, $id = null, $tb_name = null, $imageName, $width, $height)
    {
        // $publicpath = Upload::getPath();
        $publicpath = storage_path('app/public/photos/1/');
        if ($width == 100) {
            $imgSize = getimagesize($file);
            $dr = $publicpath . $tb_name . '/' . $id . '/main/';

            if (!file_exists($dr)) {
                File::makeDirectory($dr, 0775, true, true);
            }
            $dr = $dr . $imageName;
            $img = Image::make($file)->resize($imgSize[0], $imgSize[1]);
            $img->save($dr, 75);
        } else {
            $dr = $publicpath . $tb_name . '/' . $id . '/' . $width . 'X' . $height . '/';
            if (!file_exists($dr)) {
                File::makeDirectory($dr, 0775, true, true);
            }
            $dr = $dr . $imageName;
            $img = Image::make($file)->resize($width, $height);
            $img->save($dr, 75);
        }
    }

    public static function fileResize($file = null, $id = null, $tb_name = null, $imageName, $width, $height = 0)
    {
        $publicpath = storage_path('app/public/photos/1/' . $tb_name . '/');
        if (empty($height)) {
            $imgSize = getimagesize($file);
            $ratio = $imgSize[0] / $width;
            $height = round($imgSize[1] / $ratio);
        }
        $dr = $publicpath;
        if (!file_exists($dr)) {
            File::makeDirectory($dr, 0775, true, true);
        }
        $dr = $dr . $imageName;
        $img = Image::make($file)->resize($width, $height);
        $img->save($dr, 75);

        return $imageName;
    }

    public static function uploadAll($file = null, $tb_name = null, $id = '')
    {

        $publicpath = storage_path('app/public/photos/1/' . $tb_name . '/');

        if (!file_exists($publicpath)) {
            File::makeDirectory($publicpath, 0775, true, true);
        }

        $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $fileName =  CustomStr::slug($fileName) . '-' . time() . '-' . $id . '.' . $file->getClientOriginalExtension();

        $file->move($publicpath, $fileName);
        return $fileName;
    }
}
