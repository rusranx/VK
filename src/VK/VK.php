<?php

/**
 * The PHP class for vk.com API and to support OAuth.
 *
 * @author  Vlad Pronsky <vladkens@yandex.ru>
 * @license https://raw.github.com/vladkens/VK/master/LICENSE MIT
 */

namespace VK;

use RusranUtils\Curl;

class VK
{
	/**
	 * VK application id.
	 *
	 * @var string
	 */
	private $app_id;

	/**
	 * VK application secret key.
	 *
	 * @var string
	 */
	private $api_secret;

	/**
	 * API version. If null uses latest version.
	 *
	 * @var int
	 */
	private $api_version;

	/**
	 * VK access token.
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Authorization status.
	 *
	 * @var bool
	 */
	private $auth = false;

	/**
	 * Instance curl.
	 *
	 * @var Resource
	 */
	private $ch;

	const AUTHORIZE_URL = 'https://oauth.vk.com/authorize';
	const ACCESS_TOKEN_URL = 'https://oauth.vk.com/access_token';
	const TOKEN_URL = 'https://oauth.vk.com/token';
	const API_URL = 'https://api.vk.com/method';

	/**
	 * Constructor.
	 *
	 * @param   string $app_id
	 * @param   string $api_secret
	 * @param   string $access_token
	 * @throws  VKException
	 */
	public function __construct($app_id, $api_secret, $access_token = null)
	{
		$this->app_id = $app_id;
		$this->api_secret = $api_secret;
		$this->setAccessToken($access_token);

		$this->ch = new Curl();
	}

	/**
	 * Set special API version.
	 *
	 * @param   int $version
	 * @return  void
	 */
	public function setApiVersion($version)
	{
		$this->api_version = $version;
	}

	/**
	 * Set Access Token.
	 *
	 * @param   string $access_token
	 * @throws  VKException
	 * @return  void
	 */
	public function setAccessToken($access_token)
	{
		$this->access_token = $access_token;
	}

	/**
	 * Returns base API url.
	 *
	 * @param   string $method
	 * @param   string $response_format
	 * @return  string
	 */
	public function getApiUrl($method, $response_format = 'json')
	{
		return sprintf("%s/%s.%s", self::API_URL, $method, $response_format);
	}

	/**
	 * Returns authorization link with passed parameters.
	 *
	 * @param   int|string $scope
	 * @param   string     $callback_url
	 * @param   string     $type
	 * @param   bool       $test_mode
	 * @return  string
	 */
	public function getAuthorizeUrl($scope = '', $callback_url = 'https://api.vk.com/blank.html',
	                                $type = 'token', $test_mode = false)
	{
		$parameters = [
			'client_id'     => $this->app_id,
			'scope'         => $scope,
			'redirect_uri'  => $callback_url,
			'response_type' => $type
		];

		if ($test_mode)
			$parameters['test_mode'] = 1;

		return $this->createUrl(self::AUTHORIZE_URL, $parameters);
	}

	/**
	 * @param string     $login
	 * @param string     $password
	 * @param int|string $scope
	 * @return string
	 * @throws VKException
	 */
	public function getToken($login, $password, $scope = '')
	{
		throw new VKException('Standalone apps ONLY');
		$parameters = [
			'grant_type'    => 'password',
			'client_id'     => $this->app_id,
			'client_secret' => $this->api_secret,
			'username'      => $login,
			'password'      => $password,
			'scope'         => $scope
		];
		$url = $this->createUrl(self::TOKEN_URL, $parameters);
		$response = json_decode($this->request($url), true);

		return $response['token'];
	}

