<?php
/**
* Oauth consumer interface for the LinkedIn API
*
* This class extends the PHP Oauth class to provide methods for accessing
* LinkedIn's implementation of the specification. It requires the Oauth PECL
* extension and PHP 5.3.
*
* @link https://developer.linkedin.com/documents/authentication
* @link http://www.php.net/manual/en/class.oauth.php
*
* @author Dan Mills <dan@lunamouse.com>
* @license http://opensource.org/licenses/bsd-license.php
*/

/**
* LinkedIn API classes shall always be constructed with an API Key and an API
* Secret.  The constuctors may also take a request/access token and secret.
*/
interface Li_Api {
	public function __construct(
		$consumer_key, $consumer_secret, $token='', $token_secret='');
}

/**
* LinkedIn Oauth class
*/
class Li_Oauth extends Oauth implements Li_Api {

	/**
	* URI stub for LinkedIn Oauth server
	*/
	const LI_OAUTH_URI = 'https://api.linkedin.com/uas/oauth/';

	/**
	* URI stub for LinkedIn API (version 1)
	*/
	const LI_API_URI = 'http://api.linkedin.com/v1/';

	/**
	* Error thrown when an application tries to set an invalid scope
	*/
	const ERR_INVALID_SCOPE = 1001;

	/**
	* Error thrown when user verification GET param is not found
	*/
	const ERR_NO_VERIFIER = 1002;

	/**
	* Error thrown when params are generally malformed
	*/
	const ERR_INVALID_PARAMS = 1003;

	/**
	* URL segments for LinkedIn Oauth functions
	* @var array
	*/
	protected static $urls =array(
		'request_token' => 'requestToken', // Token to initiate LinkedIn auth
		'authorize' => 'authenticate', // Verfier PIN from user
		'access_token' => 'accessToken' // 60-day access token for user
	);

	/**
	* Scope of data to which the application is requesting access.
	* @see Li_Oauth::set_scope()
	* @var string
	*/
	protected static $scope = '';

	/**
	* Map of keys for acceptable scopes to their LinkedIn names.
	* @var array
	*/
	protected static $valid_scopes =array(
		'basicprofile' => 'r_basicprofile', // "Your Profile Overview"
		'fullprofile' => 'r_fullprofile', // "Your Full Profile"
		'emailaddress' => 'r_emailaddress', // "Your Email Address"
		'network' => 'r_network', // "Your Connections"
		'contactinfo' => 'r_contactinfo', // "Your Contact Info"
		'updates' => 'rw_nus', // "Network Updates"
		'groups' => 'rw_group', // "Group Discussions"
		'messages' => 'w_messages', // "Invitations and Messages"
	);

	/**
	* Oauth anonymous request token
	* @see Li_Oauth::request_token()
	* @var array
	*/
	protected static $anon_token = array();

	/**
	* 60-day Oauth access token for a specific LI user
	* @see Li_Oauth::access_token()
	* @var array
	*/
	protected static $user_token = array();

	/**
	* The class constructor
	*
	* Passes the consumer's API key and secret to the Oauth constructor.
	* Optionally sets token and secret.
	* Turns the LI URLs array values into absolute paths.
	* Returns the instance.
	*
	* @param string $consumer_key LinkedIn API Key
	* @param string $consumer_secret LinkedIn Secret Key
	* @param string $token An Oauth request or access token
	* @param string $token_secret An Oauth request or access token secret
	* @return object LI_OAuth object
	*/
	public function __construct($consumer_key, $consumer_secret, $token='', $token_secret='')
	{
		parent::__construct($consumer_key, $consumer_secret);
		if ($token and $token_secret)
		{
			self::setToken($token,$token_secret);
		}

		foreach ( self::$urls as $key => $value )
		{
			self::$urls[$key] = self::LI_OAUTH_URI.$value;
		}
		return;
	}

	/**
	* Get an anonymous LinkedIn request token to initiate authentication.
	*
	* After granting permission to the application, the LinkedIn user will be
	* returned to the given $callback_url.
	*
	* The accepted values for the $given_scope are defined by the class's
	* valid_scopes property.
	*
	* Returns a response array on success or throws exception. (Example in PHP
	* docs seems to say false can be returned w/o exception.)
	*
	* Elements in returned array:
	*    string oauth_token,
	*    string oauth_token_secret,
	*    bool oauth_callback_confirmed,
	*    string xoauth_request_auth_url,
	*    int oauth_expires_in
	*
	* @link http://www.php.net/manual/en/oauth.getrequesttoken.php
	*
	* @param string $callback_url URL of application's access token handler
	* @param array $given_scope List of requested permissions
	* @return array
	*/
	public function request_token($callback_url, $given_scope=array())
	{
		// Set the scope of permissions the application is asking of LI user.
		if ($given_scope) self::set_scope($given_scope);
		$passed_scope = self::$scope ?
		                self::$scope :
		                self::$valid_scopes['basicprofile'];

		// Get an anonymous token from LinkedIn.
		$query = http_build_query( array('scope'=>$passed_scope) );
		$req_url = self::url('request_token')."?$query";
		$token = self::getRequestToken($req_url, $callback_url);

		// Save the token and return it to caller.
		self::$anon_token = $token;
		return $token;
	}

