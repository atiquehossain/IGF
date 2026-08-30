<?php

namespace App\Helper;

use Exception;

class StaticUtil {

    public static function pageRoute() {
        $language = Translation::language()['admin']['Menu'];
        $arr = (array) [
            (object) ['id' => 'frontend.home', 'name' => $language['Home'], 'type' => ''],
            (object) ['id' => 'frontend.category', 'name' => $language['Category'], 'type' => 'category'],
            (object) ['id' => 'frontend.page', 'name' => $language['Page'], 'type' => 'page'],
            // (object) ['id' => 'frontend.gallery', 'name' => $language['Gallery'], 'type' => ''],
            // (object) ['id' => 'frontend.recentPost', 'name' => $language['recent-post-slug'], 'type' => 'category'],
            (object) ['id' => 'frontend.contactUs', 'name' => $language['ContactUs'], 'type' => 'page'],
            (object) ['id' => 'frontend.annual_report.index', 'name' => $language['AnnualReportTitle'], 'type' => 'page'],
            // (object) ['id' => 'frontend.join', 'name' => $language['Join'], 'type' => 'page'],
            // (object) ['id' => 'frontend.events', 'name' => $language['Events'], 'type' => ''],
            (object) ['id' => 'frontend.donate.direct', 'name' => 'Make a Donation', 'type' => 'page'],
            (object) ['id' => 'frontend.donate.index', 'name' => 'Donation Causes', 'type' => 'page'],
            (object) ['id' => 'frontend.zakat', 'name' => 'Give Zakat', 'type' => 'page'],
            (object) ['id' => 'frontend.about', 'name' => $language['About'], 'type' => 'page'],
            // (object) ['id' => 'frontend.members', 'name' => $language['Members'], 'type' => ''],
            (object) ['id' => 'frontend.events', 'name' => $language['Events'], 'type' => 'page'],
            (object) ['id' => 'frontend.workshops.index', 'name' => 'Workshop', 'type' => 'page'],
            (object) ['id' => 'frontend.project', 'name' => $language['Project'], 'type' => 'project'],
        ];
        return $arr;
    }

    public static function findOne($data = [], $name = '', $field = '') {
        try {
            return array_values(array_filter( $data, function ($e) use ($name, $field) {
                if(!empty($field)){
                    return $e->$field == $name;
                } else {
                    return $e->id == $name;
                }
            } ))[0];
        } catch (Exception $e) {
            return (object)[];
        }
    }

    public static function pageRouteName( $name = '') {
        $rountes = StaticUtil::pageRoute();
        $new_obj =  (object) [];
        foreach ($rountes as $obj ) {
            if((@$obj->id == $name)){
                $new_obj = $obj;
            }
        };
        return  @$new_obj->name;
    }


    public static function pageRemoveImage( $content = '' ) {
        $content = preg_replace("/<img[^>]+\>/i", " ", $content);
        $new_str = str_replace("&nbsp;", '', $content);
        return  strip_tags(trim($new_str));
    }

    public static function pageRemoveNewLine( $content = '' ) {
        $content = preg_replace('/[ \t]+/', ' ', preg_replace('/[\r\n]+/', "", $content));
        $content = rtrim($content);
        $content = ltrim($content);
        return  trim($content);
    }

    public static function pageOneImage( $content = '') {
        try {
            $htmlDom =  new \DOMDocument();
            @$htmlDom->loadHTML($content);
            $imageTags = $htmlDom->getElementsByTagName('img');
            if(!empty($imageTags->length)){
                $imageTagsOne =  $imageTags[0];
               return $imageTagsOne->getAttribute('src');
            } else {
                return '/image/no-image.png';
            }
        } catch (Exception $e) {
            return '/image/no-image.png';
        }
    }

    public static function pageAllImage( $content = '', $slice = 0) {
        $imagData = (array) [];
        try {
            $htmlDom =  new \DOMDocument();
            @$htmlDom->loadHTML($content);
            $imageTags = $htmlDom->getElementsByTagName('img');
            if(!empty($imageTags->length)){
                foreach ($imageTags as $tag) {
                    $src =  (object)  ['img' => $tag->getAttribute('src')];
                    $imagData[] = $src;
                }
                if(!empty($slice)) {
                   return array_slice($imagData, 0, $slice);
                } else {
                    return $imagData;
                }
            } else {
                return $imagData;
            }
        } catch (Exception $e) {
            return $imagData;
        }
    }

    // ex  This string is too long and...
    public static function substrwords($text, $maxchar, $end='...') {
        if (strlen($text) > $maxchar || $text == '') {
            $words = preg_split('/\s/', $text);
            $output = '';
            $i      = 0;
            while (1) {
                $length = strlen($output)+strlen($words[$i]);
                if ($length > $maxchar) {
                    break;
                }
                else {
                    $output .= " " . $words[$i];
                    ++$i;
                }
            }
            $output .= $end;
        }
        else {
            $output = $text;
        }
        return $output;
    }

    public static function formatBytes($size, $precision = 2) {
        $base = log($size, 1024);
        $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');

        return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
    }

    public static function rerourceTabs($locale = null) {
        $language = Translation::language($locale)['admin']['Resource'];
        $arr = (array) [
            (object) ['id' => '', 'name' => $language['Option']['ViewAll']],
            (object) ['id' => 'myanmar_curriculum', 'name' => $language['Option']['MyanmarCurriculum']],
            (object) ['id' => 'alp', 'name' => $language['Option']['ALP']],
            (object) ['id' => 'ece', 'name' => $language['Option']['ECE']],
            (object) ['id' => 'tech_transform', 'name' => $language['Option']['TechTransform']],
            (object) ['id' => 'social_emotional', 'name' => $language['Option']['SocialEmotional']],
            (object) ['id' => 'inclusive_edu', 'name' => $language['Option']['InclusiveEdu']],
            (object) ['id' => 'reading_corner', 'name' => $language['Option']['ReadingCorner']],
            (object) ['id' => 'training', 'name' => $language['Option']['Training']],
            (object) ['id' => 'others', 'name' => $language['Option']['Others']],
        ];
        return $arr;
    }
        
    public static function activityTabs($locale = null) {
        $language = Translation::language($locale)['admin']['Activity'];
        $arr = (array) [
            (object) ['id' => 'sel', 'name' => $language['Option']['SELActivities']],
            (object) ['id' => 'pre-primary', 'name' => $language['Option']['PrePrimaryActivities']],
            (object) ['id' => 'inclusive-sports-recreation', 'name' => $language['Option']['InclusiveSportsAndRecreation']],
        ];
        return $arr;
    }

    public static function interactiveAudioTabs($locale = null) {
        $language = Translation::language($locale)['admin']['InteractiveRadio'];
        $arr = (array) [
            (object) ['id' => '', 'name' => $language['Option']['ViewAll']],
            // (object) ['id' => 'igf', 'name' => $language['Option']['ResourcesByIGF']],
            (object) ['id' => 'others', 'name' => $language['Option']['Others']],
        ];
        return $arr;
    }

    public static function ssr($meta_tag = null) {
        view()->composer('*', function($view) use ($meta_tag) {
            if(!empty($meta_tag)) {
                $view->with('meta', (object) $meta_tag);
            }
        });
    }
}