	/**
	 * Returns access token by code received on authorization link.
	 *
	 * @param   string $code
	 * @param   string $callback_url
	 * @throws  VKException
	 * @return  array
	 */
	public function getAccessToken($code, $callback_url = 'https://api.vk.com/blank.html')
	{
		if (!is_null($this->access_token) && $this->auth) {
			throw new VKException('Already authorized.');
		}

		$parameters = [
			'client_id'     => $this->app_id,
			'client_secret' => $this->api_secret,
			'code'          => $code,
			'redirect_uri'  => $callback_url
		];

		$rs = json_decode($this->request(
			$this->createUrl(self::ACCESS_TOKEN_URL, $parameters)), true);

		if (isset($rs['error'])) {
			throw new VKException($rs['error'] .
				(!isset($rs['error_description']) ?: ': ' . $rs['error_description']));
		} else {
			$this->auth = true;
			$this->access_token = $rs['access_token'];

			return $rs;
		}
	}

	/**
	 * Return user authorization status.
	 *
	 * @return  bool
	 */
	public function isAuth()
	{
		return !is_null($this->access_token);
	}

	/**
	 * Check for validity access token.
	 *
	 * @param   string $access_token
	 * @return  bool
	 */
	public function checkAccessToken($access_token = null)
	{
		$token = is_null($access_token) ? $this->access_token : $access_token;
		if (is_null($token)) return false;

		$rs = $this->api('getUserSettings', ['access_token' => $token]);

		return isset($rs['response']);
	}

	/**
	 * Execute API method with parameters and return result.
	 *
	 * @param   string $method
	 * @param   array  $parameters
	 * @param   string $format
	 * @param   string $requestMethod
	 * @return  mixed
	 */
	public function api($method, $parameters = [], $format = 'array', $requestMethod = 'get')
	{
		$parameters['timestamp'] = time();
		$parameters['api_id'] = $this->app_id;
		$parameters['random'] = rand(0, 10000);

		if (!array_key_exists('access_token', $parameters) && !is_null($this->access_token)) {
			$parameters['access_token'] = $this->access_token;
		}

		if (!array_key_exists('v', $parameters) && !is_null($this->api_version)) {
			$parameters['v'] = $this->api_version;
		}

		ksort($parameters);

		$sig = '';
		foreach ($parameters as $key => $value) {
			$sig .= $key . '=' . $value;
		}
		$sig .= $this->api_secret;

		$parameters['sig'] = md5($sig);

		if ($method == 'execute' || $requestMethod == 'post') {
			$rs = $this->request(
				$this->getApiUrl($method, $format == 'array' ? 'json' : $format),
				"POST",
				$parameters
			);
		} else {
			$rs = $this->request(
				$this->createUrl(
					$this->getApiUrl($method, $format == 'array' ? 'json' : $format),
					$parameters
				)
			);
		}

		return $format == 'array' ? json_decode($rs, true) : $rs;
	}

	/**
	 * Concatenate keys and values to url format and return url.
	 *
	 * @param   string $url
	 * @param   array  $parameters
	 * @return  string
	 */
	private function createUrl($url, $parameters)
	{
		$url .= '?' . http_build_query($parameters);

		return $url;
	}

	/**
	 * Executes request on link.
	 *
	 * @param   string $url
	 * @param   string $method
	 * @param   array  $postfields
	 * @return  null|string
	 */
	private function request($url, $method = 'GET', $postfields = [])
	{
		switch (strtolower($method)) {
			case "get":
				return $this->ch->get($url);
				break;
			case "post":
				return $this->ch->post($url, $postfields);
				break;
		}

		return null;
	}

	/**
	 * Returns SSL photo url.
	 *
	 * @param string $photoUrl
	 * @return null|string
	 */
	public static function sslPhoto($photoUrl = null)
	{
		if (is_null($photoUrl))
			return null;

		$url = parse_url($photoUrl);

		if ($url["scheme"] == "https")
			return $photoUrl;

		if ($url["host"] == "vk.com")
			return sprintf("https://%s%s", $url["host"], $url["path"]);

		preg_match("/cs(\d+)\.(.+)/", $url["host"], $domain);

		return sprintf("https://pp.%s/c%s%s", $domain[2], $domain[1], $url["path"]);
		/*
		 * BEFORE:  http://cs625631.vk.me/v625631245/43f56/MCuFMclvN0U.jpg
		 * AFTER:   https://pp.vk.me/c625631/v625631245/43f56/MCuFMclvN0U.jpg
		 */
	}

}

