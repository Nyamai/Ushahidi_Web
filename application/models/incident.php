<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Model for reported Incidents
 *
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author	   Ushahidi Team <team@ushahidi.com>
 * @package	   Ushahidi - http://source.ushahididev.com
 * @module	   Incident Model
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license	   http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL)
 */

class Incident_Model extends ORM
{
	// Relationships
	protected $has_many = array('category' => 'incident_category', 'media', 'verify', 'comment',
		'rating', 'alert' => 'alert_sent', 'form_response', 'incident_lang');
	protected $has_one = array('location','incident_person','user','message','form');
	protected $belongs_to = array('sharing');

	// Database table name
	protected $table_name = 'incident';

	// Prevents cached items from being reloaded
	protected $reload_on_wakeup	  = FALSE;

	// Ignored columns - objects that don't actually exist in the incident model
	protected $ignored_columns = array('incident_hour','incident_minute','incident_ampm',
		'latitude', 'longitude', 'location_name', 'incident_news', 'incident_video', 
		'categories', 'incident_photo', 'person_first', 'person_last', 'person_email');
	
	/**
	 * Save report method
	 *
	 * @param  array	form array
	 * @return	ORM Incident_Model
	 * @throws	Kohana_User_Exception
	 */ 
	public function save_report($post)
	{
		// If we have an existing Incident
		if ($this->loaded)
		{
			ORM::factory('Incident_Category')
				->where('incident_id',$this->id)
				->delete_all();

			ORM::factory('Media')
				->where('incident_id',$this->id)
				->where('media_type <> 1')
				->delete_all();

			ORM::factory('Incident_Person')
				->where('incident_id',$this->id)
				->delete_all();

			$this->incident_datemodify = date("Y-m-d H:i:s",time());
		}

		// STEP 1: SAVE LOCATION
		$location = ORM::factory('location');
		$location->location_name = $post->location_name;
		$location->latitude = $post->latitude;
		$location->longitude = $post->longitude;
		$location->location_date = date("Y-m-d H:i:s",time());
		$location->save();

		// STEP 2: SAVE INCIDENT
		$this->location_id = $location->id;
		$this->form_id = $post->form_id;
		$this->user_id = 0;
		$this->incident_title = $post->incident_title;
		$this->incident_description = $post->incident_description;
		$incident_date=explode("/",$post->incident_date);
		$incident_date=$incident_date[2]."-".$incident_date[0]."-".$incident_date[1];
		$incident_time = $post->incident_hour.":".$post->incident_minute.":00 ".$post->incident_ampm;
		$this->incident_date = $incident_date." ".$incident_time;
		$this->incident_dateadd = date("Y-m-d H:i:s",time());
		parent::save();

		// STEP 3: SAVE CATEGORIES
		foreach($post->categories as $item)
		{
			$incident_category = ORM::factory('incident_category');
			$incident_category->incident_id = $this->id;
			$incident_category->category_id = $item;
			$incident_category->save();
		}

		// STEP 4: SAVE MEDIA
		// a. News
		foreach($post->incident_news as $item)
		{
			if ( ! empty($item))
			{
				$news = ORM::factory('media');
				$news->location_id = $location->id;
				$news->incident_id = $this->id;
				$news->media_type = 4;		// News
				$news->media_link = $item;
				$news->media_date = date("Y-m-d H:i:s",time());
				$news->save();
			}
		}

		// b. Video
		foreach($post->incident_video as $item)
		{
			if ( ! empty($item))
			{
				$video = ORM::factory('media');
				$video->location_id = $location->id;
				$video->incident_id = $this->id;
				$video->media_type = 2;		// Video
				$video->media_link = $item;
				$video->media_date = date("Y-m-d H:i:s",time());
				$video->save();
			}
		}

		// c. Photos
		$filenames = upload::save('incident_photo');
		$i = 1;

		foreach ($filenames as $filename)
		{
			$new_filename = $this->id."_".$i."_".time();

			// Resize original file... make sure its max 408px wide
			Image::factory($filename)->resize(408,248,Image::AUTO)
				->save(Kohana::config('upload.directory', TRUE).$new_filename.".jpg");

			// Create thumbnail
			Image::factory($filename)->resize(70,41,Image::HEIGHT)
				->save(Kohana::config('upload.directory', TRUE).$new_filename."_t.jpg");

			// Remove the temporary file
			unlink($filename);

			// Save to DB
			$photo = ORM::factory('media');
			$photo->location_id = $location->id;
			$photo->incident_id = $this->id;
			$photo->media_type = 1; // Images
			$photo->media_link = $new_filename.".jpg";
			$photo->media_thumb = $new_filename."_t.jpg";
			$photo->media_date = date("Y-m-d H:i:s",time());
			$photo->save();
			$i++;
		}


		// STEP 5: SAVE CUSTOM FORM FIELDS
		if (isset($post->custom_field))
		{
			foreach($post->custom_field as $key => $value)
			{
				$form_response = ORM::factory('form_response')
					->where('form_field_id', $key)
					->where('incident_id', $this->id)
					->find();
				if ($form_response->loaded == true)
				{
					$form_response->form_field_id = $key;
					$form_response->form_response = $value;
					$form_response->save();
				}
				else
				{
					$form_response->form_field_id = $key;
					$form_response->incident_id = $this->id;
					$form_response->form_response = $value;
					$form_response->save();
				}
			}
		}

		// STEP 6: SAVE PERSONAL INFORMATION
		$person = ORM::factory('incident_person');
		$person->location_id = $location->id;
		$person->incident_id = $this->id;
		$person->person_first = $post->person_first;
		$person->person_last = $post->person_last;
		$person->person_email = $post->person_email;
		$person->person_date = date("Y-m-d H:i:s",time());
		$person->save();

		// ADMIN ONLY
		if (Auth::instance()->logged_in() AND isset($this->admin)
			AND $this->admin)
		{
			// Incident Evaluation
			$this->incident_active = $post->incident_active;
			$this->incident_verified = $post->incident_verified;
			$this->incident_source = $post->incident_source;
			$this->incident_information = $post->incident_information;
			// Service (Twitter, Email, SMS etc)
			if($this->service_id)
			{
				if ($service_id == 1)
				{ // SMS
					$this->incident_mode = 2;
				}
				elseif ($service_id == 2)
				{ // Email
					$this->incident_mode = 3;
				}
				elseif ($service_id == 3)
				{ // Twitter
					$this->incident_mode = 4;
				}
				elseif ($service_id == 4)
				{ // Laconica
					$this->incident_mode = 5;
				}
			}
			parent::save();

			// Record Approval/Verification Action
			$verify = new Verify_Model();
			$verify->incident_id = $this->id;
			$verify->user_id = $_SESSION['auth_user']->id;			// Record 'Verified By' Action
			$verify->verified_date = date("Y-m-d H:i:s",time());
			if ($post->incident_active == 1)
			{
				$verify->verified_status = '1';
			}
			elseif ($post->incident_verified == 1)
			{
				$verify->verified_status = '2';
			}
			elseif ($post->incident_active == 1 AND $post->incident_verified == 1)
			{
				$verify->verified_status = '3';
			}
			else
			{
				$verify->verified_status = '0';
			}
			$verify->save();

			// SAVE LINK TO REPORTER MESSAGE
			// We're creating a report from a message with this option
			if($this->message_id)
			{
				$savemessage = ORM::factory('message', $this->message_id);
				if ($savemessage->loaded == true) 
				{
					$savemessage->incident_id = $this->id;
					$savemessage->save();
				}
			}

			// SAVE LINK TO NEWS FEED
			// We're creating a report from a newsfeed with this option
			if($this->feed_item_id)
			{
				$savefeed = ORM::factory('feed_item', $this->feed_item_id);
				if ($savefeed->loaded == true) 
				{
					$savefeed->incident_id = $this->id;
					$savefeed->location_id = $location->id;
					$savefeed->save();
				}
			}
		}

		return $this;
	}
	
	
	/**
	 * Report Action method
	 *
	 * @param string $action that we will be performing
	 * @return string|bool
	 */
	public function action($action = null)
	{
		if ($this->loaded AND $action)
		{
			$verify = ORM::factory('verify');
			$verify->incident_id = $this->id;
			$action_status = "";
			switch ($action)
			{
				// Mark report as approved
				case 'a':
					$this->incident_active = '1';
					// Tag this as a report that needs to be sent out as an alert
					$this->incident_alert_status = '1';
					// Log this update in 'Verified By' table
					$verify->verified_status = '1';
					$action_status = strtoupper(Kohana::lang('ui_admin.approved'));
					break;
				
				// Mark report as unapproved
				case 'u':
					$this->incident_active = '0';
					// Log this update in 'Verified By' table
					$verify->verified_status = '0';
					$action_status = strtoupper(Kohana::lang('ui_admin.unapproved'));
					break;
				
				// Mark report as verified	
				case 'v':
					if ($this->incident_verified == '1')
					{
						$this->incident_verified = '0';
						$verify->verified_status = '0';
					}
					else
					{
						$this->incident_verified = '1';
						$verify->verified_status = '2';
					}
					$action_status = "VERIFIED";
					break;
				
				// Delete report	
				case 'd':
					parent::delete();
					$action_status = strtoupper(Kohana::lang('ui_admin.deleted'));
					
				default:
					return false;
					
			}
			
			parent::save();
			$verify->save();
			
			return $action_status;
		}
		else
		{
			return false;
		}
	}
	
	
	/**
	 * Overload the delete method
	 *
	 * @return  boolean
	 * @throws  Kohana_User_Exception
	 */
	public function delete()
	{
		if ($this->loaded)
		{
			// Delete Location
			ORM::factory('location')->where('id',$this->location_id)->delete_all();
			
			// Delete Categories
			ORM::factory('incident_category')->where('incident_id',$this->id)->delete_all();
			
			// Delete Translations
			ORM::factory('incident_lang')->where('incident_id',$this->id)->delete_all();
			
			// Delete Photos From Directory
			foreach (ORM::factory('media')
				->where('incident_id',$this->id)
				->where('media_type', 1) as $photo)
			{
				deletePhoto($photo->id);
			}
			
			// Delete Media
			ORM::factory('media')->where('incident_id',$this->id)->delete_all();
			
			// Delete Sender
			ORM::factory('incident_person')->where('incident_id',$this->id)->delete_all();
			
			// Delete relationship to SMS message
			$updatemessage = ORM::factory('message')->where('incident_id',$this->id)->find();
			if ($updatemessage->loaded)
			{
				$updatemessage->incident_id = 0;
				$updatemessage->save();
			}
			
			// Delete Comments
			ORM::factory('comment')->where('incident_id',$this->id)->delete_all();
			
			// Finally delete the report itself
			parent::delete();
		}
	}
	
	
	/**
	 * Validates and optionally saves report from an array.
	 *
	 * @param  array	values to check
	 * @param  boolean	save the record when validation succeeds
	 * @return boolean
	 */
	public function validate(array & $array, $save = FALSE)
	{
		$array = Validation::factory($array)
			->pre_filter('trim', TRUE)
			->add_rules('incident_title', 'required', 'length[3,200]')
			->add_rules('incident_description', 'required')
			->add_rules('incident_date', 'required', 'date_mmddyyyy')
			->add_rules('incident_hour', 'required', 'between[1,12]')
			->add_rules('incident_minute', 'required', 'between[0,59]')
			->add_rules('incident_ampm', 'required', 'in_array[am,pm]')
			->add_rules('latitude', 'required', 'between[-90,90]')
			->add_rules('longitude', 'required', 'between[-180,180]')
			->add_rules('location_name', 'required', 'length[3,200]');
		
		//Validate for no checkboxes checked
		if ( ! isset($array->categories))
		{
			$array->categories = "";
			$array->add_error('categories', 'required');
		}
		else
		{
			$array->add_rules('categories.*', 'required', 'numeric');
		}
		
		// Validate only the incident_news fields that are filled in	
		if ( ! empty($array->incident_news))
		{
			foreach ($array->incident_news as $key => $url) 
			{
				if ( ! empty($url) AND 
					! (bool) filter_var($url, FILTER_VALIDATE_URL, 
					FILTER_FLAG_HOST_REQUIRED))
				{
					$array->add_error('incident_news', 'url');
				}
			}
		}
		
		// Validate only the incident_video fields that are filled in
		if ( ! empty($array->incident_video))
		{
			foreach ($array->incident_video as $key => $url) 
			{
				if ( ! empty($url) AND 
					! (bool) filter_var($url, FILTER_VALIDATE_URL, 
									   FILTER_FLAG_HOST_REQUIRED))
				{
					$array->add_error('incident_video', 'url');
				}
			}
		}

		$array->add_rules('incident_photo', 'upload::valid',
			'upload::type[gif,jpg,png]', 'upload::size[2M]');
		$array->add_rules('person_first', 'length[3,100]');
		$array->add_rules('person_last', 'length[3,100]');
		$array->add_rules('person_email', 'email', 'length[3,100]');

		return parent::validate($array, $save);
		
	} // END function validate
	

