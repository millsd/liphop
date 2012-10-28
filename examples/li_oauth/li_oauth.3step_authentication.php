<?php if (isset($_GET['code'])) { die(highlight_file(__FILE__, 1)); }
/**
* Example of the using the Li_Oauth class to fetch current user's LinkedIn name,
* email address, headline/title, location and profile summary.
*
* @author Dan Mills <dan@lunamouse.com>
* @license http://opensource.org/licenses/bsd-license.php
*/

// Set your LinkedIn application's API Key and Secret Key.
include('./api_keys.php');

// Load the Li_Oauth class
require_once('../classes/li_oauth.php');

// We will be storing our keys in a PHP session.
// A production application would probably want to use a database.
session_start();

// This will be a 3 step process (well at least 2.5 steps). Each step is a
// distinct http request.  We're keeping track of which step we are on with a
// GET param named 'step'.
$step = isset($_GET['step']) ? $_GET['step'] : 1;

// Try to take a step. There's plenty of ways this could fail, so instead
// of handling each one separately we'll just catch the whole block.
try {
	switch( $step ) {

		// Step 1 is too get an anonymous token from LinkedIn and to ask the
		// user to authorize us to access their profile.
		case 1:
			// Instantiate an Li_Oauth object with our API's key and secret.
			$li = new Li_Oauth(LI_API_KEY, LI_API_SECRET);

			// There are a variety of different permission types that LinkedIn
			// users can grant an application.  Here we are asking for
			// permission to use their basic profile and email address.
			// See https://developer.linkedin.com/documents/authentication#granting
			$scopes = array('basicprofile', 'emailaddress');

			// Here we ask LinkedIn for an anonymous token. We give them a
			// callback URL where the user will return after granting us
			// permission. In this case we want them to go to step 2 of this
			// script.  We also pass the permission scopes we defined above.
			$request_token = $li->request_token(
				"{$_SERVER['SCRIPT_URI']}?step=2", $scopes);

			// Since request_token() didn't throw an exception, we can assume
			// that we have successfully fetched a request token from LI.
			// We save the token and secret in the session, so they will be
			// available to step 2.
			$_SESSION['request_token'] = $request_token['oauth_token'];
			$_SESSION['request_secret'] = $request_token['oauth_token_secret'];

			// Now we redirect the user to linkedin.com so that they may consent
			// to associating the request token with their account.
			$li->user_authorization_redirect();
			break;

		// Step 2 is the callback URL we gave to LinkedIn during step 1.
		// It's job is to exchange the anonymous request token for an
		// access token associated with the user.
		case 2:
			// This time our Li_Oauth object gets instantiated with the
			// request token from the session, as well the API keys.
			$li = new Li_Oauth(LI_API_KEY, LI_API_SECRET,
				$_SESSION['request_token'], $_SESSION['request_secret']);

			// If we run into a problem, perhaps we should debug.
			$li->debug = TRUE;

			// Here is where we get the new token.  If no exception is thrown,
			// we can assume everything worked as expected.
			$access_token = $li->access_token();

			// You could make an API call now, with something like:
			// $li->reset_token($access_token['oauth_token'], $access_token['oauth_token_secret']);
			// $person = $li->fast_fetch("people/~");
			//
			// That would be the "2.5 step" option.  Instead we're going to
			// redirect the user to step 3, since that more readily mimics what
			// your application will probably want to do; i.e. authenticate
			// once and then make repeated API calls using the same
			// credentials from a variety of URLs.

			// The access token is good for 60 days, so we'll save it in the
			// session for use later.  The expiration timestamp is available in
			// $access_token['oauth_expires_in'] if you'd like.
			//
			// Though we are using $_SESSION in this example, these strings are
			// really something you want to save server-side, both for security
			// and convenience. They should be considered sensitive data.
			$_SESSION['user_token'] = $access_token['oauth_token'];
			$_SESSION['user_secret'] = $access_token['oauth_token_secret'];

			// Send user to step 3.
			header( 'HTTP/1.1 303 See Other', TRUE, 303 );
			header("Location: {$_SERVER['SCRIPT_URL']}?step=3");
			break;

		// Step 3 is where we actually make our API call since we have now
		// completed the Oauth authorization dance.
		case 3:
			// This time our Oauth instance gets the user access token saved
			// in the session during step 2.
			$li = new Li_Oauth(LI_API_KEY, LI_API_SECRET,
				$_SESSION['user_token'], $_SESSION['user_secret']);
			$li->debug = TRUE;

			// Here we set the fields of data we'd like to fetch about our user.
			// See https://developer.linkedin.com/documents/profile-fields
			$fields = 'formattedName,emailAddress,headline,summary,location:(name)';

			// Finally here is the actual API call.  We pass in the URL of the
			// LinkedIn API resource, including our requested fields.
			// See https://developer.linkedin.com/documents/profile-api
			//
			// Li_Oauth::fast_fetch() returns a standard object so it's not
			// necessary to parse an XML response.  If you actually wanted the
			// XML, you could use $li->fetch() as described in the PECL Oauth
			// docs.
			$current_li_user = $li->fast_fetch("people/~:($fields)");

			// And we're done.  Let's output the data we've gathered about
			// $current_li_user.  And why not display some links and some info
			// about the Li_Oauth object itself?
			echo '<html>
				<h1>$current_li_user = </h1><pre>'.print_r($current_li_user,1).'</pre>
				<p>Where now?
				<a href="'.$_SERVER['SCRIPT_URL'].'">Start over at Step 1</a> |
				<a href="'.$_SERVER['SCRIPT_URL'].'?step=2">Skipping to step 2 raises exception</a> |
				<a href="'.$_SERVER['SCRIPT_URL'].'?step=3">Reloading step 3 is no problem</a>
				</p>
				<pre>FYI, here is $li = '.print_r($li,1).'</pre>
			  </html>';
			break;
		default:
			throw new Exception('Unknown step.');
	}
} catch (Exception $e) {
	// Our step failed. Echo some info about what went wrong.
	$class = get_class($e);
	$error_code = $e->getCode();
	$message = $e->getMessage();
	$response = $e->lastResponse;
	$debug_info = $e->debugInfo;
	$oops = compact('error_code', 'message', 'response', 'debug_info','class');
	echo '<html><h1>$oops = </h1><pre>'.htmlentities(print_r($oops,1)).'</pre></html>';
}

?>