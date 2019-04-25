<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * User: hewro
 * Blog: www.ihewro.com
 * Date: 2019/4/24
 * Time: 20:43
 *
 * 将微博图床的图片迁移到自己的博客服务器上
 */
// ❗【使用说明】：https://github.com/ihewro/PullSinaimgtoTypecho

//【调用说明】你的博客地址/?action=pullsina&key=下面的$key变量的值
// 举个例子 http://www.ihewro.com/?action=pullsina&key=ihewro

//【变量说明】：这个变量是为了防止别人恶意调用接口设置的，调用该接口的时候key参数的值要与这个变量对应
$GLOBALS['key'] = "ihewro";

// 【变量说明】：
//  true 表示执行该接口不会修改数据库内容，只会显示数据库中含有新浪图床的数目信息，
//  false 表示会自动下载新浪图片图片到本地服务器并修改数据库内容
$GLOBALS['is_replace'] = false;

// 【变量说明】每次替换的数目，为了防止替换数目太多一直处于等待状态，你可以将这个变量设置较小的值，多次调用该接口
$GLOBALS['limit'] = 9999;

//这个变量请勿修改值
$GLOBALS['haveNum'] = 0;//已经替换的图片数目

$options = Helper::options();
$GLOBALS['blog_url'] = $options->rootUrl;

$GLOBALS['patten'] = '/(https|http):\/\/[^\s|\"|\)]+sinaimg\.cn[^\s|\"|\)]+/';


function getDataFromWebUrl($url){
    $file_contents = "";
    if (function_exists('file_get_contents')) {
        $file_contents = @file_get_contents($url);
    }
    if ($file_contents == "") {
        $ch = curl_init();
        $timeout = 30;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $file_contents = curl_exec($ch);
        curl_close($ch);
    }
    return $file_contents;
}


if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $action = @$_GET['action'];
    if($action == "pullsina"){
        $key = @$_GET['key'];
        if ($GLOBALS['key'] == $key){
            //显示提示信息
            if ($GLOBALS['is_replace']){
                print_l("开始执行新浪图床拉取到本地服务器……@ihewro",3,"normal");
            }else{
                print_l("下面为你的博客包含新浪图片的列表，本次操作不会替换和修改数据库，请修改「is_replace」变量为true进行执行……@ihewro",3,"underline","注意");
            }

            $db = Typecho_Db::get();//获取数据库对象

            //替换文章和独立页面
            $sql_content = $db->select('text','cid')->from('table.contents')//查询文章
            ->where('text like ?', "%sinaimg.cn%");
            $content = $db->fetchAll($sql_content);
            print_l(count($content)."篇文章含有新浪图床的图片",2,"normal","文章&&独立页面");
            $index = 1;
            foreach ($content as $item){
                if ($GLOBALS['haveNum'] > $GLOBALS['limit']){
                    break;
                }
                print_l("替换第".$index."篇文章的图片(cid=".$item['cid'].")",1,"underline","开始");
                $text = $item['text'];//不能转换成HTML，否则会导致数据库markdown语法失效

                //维护这三种方式的正则表达式太麻烦了，难以确保适合任何情况，直接暴力匹配新浪图片的URL即可，因为一般都是用新浪图床做图片的吧，没其他用途……
                /*//替换text中HTML图片结构
                $text = preg_replace_callback('/<img.*?src="(.*?sinaimg\.cn.*?)"(.*?)(alt="(.*?)")??(.*?)\/?>/',"replaceImage",
                    $text);
                //替换text中的markdown1图片结构  ![xxx](xxx.jpg)
                $text = preg_replace_callback('/\!\[.*\]\((.*?sinaimg\.cn.*?)\)/',"replaceImage",
                    $text);
                //替换text中的markdown2图片结构 ![xxx][1]  [1]:
                $text = preg_replace_callback('/\[\d\]:\s(.*?sinaimg\.cn.*)\n?/',"replaceImage",
                    $text);*/

                $text = preg_replace_callback($GLOBALS['patten'],"replaceImage",$text);

                //写数据库
                if ($GLOBALS['is_replace']){
                    $db->query($db->update('table.contents')->rows(array('text' => $text))->where('cid = ?', $item['cid']));
                }
                print_l("替换第".$index."篇文章的所有图片",3,"underline","成功");
                $index ++;
            }


            //替换评论
            $sql_comment = $db->select('text','coid')->from('table.comments')//查询评论
            ->where('text like ?', "%sinaimg.cn%");
            $comment = $db->fetchAll($sql_comment);
            print_l(count($comment)."条评论含有新浪图床的图片",2,"normal","评论");
            $index = 1;
            foreach ($comment as $item){
                if ($GLOBALS['haveNum'] > $GLOBALS['limit']){
                    break;
                }
                print_l("替换第".$index."条评论的图片",1,"underline","开始");
                $text = $item['text'];//不能转换成HTML，否则会导致数据库markdown语法失效
                $text = preg_replace_callback($GLOBALS['patten'],"replaceImage",$text);
                //写数据库
                if ($GLOBALS['is_replace']){
                    $db->query($db->update('table.comments')->rows(array('text' => $text))->where('coid = ?', $item['coid']));
                }
                print_l("替换第".$index."条评论的所有图片",3,"underline","成功");
                $index ++;
            }

            //替换字段
            $sql_fields = $db->select('str_value','cid','name')->from('table.fields')//查询评论
            ->where('str_value like ?', "%sinaimg.cn%");
            $fields = $db->fetchAll($sql_fields);
            print_l(count($fields)."个字段含有新浪图床的图片",2,"normal","评论");
            $index = 1;
            foreach ($fields as $item){
                if ($GLOBALS['haveNum'] > $GLOBALS['limit']){
                    break;
                }
                print_l("替换第".$index."个字段的图片",1,"underline","开始");
                $text = $item['str_value'];//不能转换成HTML，否则会导致数据库markdown语法失效
                $text = preg_replace_callback($GLOBALS['patten'],"replaceImage",$text);
                //写数据库
                if ($GLOBALS['is_replace']){
                    $db->query($db->update('table.fields')->rows(array('str_value' => $text))->where('cid = ? and name = ?',
                        $item['cid'],$item['name']));
                }
                print_l("替换第".$index."个字段的所有图片",3,"underline","成功");
                $index ++;
            }

            //替换设置里面
            $sql_options = $db->select('value','user','name')->from('table.options')//查询评论
            ->where('value like ?', "%sinaimg.cn%");
            $options = $db->fetchAll($sql_options);
            print_l(count($options)."个设置项含有新浪图床的图片",2,"normal","评论");
            $index = 1;
            foreach ($options as $item){
                if ($GLOBALS['haveNum'] > $GLOBALS['limit']){
                    break;
                }
                print_l("替换第".$index."个设置的图片",1,"underline","开始");
                $text = $item['value'];//需要先进行反序列化替换后再序列化
                //一定不能对序列化的字符串直接操作，否则导致对象错误❌
                $array = @unserialize($text);
                if (count($array) == 0 || count($array) == 1){
                    print_l("当前[".$item['name']."设置数据结构有问题，无法替换",1);
                }else{
                    foreach ($array as $key => $value){
                        if (is_array($value)){
                            foreach ($value as $key2 => $vvalue){
                                $array[$key][$key2] = preg_replace_callback($GLOBALS['patten'],"replaceImage",$vvalue);
                            }
                        }else{
                            $array[$key] = preg_replace_callback($GLOBALS['patten'],"replaceImage",$value);
                        }
                    }
                    $text = serialize($array);//序列化
                    //写数据库
                    if ($GLOBALS['is_replace']){
                        $db->query($db->update('table.options')->rows(array('value' => $text))->where('user = ? and name = ?',
                            $item['user'],$item['name']));
                    }
                }

                print_l("替换第".$index."个设置的所有图片",3,"underline","成功");
                $index ++;
            }
        }else{
            echo "你的key变量配置错误，无法鉴权，请联系博客主人。";
        }
        die();
    }
}