	public static function get_active_categories()
	{
		// Get all active categories
		$categories = array();
		foreach (ORM::factory('category')
			->where('category_visible', '1')
			->find_all() as $category)
		{
			// Create a list of all categories
			$categories[$category->id] = array($category->category_title, $category->category_color);
		}
		return $categories;
	}


	/**
	* get the total number of reports
	* @param approved - Only count approved reports if true
	*/
	public static function get_total_reports($approved=false)
	{
		if($approved)
		{
			$count = ORM::factory('incident')->where('incident_active', '1')->count_all();
		}else{
			$count = ORM::factory('incident')->count_all();
		}

		return $count;
	}


	/**
	* get the total number of verified or unverified reports
	* @param verified - Only count verified reports if true, unverified if false
	*/
	public static function get_total_reports_by_verified($verified=false)
	{
		if($verified)
		{
			$count = ORM::factory('incident')->where('incident_verified', '1')->count_all();
		}else{
			$count = ORM::factory('incident')->where('incident_verified', '0')->count_all();
		}

		return $count;
	}

	/**
	* get the timestamp of the oldest report
	* @param approved - Oldest approved report timestamp if true (oldest overall if false)
	*/
	public static function get_oldest_report_timestamp($approved=true)
	{
		if($approved)
		{
			$result = ORM::factory('incident')->where('incident_active', '1')->orderby(array('incident_date'=>'ASC'))->find_all(1,0);
		}else{
			$result = ORM::factory('incident')->where('incident_active', '0')->orderby(array('incident_date'=>'ASC'))->find_all(1,0);
		}

		foreach($result as $report)
		{
			return strtotime($report->incident_date);
		}
	}

