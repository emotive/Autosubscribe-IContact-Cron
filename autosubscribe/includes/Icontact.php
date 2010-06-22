<?php

// Copyright (c) 2010 Andy Fletcher (http://www.foundry26.com)
//
// Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

class IcontactException extends Exception {
	
	private $errorData;
	
	public function __construct( $error_code, $error_data = null ) {
		parent::__construct( 'iContact Exception ... call GetErrorData for specifics', $error_code );
		$this->errorData = $error_data;
	}
	
	public function GetErrorData() {
		return $this->errorData;
	}
	
}

class Icontact {
	
	const HTTP_STATUS_CODE_SUCCESS = 200;

	const SUCCESS_CODE = 1;
	const ERROR_CODE = 0;
	const ERROR_CODE_INVALID_RESPONSE = -1;
	
	private $apiUrl;
	private $username;
	private $password;
	private $appId;
	private $accountId;
	private $clientFolderId;
	
	private $warningCount = 0;
	private $warnings = null;
	
	public function __construct( $api_url, $username, $password, $app_id, $account_id = false, $client_folder_id = false ) {
		$this->apiUrl = $api_url;
		$this->username = $username;
		$this->password = $password;
		$this->appId = $app_id;
		$this->accountId = $account_id;
		$this->clientFolderId = $client_folder_id;
	}
	
	public function LookUpAccountId() {
		$response = $this->callResource( '/a/', 'GET' );
		if ( $response[ 'code' ] == self::HTTP_STATUS_CODE_SUCCESS ) {
			if ( is_array( $response[ 'data' ] ) && isSet( $response[ 'data' ][ 'accounts' ][ 0 ][ 'accountId' ] ) ) {
				$this->accountId = $response[ 'data' ][ 'accounts' ][ 0 ][ 'accountId' ];
				return $this->accountId;
			}
			
			throw new IcontactException( self::ERROR_CODE_INVALID_RESPONSE, $response );
		}
		throw new IcontactException( self::ERROR_CODE, $response );
	}
	
	public function LookUpClientFolderId() {
		$response = $this->callResource( '/a/' . $this->accountId . '/c/', 'GET' );
		if ( $response[ 'code' ] == self::HTTP_STATUS_CODE_SUCCESS ) {
			if ( is_array( $response[ 'data' ] ) && isSet( $response[ 'data' ][ 'clientfolders' ][ 0 ][ 'clientFolderId' ] ) ) {
				$this->clientFolderId = $response[ 'data' ][ 'clientfolders' ][ 0 ][ 'clientFolderId' ];
				return $this->clientFolderId;
			}
			throw new IcontactException( self::ERROR_CODE_INVALID_RESPONSE, $response );
		}
		throw new IcontactException( self::ERROR_CODE, $response );
	}
	
	public function GetContacts() {
		$response = $this->callClientFolderResource( '/contacts', 'GET' );
		if ( $response[ 'code' ] == self::HTTP_STATUS_CODE_SUCCESS ) {
			if ( is_array( $response[ 'data' ] )&& array_key_exists( 'contacts', $response[ 'data' ] ) ) {
				$this->processWarnings( $response );
				return $response[ 'data' ][ 'contacts' ];
			}
			throw new IcontactException( self::ERROR_CODE_INVALID_RESPONSE, $response );
		}
		throw new IcontactException( self::ERROR_CODE, $response );
	}
	
	public function GetOrphanContacts() {
		$response = $this->callClientFolderResource( '/contacts?status=unlisted', 'GET' );
		if ( $response[ 'code' ] == self::HTTP_STATUS_CODE_SUCCESS ) {
			if ( is_array( $response[ 'data' ] )&& array_key_exists( 'contacts', $response[ 'data' ] ) ) {
				$this->processWarnings( $response );
				return $response[ 'data' ][ 'contacts' ];
			}
			throw new IcontactException( self::ERROR_CODE_INVALID_RESPONSE, $response );
		}
		throw new IcontactException( self::ERROR_CODE, $response );
	}
	
	public function GetTodaysOrphanContacts() {
		$response = $this->callClientFolderResource( '/contacts?status=unlisted&createDate='.Date('Y-m-d',time()-86400).'&createDateSearchType=gt', 'GET' );
		if ( $response[ 'code' ] == self::HTTP_STATUS_CODE_SUCCESS ) {
			if ( is_array( $response[ 'data' ] )&& array_key_exists( 'contacts', $response[ 'data' ] ) ) {
				$this->processWarnings( $response );
				return $response[ 'data' ][ 'contacts' ];
			}
			throw new IcontactException( self::ERROR_CODE_INVALID_RESPONSE, $response );
		}
		throw new IcontactException( self::ERROR_CODE, $response );
	}
	
