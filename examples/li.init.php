<?php
/**
* Example of the using the Li class to initiate an instance.
*
* @author Dan Mills <dan@lunamouse.com>
* @license http://opensource.org/licenses/bsd-license.php
*/

// Load the Li_Oauth class
require_once('../classes/li.php');

// Set your LinkedIn application's API Key and Secret Key.
include('./api_keys.php');

// Using the Li init() method rather than creating an instance with the new
// keyword offers automatic exception handling. A successful instance will not
// have an error_code property, and can be used to fast_fetch() profile data.
// Or do anyting else in the Oauth, Li_Oauth or Li_People classes.
$li = Li::init($credentials, 'people');
echo isset($li->error_code) ?
     "<p>Error: {$li->error_message}</p>" :
	 '<p><img src="'.$li->fast_fetch('people/~:(pictureUrl)')->pictureUrl.'" /></p>';
?>
