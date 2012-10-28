<?php
/**
* The following constants are used throughout the examples. If you would like to
* try the examples out, replace the "x" values with the ones found in the
* "OAuth Keys" area of your LinkedIn application's details page and save the
* file as 'api_keys.php' in this directory.
*
* Don't share these values publically!
*
* @link https://www.linkedin.com/secure/developer
*/
define('LI_API_KEY', 'xxxxxxxxxxxx'); // "API Key"
define('LI_API_SECRET', 'xxxxxxxxxxxxxxxx'); // "Secret Key"
define('LI_USER_TOKEN', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'); // "OAuth User Token"
define('LI_USER_SECRET', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'); // "OAuth User Secret"

$credentials = array(
	'api_key' => LI_API_KEY,
	'api_secret' => LI_API_SECRET,
	'user_token' => LI_USER_TOKEN,
	'user_secret' => LI_USER_SECRET
);
?>