	/**
	* Redirect LinkedIn user to LinkedIn.com to verify request token.
	*
	* After successfully calling request_token() to get an anonymous token from
	* LI, call this to authorize associating that token with the user.
	*
	* @return void
	*/
	public function user_authorization_redirect()
	{
		// LinkedIn returns a authorize URL as part of request token. Assuming
		// they continue to do so, we'll use that, but we can fall back to $urls.
		$url = isset(self::$anon_token['xoauth_request_auth_url']) ?
			   self::$anon_token['xoauth_request_auth_url'] :
			   self::url('authorize');

		// Redirect user; include the non-secret 1/2 of the request token.
		$oauth_token = self::$anon_token['oauth_token'];
		$query = http_build_query( compact('oauth_token') );
		header( "HTTP/1.1 303 See Other", true, 303 );
		header("Location: $url?$query");
		return;
	}

	/**
	* Exchange a request token and verifier for a LinkedIn access token.
	*
	* The callback_url passed to request_token() should call this method as the
	* last step in authentication.  The LI user has verfied the anonymous
	* token and LinkedIn has returned a verifier code in the GET params. Passing
	* the anonymous token and the verifier back to LinkedIn should give us back
	* a user access token that is good for 60 days.
	*
	* In 'oob' cases, the verifier PIN may be passed to the method.
	*
	* Success returns an array with these elements:
	*    string oauth_token,
	*    string oauth_token_secret,
	*    int oauth_expires_in
	*    int oauth_authorization_expires_in
	*
	* @link http://www.php.net/manual/en/oauth.getaccesstoken.php
	*
	* @param int $verifier
	* @return array
	*/
	public function access_token( $verifier=0 )
	{
		$verifier = $verifier ? (int) $verifier : (int) $_GET['oauth_verifier'];
		if ( ! $verifier )
		{
			$msg = 'Verifier must be a non-zero integer.  If no $verifier is '.
				'given, Li_Oauth::access_token() expects an GET param named '.
				'"oauth_verifier".';
			throw new InvalidArgumentException($msg, self::ERR_NO_VERIFIER);
		}

		$token = self::getAccessToken(
			self::url('access_token'), '', $verifier);
		self::$user_token = $token;
		return $token;
	}

	/**
	* Fetch a LI API resource.
	*
	* This method provides a shortcut to Oauth::fetch() that returns a standard
	* object representation of LinkedIn's json response.
	*
	* @param string $url The relative URL of the LI resource
	* @param array $params Arguments for LI resource
	* @param string $method (get|post|put|head|delete)
	* @param array $headers HTTP header pairs
	* @return object
	*/
	public function fast_fetch($url, $params=array(),
		$method='get', $headers=array())
	{
		if ( ! is_array($params) or ! is_array($headers))
		{
			$msg = 'LiOauth::fast_fetch() params and headers are expected to '.
				'be arrays.';
			throw new InvalidArgumentException($msg, self::ERR_INVALID_PARAMS);
		}
		$headers['x-li-format'] = 'json';

		switch ($method)
		{
			case 'post': $passed_method = OAUTH_HTTP_METHOD_POST;
				break;
			case 'put': $passed_method = OAUTH_HTTP_METHOD_PUT;
				break;
			case 'head': $passed_method = OAUTH_HTTP_METHOD_HEAD;
				break;
			case 'delete': $passed_method = OAUTH_HTTP_METHOD_DELETE;
				break;
			case 'get':
			default:
				$passed_method = OAUTH_HTTP_METHOD_GET;
		}
		$url = self::url($url);
		self::fetch($url, $params, $passed_method, $headers);
		return json_decode(self::getLastResponse());
	}

	/**
	* Get the absolute URL for a LI API call.
	*
	* This method accepts either a key name from the class $urls array or an
	* arbitrary URL string to be appended to the LinkedIn API URI.
	*
	* If a URL is given, only the path component is accepted. Other components
	* will be ignored.
	*
	* @param string $key_or_url
	* @return string
	*/
	public function url($key_or_url)
	{
		if (isset(self::$urls[$key_or_url])) return self::$urls[$key_or_url];
		$path = parse_url($key_or_url, PHP_URL_PATH);
		return self::LI_API_URI.$path;
	}

	/**
	* Turn a given array of requested scopes into a string.
	*
	* When requesting an anonymous token, LinkedIn expects us to provide a
	* string describing the permissions the user is authorizing for us. This
	* method takes an array of permissions, filters out any that are not
	* accepted by LinkedIn and sets the class scope property to a string with
	* spaces delimiting the array values. This string is suitable for inclusion
	* in the request_token() URL.
	*
	* If you only need a single permission, $scopes_in may be a string rather
	* than an array.
	*
	* This method returns an array of the acceptable permissions given.
	* (Note: not the string.). An exception is thrown if no acceptable
	* permissions are found.
	*
	* @param mixed $scopes_in
	* @return array
	*/
	public function set_scope($scopes_in)
	{
		$scopes_in = (array) $scopes_in;
		$scopes_set = array();
		$valid_keys = array_keys(self::$valid_scopes);

		foreach ($scopes_in as $scope)
		{
			if (in_array($scope, $valid_keys)) $scopes_set[] = self::$valid_scopes[$scope];
		}

		if ( ! count($scopes_set))
		{
			$msg = 'LiOauth::set_scope(): no valid scope provided.';
			throw new InvalidArgumentException($msg, self::ERR_INVALID_SCOPE);
		}

		self::$scope = implode(' ', $scopes_set);
		return $scopes_set;
	}

	/**
	* Reset the tokens (simply a wrapper for Oauth::setToken)
	*
	* @param string $token
	* @param string $secret
	* @return bool
	*/
	public function reset_token($token, $secret)
	{
		return self::setToken($token, $secret);
	}

}
?>