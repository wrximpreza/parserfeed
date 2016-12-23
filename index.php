<?php
require __DIR__.'/vendor/autoload.php';
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use PHPHtmlParser\Dom;
use \Curl\Curl;

// create a log channel
$log = new Logger('parserfeed');
$log->pushHandler(new StreamHandler(__DIR__.'/logs.log', Logger::ERROR));

$curl = new Curl();
$dom = new Dom;

$url_list = array(
	'http://clickkydsp.com/dsp/bidResponse/id/9',
	'http://clickkydsp.com/dsp/bidResponse/id/8'
);

$curl->post('http://clickkydsp.com/login', array(
	'email' => 'clickky',
	'password' => 'Temp123#!',
	'remember'=> 1
));

if ($curl->error) {
    $log->error('Error:' . $curl->errorCode . ': ' . $curl->errorMessage);
} else {

    foreach ($curl->responseCookies as $k => $v) {
        $curl->setCookie($k, $v);
    }

    foreach ($url_list as $k=>$url) {
        $curl->get($url);
        if (!$curl->error) {
            $dom->load($curl->response);
            $contents = $dom->find('.portlet-body .note pre');

            $prev_id = trim(file_get_contents(__DIR__.'/last_ids/id_'.($k+1).'.txt'));
            $i=0;

            foreach ($contents as $content) {
                $html = htmlspecialchars_decode(trim($content->innerHtml, '"'));
                $data = json_decode($html);

                if($i===0) {
                    $id = $data->data->id;
                }
                $i++;

                if (!$data) {
                    switch (json_last_error()) {
                        case JSON_ERROR_NONE:
                            $log->error('Ошибок нет');
                            break;
                        case JSON_ERROR_DEPTH:
                            $log->error('Достигнута максимальная глубина стека');
                            break;
                        case JSON_ERROR_STATE_MISMATCH:
                            $log->error('Некорректные разряды или не совпадение режимов');
                            break;
                        case JSON_ERROR_CTRL_CHAR:
                            $log->error('Некорректный управляющий символ');
                            break;
                        case JSON_ERROR_SYNTAX:
                            $log->error('Синтаксическая ошибка, не корректный JSON');
                            break;
                        case JSON_ERROR_UTF8:
                            $log->error('Некорректные символы UTF-8, возможно неверная кодировка');
                            break;
                        default:
                            $log->error('Неизвестная ошибка');
                            break;
                    }

                } else {
                    if($prev_id !== $id) {
                        try {
                            file_put_contents(__DIR__ . '/last_ids/id_'.($k+1).'.txt', $id);
                            $file_name = __DIR__ . '/data/'.($k+1).'/' . date('d-m-Y', time()). '.txt';
                            if(!@mkdir(__DIR__ . '/data/'.($k+1), 0777) || !is_dir($file_name)){
                                $log->error('Ошибка создание директории');
                            }
                            echo 'write<br />';
                            file_put_contents($file_name, $html . '\n', FILE_APPEND | LOCK_EX);
                        }catch (Exception $e){
                            $log->error($e->getMessage());
                        }
                    }
                }

            }
        } else {
            $log->error('Неизвестная ошибка');
        }
    }

}

$curl->close();