	private static function category_graph_text($sql, $category)
	{
		$db = new Database();
		$query = $db->query($sql);
		$graph_data = array();
		$graph = ", \"".  $category[0] ."\": { label: '". str_replace("'","",$category[0]) ."', ";
		foreach ( $query as $month_count )
		{
			array_push($graph_data, "[" . $month_count->time * 1000 . ", " . $month_count->number . "]");
		}
		$graph .= "data: [". join($graph_data, ",") . "], ";
		$graph .= "color: '#". $category[1] ."' ";
		$graph .= " } ";
		return $graph;
	}

	static function get_incidents_by_interval($interval='month',$start_date=NULL,$end_date=NULL,$active='true',$media_type=NULL)
	{
		// Table Prefix
		$table_prefix = Kohana::config('database.default.table_prefix');

		// get graph data
		// could not use DB query builder. It does not support parentheses yet
		$db = new Database();

		$select_date_text = "DATE_FORMAT(incident_date, '%Y-%m-01')";
		$groupby_date_text = "DATE_FORMAT(incident_date, '%Y%m')";
		if ($interval == 'day')
		{
			$select_date_text = "DATE_FORMAT(incident_date, '%Y-%m-%d')";
			$groupby_date_text = "DATE_FORMAT(incident_date, '%Y%m%d')";
		}
		elseif ($interval == 'hour')
		{
			$select_date_text = "DATE_FORMAT(incident_date, '%Y-%m-%d %H:%M')";
			$groupby_date_text = "DATE_FORMAT(incident_date, '%Y%m%d%H')";
		}
		elseif ($interval == 'week')
		{
			$select_date_text = "STR_TO_DATE(CONCAT(CAST(YEARWEEK(incident_date) AS CHAR), ' Sunday'), '%X%V %W')";
			$groupby_date_text = "YEARWEEK(incident_date)";
		}

		$date_filter = "";
		if ($start_date)
		{
			$date_filter .= ' AND incident_date >= "' . $start_date . '"';
		}
		if ($end_date)
		{
			$date_filter .= ' AND incident_date <= "' . $end_date . '"';
		}

		$active_filter = '1';
		if ($active == 'all' OR $active == 'false')
		{
			$active_filter = '0,1';
		}

		$joins = '';
		$general_filter = '';
		if (isset($media_type) AND is_numeric($media_type))
		{
			$joins = 'INNER JOIN '.$table_prefix.'media AS m ON m.incident_id = i.id';
			$general_filter = ' AND m.media_type IN ('. $media_type	 .')';
		}

		$graph_data = array();
		$all_graphs = array();

		$all_graphs['0'] = array();
		$all_graphs['0']['label'] = 'All Categories';
		$query_text = 'SELECT UNIX_TIMESTAMP(' . $select_date_text . ') AS time,
					   COUNT(*) AS number
					   FROM '.$table_prefix.'incident AS i ' . $joins . '
					   WHERE incident_active IN (' . $active_filter .')' .
		$general_filter .'
					   GROUP BY ' . $groupby_date_text;
		$query = $db->query($query_text);
		$all_graphs['0']['data'] = array();
		foreach ( $query as $month_count )
		{
			array_push($all_graphs['0']['data'],
				array($month_count->time * 1000, $month_count->number));
		}
		$all_graphs['0']['color'] = '#990000';

		$query_text = 'SELECT category_id, category_title, category_color, UNIX_TIMESTAMP(' . $select_date_text . ')
							AS time, COUNT(*) AS number
								FROM '.$table_prefix.'incident AS i
							INNER JOIN '.$table_prefix.'incident_category AS ic ON ic.incident_id = i.id
							INNER JOIN '.$table_prefix.'category AS c ON ic.category_id = c.id
							' . $joins . '
							WHERE incident_active IN (' . $active_filter . ')
								  ' . $general_filter . '
							GROUP BY ' . $groupby_date_text . ', category_id ';
		$query = $db->query($query_text);
		foreach ( $query as $month_count )
		{
			$category_id = $month_count->category_id;
			if ( ! isset($all_graphs[$category_id]))
			{
				$all_graphs[$category_id] = array();
				$all_graphs[$category_id]['label'] = $month_count->category_title;
				$all_graphs[$category_id]['color'] = '#'. $month_count->category_color;
				$all_graphs[$category_id]['data'] = array();
			}
			array_push($all_graphs[$category_id]['data'],
				array($month_count->time * 1000, $month_count->number));
		}
		$graphs = json_encode($all_graphs);
		return $graphs;
	}
}