	public function AddContact( $contact ) {
		$response = $this->callClientFolderResource( '/contacts', 'POST', array( $contact ) );
		if ( $response[ 'code' ] == self::HTTP_STATUS_CODE_SUCCESS ) {
			if ( is_array( $response[ 'data' ] ) && isSet( $response[ 'data' ][ 'contacts' ][ 0 ][ 'contactId' ] ) ) {
				$this->processWarnings( $response );
				return $response[ 'data' ][ 'contacts' ][ 0 ][ 'contactId' ];
			}
			throw new IcontactException( self::ERROR_CODE_INVALID_RESPONSE, $response );
		}
		throw new IcontactException( self::ERROR_CODE, $response );
	}
	
	public function UpdateContact( $contact_id, $contact ) {
		$response = $this->callClientFolderResource( '/contacts/' . $contact_id, 'POST', $contact );
		if ( $response[ 'code' ] == self::HTTP_STATUS_CODE_SUCCESS ) {
			$this->processWarnings( $response );
			return self::SUCCESS_CODE;
		}
		throw new IcontactException( self::ERROR_CODE, $response );
	}
	
	public function AddList( $list ) {
		$response = $this->callClientFolderResource( '/lists', 'POST', array( $list ) );
		if ( $response[ 'code' ] == self::HTTP_STATUS_CODE_SUCCESS ) {
			if ( is_array( $response[ 'data' ] ) && isSet( $response[ 'data' ][ 'lists' ][ 0 ][ 'listId' ] ) ) {
				$this->processWarnings( $response );
				return $response[ 'data' ][ 'lists' ][ 0 ][ 'listId' ];
			} 
			throw new IcontactException( self::ERROR_CODE_INVALID_RESPONSE, $response );
		}
		throw new IcontactException( self::ERROR_CODE, $response );
	}
	
	public function SubscribeContactToList( $contact_id, $list_id ) {
		$response = $this->callClientFolderResource(
			'/subscriptions',
			'POST',
			array( array(
				'contactId' => $contact_id,
				'listId' => $list_id,
				'status' => 'normal'
			))
		);
		if ( $response[ 'code' ] == self::HTTP_STATUS_CODE_SUCCESS ) {
			$this->processWarnings( $response );
			return self::SUCCESS_CODE;
		}
		throw new IcontactException( self::ERROR_CODE, $response );
	}
	
	public function GetWarnings() {
		return $this->warnings;
	}
	
	private function processWarnings( $response ) {
		$this->warningCount = 0;
		$this->warnings = null;
		if ( isSet( $response[ 'data' ][ 'warnings' ] ) ) {
			$this->warningCount = count( $response[ 'data' ][ 'warnings' ] );
			$this->warnings = $response[ 'data' ][ 'warnings' ];
		}
	}
	
	private function callClientFolderResource( $resource, $method, $data = null ) {
		return $this->callResource(
			'/a/' . $this->accountId . '/c/' . $this->clientFolderId . $resource,
			$method,
			$data
		);
	}
	
	private function callResource( $url, $method, $data = null ) {
		$url    = $this->apiUrl . $url;
		$handle = curl_init();
		
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'Api-Version: 2.0',
			'Api-AppId: ' . $this->appId,
			'Api-Username: ' . $this->username,
			'Api-Password: ' . $this->password
		);
		
		curl_setopt( $handle, CURLOPT_URL, $url );
		curl_setopt( $handle, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $handle, CURLOPT_FAILONERROR, false );
		
		switch ( $method ) {
			case 'POST':
				curl_setopt( $handle, CURLOPT_POST, true );
				curl_setopt( $handle, CURLOPT_POSTFIELDS, json_encode( $data ) );
			break;
			case 'PUT':
				curl_setopt( $handle, CURLOPT_PUT, true );
				$file_handle = fopen( $data, 'r' );
				curl_setopt( $handle, CURLOPT_INFILE, $file_handle );
			break;
			case 'DELETE':
				curl_setopt( $handle, CURLOPT_CUSTOMREQUEST, 'DELETE' );
			break;
		}
		
		$response = curl_exec( $handle );
		$response = json_decode( $response, true );
		$code = curl_getinfo( $handle, CURLINFO_HTTP_CODE );

		curl_close( $handle );

		return array(
			'code' => $code,
			'data' => $response,
		);
	}
	
}

?>