<?php
namespace ZenOAuth2;

class Client {
	/**
	 * @ignore
	 */
	public $access_token;
	/**
	 * @ignore
	 */
	public $refresh_token;
	/**
	 * Contains the last HTTP status code returned. 
	 *
	 * @ignore
	 */
	public $http_code;
	/**
	 * Contains the last API call.
	 *
	 * @ignore
	 */
	public $url;
	/**
	 * Set up the API root URL.
	 *
	 * @ignore
	 */
	public $host;
	
	protected $_curlOptions = array(
		CURLOPT_HTTP_VERSION	=> CURL_HTTP_VERSION_1_0,
		CURLOPT_USERAGENT		=> 'ZenOAuth2 v0.2',
		CURLOPT_CONNECTTIMEOUT	=> 30,
		CURLOPT_TIMEOUT			=> 30,
		CURLOPT_SSL_VERIFYPEER	=> FALSE,
	);
	
	/**
	 * Respons format.
	 *
	 * @ignore
	 */
	public $format = 'json';
	/**
	 * Decode returned json data.
	 *
	 * @ignore
	 */
	public $decode_json = TRUE;
	/**
	 * Contains the last HTTP headers returned.
	 *
	 * @ignore
	 */
	public $http_info;
	
	/**
	 * print the debug info
	 *
	 * @ignore
	 */
	public $debug = FALSE;

	/**
	 * boundary of multipart
	 * @ignore
	 */
	public static $boundary = '';

	/**
	 * construct self object
	 */
	public function __construct($access_token = NULL, $refresh_token = NULL) {
		$this->access_token = $access_token;
		$this->refresh_token = $refresh_token;
	}
	
	public function setCurlOptions(array $options){
		$this->_curlOptions = array_merge($this->_curlOptions, $options);
	}
	
	protected function _paramsFilter(&$params){
	}

	public function parseResponse($response){
		if ($this->format === 'json' && $this->decode_json) {
			$json = json_decode($response, true);		//	modified by shen2
			if ($json !== null)
				return $json;
		}
		return $response;
	}
	
	/**
	 * GET shorthand
	 *
	 * @return mixed
	 */
	public function get($url, $parameters = array()) {
		$this->_paramsFilter($parameters);
		
		$response = $this->http($this->realUrl($url) . '?' . http_build_query($parameters), 'GET');
		
		return $this->parseResponse($response);
	}

	/**
	 * POST shorthand
	 *
	 * @return mixed
	 */
	public function post($url, $parameters = array(), $multi = false) {
		$this->_paramsFilter($parameters);
		
		$headers = array();
		if (!$multi && (is_array($parameters) || is_object($parameters)) ) {
			$body = http_build_query($parameters);
		} else {
			$body = self::build_http_query_multi($parameters);
			$headers[] = "Content-Type: multipart/form-data; boundary=" . self::$boundary;
		}
		$response = $this->http($this->realUrl($url), 'POST', $body, $headers);
		
		return $this->parseResponse($response);
	}

	/**
	 * DELTE shorthand
	 *
	 * @return mixed
	 */
	public function delete($url, $parameters = array()) {
		$this->_paramsFilter($parameters);
		
		$response = $this->http($this->realUrl($url), 'DELETE', http_build_query($parameters), $headers);
		
		return $this->parseResponse($response);
	}

	/**
	 * 
	 * @return array
	 */
	protected function _additionalHeaders(){
		return array();
	}
	
	public function realUrl($url){
		if (strrpos($url, 'https://') !== 0 && strrpos($url, 'https://') !== 0) {
			$url = $this->host . $url;
		}
	
		return $url;
	}
	
	/**
	 * Make an HTTP request
	 *
	 * @return string API results
	 * @ignore
	 */
	public function http($url, $method, $postfields = NULL, $headers = array()) {
		$this->http_info = array();
		$ci = curl_init();
		/* Curl settings */
		foreach($this->_curlOptions as $optionName => $optionValue){
			curl_setopt($ci, $optionName, $optionValue);
		}
		curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ci, CURLOPT_ENCODING, "");
		curl_setopt($ci, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));
		curl_setopt($ci, CURLOPT_HEADER, FALSE);

		switch ($method) {
			case 'POST':
				curl_setopt($ci, CURLOPT_POST, TRUE);
				if (!empty($postfields)) {
					curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields);
					$this->postdata = $postfields;
				}
				break;
			case 'DELETE':
				curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'DELETE');
				if (!empty($postfields)) {
					$url = "{$url}?{$postfields}";
				}
		}

		curl_setopt($ci, CURLOPT_URL, $url );
		curl_setopt($ci, CURLOPT_HTTPHEADER, array_merge($headers, $this->_additionalHeaders()) );
		curl_setopt($ci, CURLINFO_HEADER_OUT, TRUE );

		$response = curl_exec($ci);
		
		if ($response === false){	//	modified by shen2
	    	$message = curl_error($ci);
	    	$code = curl_errno($ci);
	    	curl_close($ci);
	    	throw new CurlException($message, $code);
	    }
		
		$this->http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
		$this->http_info = array_merge($this->http_info, curl_getinfo($ci));
		$this->url = $url;

		if ($this->debug) {
			echo "=====post data======\r\n";
			var_dump($postfields);

			echo '=====info====='."\r\n";
			print_r( curl_getinfo($ci) );

			echo '=====$response====='."\r\n";
			print_r( $response );
		}
		curl_close ($ci);
		return $response;
	}

	/**
	 * Get the header info to store.
	 *
	 * @return int
	 * @ignore
	 */
	public function getHeader($ch, $header) {
		$i = strpos($header, ':');
		if (!empty($i)) {
			$key = str_replace('-', '_', strtolower(substr($header, 0, $i)));
			$value = trim(substr($header, $i + 2));
			$this->http_header[$key] = $value;
		}
		return strlen($header);
	}

	/**
	 * @ignore
	 */
	public static function build_http_query_multi($params) {
		if (!$params) return '';

		uksort($params, 'strcmp');

		$pairs = array();

		self::$boundary = $boundary = uniqid('------------------');
		$MPboundary = '--'.$boundary;
		$endMPboundary = $MPboundary. '--';
		$multipartbody = '';

		foreach ($params as $parameter => $value) {
			if(	$value instanceof ImageFile ) {
				$multipartbody .= $MPboundary . "\r\n";
				$multipartbody .= 'Content-Disposition: form-data; name="' . $parameter . '"; filename="' . $value->filename . '"'. "\r\n";
				$multipartbody .= "Content-Type: image/unknown\r\n\r\n";
				$multipartbody .= $value->content. "\r\n";
			}
			else if( in_array($parameter, array('pic', 'image')) && $value{0} == '@' ) {
				$url = ltrim( $value, '@' );
				$content = file_get_contents( $url );
				$array = explode( '?', basename( $url ) );
				$filename = $array[0];

				$multipartbody .= $MPboundary . "\r\n";
				$multipartbody .= 'Content-Disposition: form-data; name="' . $parameter . '"; filename="' . $filename . '"'. "\r\n";
				$multipartbody .= "Content-Type: image/unknown\r\n\r\n";
				$multipartbody .= $content. "\r\n";
			} else {
				$multipartbody .= $MPboundary . "\r\n";
				$multipartbody .= 'content-disposition: form-data; name="' . $parameter . "\"\r\n\r\n";
				$multipartbody .= $value."\r\n";
			}

		}

		$multipartbody .= $endMPboundary;
		return $multipartbody;
	}
}
