<?php

require_once '../vendor/autoload.php';
include_once "../vendor/google/apiclient/examples/templates/base.php";

putenv("GOOGLE_APPLICATION_CREDENTIALS=cutie-964b466f9847.json");

class GoogleParser{
	private $googleClient;
	private $fileId;
	private $accessToken;
	
	public function __construct($fileId) {
		$this->googleClient = new Google_Client();
		$this->initGoogleClient();
		$this->getToken();
		$this->fileId = $fileId;
	}
	
	private function initGoogleClient(){
		if ($credentials_file = checkServiceAccountCredentialsFile()) {
			// set the location manually
			$this->googleClient->setAuthConfig($credentials_file);
		} elseif (getenv('GOOGLE_APPLICATION_CREDENTIALS')) {
			// use the application default credentials
			$this->googleClient->useApplicationDefaultCredentials();
		} else {
			throw new Exception(missingServiceAccountDetailsWarning());
		}
		$this->googleClient->setApplicationName("cutie");
		$this->googleClient->setScopes(['https://www.googleapis.com/auth/drive','https://spreadsheets.google.com/feeds']);
	}
	
	private function getToken(){
		$tokenArray = $this->googleClient->fetchAccessTokenWithAssertion();
		$this->accessToken = $tokenArray["access_token"];
	}
	
	private function getBody() {
		$url = "https://spreadsheets.google.com/feeds/list/".$this->fileId."/od6/private/full";
		$method = 'GET';
		$headers = ["Authorization" => "Bearer $this->accessToken", "GData-Version" => "3.0"];
		$httpClient = new GuzzleHttp\Client(['headers' => $headers]);
		$resp = $httpClient->request($method, $url);
		$body = $resp->getBody()->getContents();
		return $body;
	}
	
	public function getData(){
		$body = $this->getBody();
		$tableXML = simplexml_load_string($body);
		$output = array();
		foreach ($tableXML->entry as $entry) {
			foreach ($entry->children('gsx', TRUE) as $column) {
				$colName = (string)$column->getName();
				$colValue = (string)$column;
				$row[$colName] = $colValue;
			}
			$output[] = $row;
		}
		return $output;
	}
}