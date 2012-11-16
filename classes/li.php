<?php
/**
* Library to authorize and call the LinkedIn API.
*
* @author Dan Mills <dan@lunamouse.com>
* @license http://opensource.org/licenses/bsd-license.php
*/

require_once(__DIR__.'/li_oauth.php');

/**
* Class of static functions for accessing the LinkedIn API.
*/
class Li {

	/**
	* Error thrown when params are generally malformed
	*/
	const ERR_INVALID_PARAMS = 1003;

	/**
	* Error thrown when an invalid API class is called
	*/
	const ERR_API_NAME = 1004;

	/**
	* Error thrown when an invalid library method is called
	*/
	const ERR_API_METHOD = 1005;

	/**
	* Ask the LI user to grant permission to access their data.
	*
	* @param array $credentials api_key, api_secret, [user_token, user_secret]
	* @param string $api_name API class (currently only People available)
	* @param string $callback_func Request token will be passed to this function
	* @param string $callback_url User will be directed here after granting access
	* @param array $scope List of permissions requested from user
	* @return object StdObject representation of Request token array
	*/
	public static function auth_request($credentials, $api_name, $callback_func,
		$callback_url='', $scope=array())
	{
		try {
			$instance = self::init($credentials, $api_name, FALSE);
			$token = $instance->request_token($callback_url, $scope);
			$callback_func($token['oauth_token'], $token['oauth_token_secret']);
			$instance->user_authorization_redirect();
			return (object) $token;
		} catch (Exception $e) {
			return self::exception_handler($e);
		}
	}

	/**
	* Exchange a request token and verifier for a LinkedIn access token.
	*
	* @param array $credentials api_key, api_secret, user_token, user_secret
	* @param string $api_name API class (currently only People available)
	* @return object oauth_token, oauth_token_secret, oauth_expires_in
	*/
	public static function auth_token($credentials, $api_name)
	{
		try {
			$instance = self::init($credentials, $api_name, FALSE);
			$a_token = $instance->access_token();
			return (object) $a_token;
		} catch (Exception $e) {
			return self::exception_handler($e);
		}
	}

	/**
	* Call an API method and class
	*
	* pseudo-param array $credentials api_key, api_secret, user_token, user_secret
	* pseudo-param string $api_method Class and method to call delimlited by '::'
	* pseudo-param mixed $arg1, $arg2, ... Arguments to passt to method
	* @return mixed
	*/
	public static function call()
	{
		try {
			$args = func_get_args();
			$credentials = array_shift($args);

			list($api_name, $method) = explode('::', array_shift($args));
			$instance = self::init($credentials, $api_name, FALSE);

			if ( ! method_exists($instance, $method))
			{
				$bad_method = get_class($instance).'::'.$method;
				$msg = "API method '$bad_method' not found.";
				throw new Exception($msg, self::ERR_API_METHOD);
			}

			return call_user_func_array(array($instance,$method), $args);
		} catch (Exception $e) {
			return self::exception_handler($e);
		}
	}

	/**
	* Create an API class instance
	*
	* @param array $credentials api_key, api_secret, [user_token, user_secret]
	* @param string $api_name API class (currently only People available)
	* @param bool $catch Catch error or throw it? (catch)
	* @return object
	*/
	public static function init($credentials, $api_name, $catch=TRUE)
	{
		try {
			$class_name = 'Li_'.ucwords(strtolower($api_name));
			$file_name = 'li_'.strtolower($api_name).'.php';

			$include = @include_once(__DIR__."/$file_name");
			if ( ! $include or ! class_exists($class_name))
			{
				$msg = "API class '$api_name' not found.";
				throw new Exception($msg, self::ERR_API_NAME);
			}

			if ( ! is_array($credentials) or count($credentials) < 2)
			{
				$msg = 'Li::init() $credentials must be an array';
				throw new Exception($msg, self::ERR_INVALID_PARAMS);
			}
			extract($credentials);
			return @new $class_name($api_key, $api_secret, $user_token,
				$user_secret);
		} catch (Exception $e) {
			return self::exception_handler($e, $catch);
		}
	}

	/**
	* Handle an exception
	*
	* @param object $e Exception
	* @param bool $catch Catch error or throw it? (catch)
	* @return object StdClass
	* @access protected
	*/
	protected static function exception_handler($e, $catch=TRUE)
	{
		$response = @json_decode($e->lastResponse);
		$error_message = $e->getMessage();
		$error_message .= isset($response->message) ?
			              ": {$response->message}" : '';
		$error_code = $e->getCode();
		$error_type = get_class($e);

		$error_arr = compact(
				'error_message','error_code','error_type','response');
		if ($catch)
		{
			return (object) $error_arr;
		} else {
			throw new $error_type($error_message, $error_code);
		}
	}

}
?>