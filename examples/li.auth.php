<?php
/**
* Example of the using the Li class to authorize access.
*
* @author Dan Mills <dan@lunamouse.com>
* @license http://opensource.org/licenses/bsd-license.php
*/

// Load the Li class
require_once('../classes/li.php');

// Set your LinkedIn application's API Key and Secret Key.
include('./api_keys.php');

// We will be storing our tokens in a PHP session.
// A production application would probably want to use a database.
session_start();

// We need a callback function to pass to Li::auth_request() that will save
// the Oauth request token and secret that will be passed to it.
function save_request_token($token, $secret)
{
	$_SESSION['request_token'] = $token;
	$_SESSION['request_secret'] = $secret;
	return;
}

// This function will save the LI user's Oauth access token and secret.  These
// credentials are good for 60 days, so it would be better to save these values
// in a server-side data store. More secure too.
function save_user_token($token, $secret)
{
	unset($_SESSION['request_token'],$_SESSION['request_secret']);
	$_SESSION['user_token'] = $token;
	$_SESSION['user_secret'] = $secret;
	return;
}

// This script handles to types of HTTP Get requests.  The default type calls
// Li::auth_request() to redirect the user to LinkedIn.com where they can grant
// our app permissions.  This is what happens unless the $_GET['oauth_verifier']
// argument is present.
if ( ! isset($_GET['oauth_verifier']))
{
	// $scope is array of permissions we are requesting.
	// See https://developer.linkedin.com/documents/authentication#granting
	$scope = array('fullprofile', 'emailaddress', 'contactinfo');

	// Set where we want LinkedIn to send the user after they grant perms.
	// In this case, we will send them back to this script with a 'next' arg
	// whose value is a URL for one last redirect to the call.php example.
	$next = dirname($_SERVER['SCRIPT_URI']).'/li.call.php';
	$callback_url = $_SERVER['SCRIPT_URI'].'?next='.urlencode($next);

	// The meaty part of asking the LI user for access:
	Li::auth_request($credentials, 'people', 'save_request_token',
		$callback_url, $scope);
} else {
	// Since the $_GET['oauth_verifier'] arg is present, this must be the second
	// type of HTTP request this script handles: the callback after user has
	// granted permission and been returned from LinkedIn.com.  We need
	// to grab the request token from the session to pass to Li::auth_token.
	$credentials['user_token'] = $_SESSION['request_token'];
	$credentials['user_secret'] = $_SESSION['request_secret'];

	// Here is the goal of the entire example script: getting an access token.
	$token = Li::auth_token($credentials, 'people');

	// If there was a problem, $token will have an error_code property
	if ($token->error_code) die("Error: {$token->error_message}");

	// Success will return a token object with these properties:
	//     string oauth_token
	//     string oauth_token_secret
	//     int oauth_expires_in

	// Save out token for later use.
	save_user_token($token->oauth_token, $token->oauth_token_secret);

	// Let's try using our token by redirecting to $next, which we set earlier
	// to point to the li.call.php example.
	$next = urldecode($_GET['next']);
	header( "HTTP/1.1 303 See Other", true, 303 );
	header("Location: $next");
}
?>
