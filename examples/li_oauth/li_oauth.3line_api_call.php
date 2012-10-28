<?php
/**
* Example of the using the Li_Oauth class to fetch and echo developer's photo in
* 3 lines of code.
*
* It is assummed you have an OAuth access token and API keys already available
* to pass to the Li_Oauth constructor. In this example that is done with the
* include(), so it is not counted as a line of code.
*
* @author Dan Mills <dan@lunamouse.com>
* @license http://opensource.org/licenses/bsd-license.php
*/

// Ceci n'est pas une pipe.
include('./api_keys.php');

// 1) Load class.
require_once('../classes/li_oauth.php');

// 2) Instantiate Li_Ouath object with API keys and user tokens.
$li = new Li_Oauth(LI_API_KEY, LI_API_SECRET, LI_USER_TOKEN, LI_USER_SECRET);

// 3) Make the call.
echo '<img src="'.$li->fast_fetch('people/~:(pictureUrl)')->pictureUrl.'" />';
?>