/**
 * @param string $str
 * @param int $num 换行的格式
 * @param string $type 打印的格式，underline 表示强调输出，normal 表示普通打印
 * @param string $prefix 前缀
 * @param string $suffix 后缀
 */
function print_l($str,$num = 1,$type = "normal",$prefix = "",$suffix = ""){
    //按照要求输出字符串
    if (trim($prefix) != ""){
        $prefix = "【".$prefix."】";
    }
    if (trim($suffix) != ""){
        $suffix = "【".$suffix."】";
    }
    if ($type == "underline"){
        print_r("----------".$prefix."----------");
    }
    print_r($str);
    if ($type == "underline"){
        print_r("----------".$suffix."----------");
    }

    for ($i = 0; $i< $num; $i++){
        print_r("</br>"."\n");
    }
    //TODO:可以在打印的同时写到log文件里
}


function replaceImage($matches){
    $url = $matches[0];
    if ($GLOBALS['haveNum'] <= $GLOBALS['limit']){
        $GLOBALS['haveNum'] ++;
        if ($GLOBALS['is_replace']){//上传并替换
            $url = uploadPic($url);
            print_l($matches[0]."已替换成".$url,1);
            return $url;
        }else{//不替换
            print_l($url,1);
            return $url;
        }
    }else{
        return $url;//不替换
    }
}


/**
 * @param $pic
 * @return string
 */
function uploadPic($pic){

    $suffix = ".jpg";//新浪图床的图片都是jpg后缀
    $blogUrl = $GLOBALS['blog_url'];
    $name = uniqid();
    $DIRECTORY_SEPARATOR = "/";
    $childDir = $DIRECTORY_SEPARATOR.'usr'.$DIRECTORY_SEPARATOR.'uploads' . $DIRECTORY_SEPARATOR .'sina'
        .$DIRECTORY_SEPARATOR;
    $dir = __TYPECHO_ROOT_DIR__ . $childDir;
    if (!file_exists($dir)){
        mkdir($dir, 0777, true);
    }
    $fileName = $name. $suffix;
    $file = $dir .$fileName;

    //开始捕捉
    $img = getDataFromWebUrl($pic);

    $fp2 = fopen($file , "a");
    fwrite($fp2, $img);
    fclose($fp2);

    return $blogUrl.$childDir.$fileName;
}





