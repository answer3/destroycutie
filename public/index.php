<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;

require '../vendor/autoload.php';
require '../vendor/config.php';
require '../vendor/NotORM.php';
require 'GoogleParser.php';

const SECRET = 'eWIiubyP9P9rlLJkI6xD';
const CDN_URL = '';
const ITEMS_PER_PAGE=30;

$app = new \Slim\App(["settings" => $config]);
$container = $app->getContainer();
$container['db'] = function ($c) {
    $db = $c['settings']['db'];
    $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	$db = new NotORM($pdo);
    return $db;
};
$container['view'] = new \Slim\Views\PhpRenderer("../templates/");

function savePic($picUrl,$randomPrefix){
	$path = dirname(__FILE__);
	$fileName = getFilename($picUrl);
	file_put_contents($path.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.$randomPrefix.'_'.$fileName, file_get_contents($picUrl));
}

function getFilename($picUrl){
	return array_pop(explode('/', $picUrl));
}

function clearImageFolder(){
	$filesToDelete = glob(dirname(__FILE__).'/images/*');
	foreach($filesToDelete as $file){ 
	if(is_file($file)){
			unlink($file); 
		}
	}
}

$app->get('/[{page}]', function (Request $request, Response $response,$args) {
	$page = isset($args['page'])?$args['page']:0;
	$content = $this->db->content()->order('id DESC')->limit(ITEMS_PER_PAGE,$page*ITEMS_PER_PAGE);
	$totalItems = $this->db->content()->count('*');
	$count = ceil($totalItems/ITEMS_PER_PAGE);
	$isShowPagination = ($totalItems>count($content))?true:false;
	$response = $this->view->render($response, "index.phtml",array('content'=>$content,
																	'cdn_url'=>CDN_URL,
																	'cur_page'=>$page,
																	'pages_count'=>$count,
																	'isShowPagination'=>$isShowPagination	
																));

    return $response;
});

$app->get('/sync_data/{secret}', function (Request $request, Response $response) {
	$secret = $request->getAttribute('secret');
	if($secret !== SECRET){
		echo "Incorrect URL";
		return;
	}
	try{
		$parser = new GoogleParser('1PhtiKh0YOI1zTXoxr6aOYUgGmJilTgzDB-yANqcbWOs');
	}  catch (Exception $e){
		echo 'Error Occured';
		return;
	}
	$data = $parser->getData();
	if(!$data){
		echo 'Data is Emty';
		return;
	}
	
	$this->db->content->delete();
	clearImageFolder();
	foreach($data as $item){
		if(!$item['imageurl'] or !$item['linkfromtheimage']){
			continue;
		}
		$randomPrefix = mt_rand(1,900000);
		savePic($item['imageurl'],$randomPrefix);
		$insertData['image_url'] = '/images/'.$randomPrefix.'_'.getFilename($item['imageurl']);
		$insertData['link'] = $item['linkfromtheimage'];
		$this->db->content->insert($insertData);
	}
	echo 'Synced Succesfully';
	
});

$app->run();