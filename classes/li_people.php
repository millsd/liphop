<?php
/**
* Fetch resources from the LinkedIn People/Profile API
*
* @link https://developer.linkedin.com/documents/profile-api
*
* @author Dan Mills <dan@lunamouse.com>
* @license http://opensource.org/licenses/bsd-license.php
*/
require_once(__DIR__.'/li_oauth.php');

/**
* People API class
*/
class Li_People extends Li_Oauth implements Li_Api {

	/**
	* Constructor.
	*
	* @param string $consumer_key LinkedIn API Key
	* @param string $consumer_secret LinkedIn Secret Key
	* @param string $token An Oauth request or access token
	* @param string $token_secret An Oauth request or access token secret
	* @return object LI_People object
	*/
	public function __construct($consumer_key, $consumer_secret, $token='', $token_secret='')
	{
		parent::__construct($consumer_key, $consumer_secret, $token, $token_secret);

		// Add people API to class urls property
		self::$urls['people'] = self::LI_API_URI.'people/';
		self::$urls['my'] = self::$urls['people'].'~'; // Logged in LI user
		self::$urls['id'] = self::$urls['people'].'id=';
		self::$urls['url'] = self::$urls['people'].'url=';
	}

	/**
	* The profile API.
	*
	* @link https://developer.linkedin.com/documents/profile-fields
	*
	* @param array|string $fields LI profile fields
	* @param string $segment LI resource url segment (~|id=12345|url=http...)
	* @param bool $produce Parse response to match fields?
	* @return object StdClass
	*/
	public function profile($fields, $segment='', $headers=array(), $produce=TRUE)
	{
		if ( $segment and $segment != '~' )
		{
			$url = (strpos($segment,'/') === FALSE) ?
				   self::url('id').$segment : self::url('url').urlencode($segment);
		} else {
			$url = self::url('my');
		}

		/*$url = $segment ?
			   self::$urls['people'].$segment :
			   self::url('my');*/

		if (is_string($fields))
		{
			$url .= ":($fields)";
			return self::fast_fetch($url);
		}

		$fields = (array) $fields;
		$url .= self::field_reduce($fields);
		$fetched_profile = self::fast_fetch($url, array(), 'get', $headers);

		return $produce ?
			   self::field_produce($fields, $fetched_profile) :
			   $fetched_profile;
	}

	/**
	* Parse response to match fields.
	*
	* @param array $fields Requested fields
	* @param object $data LI Response as returned by self::fast_fetch()
	* @return object StdClass
	* @access protected
	*/
	protected function field_produce($fields, $data)
	{
		// We are building an array named $p.
		$p = array();

		// Loop over the first layer of fields.
		foreach ($fields as $top_level_key => $top_level_field)
		{
			// If first layer field is an array, descend into the second level.
			if (is_array($top_level_field))
			{
				// Loop over the second layer of fields.
				foreach ($top_level_field as $second_level_key => $second_level_field)
				{
					// If second layer field is also an array, descend once more.
					if (is_array($second_level_field))
					{
						// At this third level, the field value is expected to
						// be either null or an object with a 'values' property.
						// Loop over that property assigning data from the
						// second level key.
						if (isset($data->$top_level_key->values))
						{
							foreach ($data->$top_level_key->values as $i => $trash)
							{
								$p[$top_level_key][$i][$second_level_key] =
									@$data->$top_level_key->values[$i]->$second_level_key;
							}
						}
					} else {
					// Second layer field is a string, so assign its value to $p.
						if (isset($data->$top_level_key->_total) and $data->$top_level_key->_total)
						{
							// The second layer value is an object and it should
							// have a 'values' property.  Loop over that property
							// assigning data from the second level field.
							foreach ($data->$top_level_key->values as $i => $trash)
							{
								$p[$top_level_key][$i][$second_level_field] =
									@$data->$top_level_key->values[$i]->$second_level_field;
							}
						} else {
							// The second layer value is a scalar. Simple enough.
							$p[$top_level_key] = $data->$top_level_key;
						}
					}
				}
			} else {
			// First layer field is a string, so assign its value to $p. (if it exists)
				if (isset($data->$top_level_field))
				{
					if (is_object($data->$top_level_field))
					{
						// If the first layer value is an object make it an array.
						if (isset($data->$top_level_field->values))
						{
							// Use the object's 'values' property as the array.
							$p[$top_level_field] = $data->$top_level_field->values;
						} else {
							// Object has no 'values' so the array is empty.
							$p[$top_level_field] = array();
						}
					} else {
						// The first layer value is a scalar. Simple enough.
						$p[$top_level_field] = $data->$top_level_field;
					}
				} else {
					$p[$top_level_field] = NULL;
				}
			}
		}

		// Make sure that anything below the top-level arrays consist of objects
		// or scalars, not another level of arrays.
		foreach ($p as $key => $value)
		{
			if (is_array($value))
			{
				foreach ($value as $i => $may_be_an_array)
				{
					$p[$key][$i] = (object) $may_be_an_array;
				}
			} elseif (is_object($value))
			{
				$p[$key] = (array) $value;
			}
		}

		// Return $p as an object.
		return (object) $p;
	}

	/**
	* Convert an array into string suitable for use as a LI field selector.
	*
	* @link https://developer.linkedin.com/documents/field-selectors
	*
	* @param array $fields Requested fields
	* @param object $data LI Response as returned by self::fast_fetch()
	* @return object StdClass
	* @access protected
	*/
	private function field_reduce($fields)
	{
		$reduced = ':(';
		foreach ($fields as $key => $f)
		{
			if (is_array($f))
			{
				$reduced .= $key.self::field_reduce($f).',';
			} else {
				$reduced .= "$f,";
			}
		}
		$reduced = rtrim($reduced, ',').')';
		return $reduced;
	}

}
?>