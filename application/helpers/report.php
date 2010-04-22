<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Report helper class.
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author	   Ushahidi Team <team@ushahidi.com> 
 * @package	   Ushahidi - http://source.ushahididev.com
 * @module	   Report Helper
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license	   http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL)
 */
class report_Core {
	
	/**
	* Display delete confirmation message and form
	* @param int $id - $id of the incident/report
	* @param bool $defaults - included default values?
	* @return array form
	*/
	static function build_form($id = 0, $defaults = FALSE)
	{
		$form = array
		(
			'location_id'	   => '',
			'form_id'	   => '',
			'locale'		   => '',
			'incident_title'	  => '',
			'incident_description'	  => '',
			'incident_date'	 => '',
			'incident_hour'		 => '',
			'incident_minute'	   => '',
			'incident_ampm' => '',
			'latitude' => '',
			'longitude' => '',
			'location_name' => '',
			'country_id' => '',
			'categories' => array(),
			'incident_news' => array(),
			'incident_video' => array(),
			'incident_photo' => array(),
			'person_first' => '',
			'person_last' => '',
			'person_email' => '',
			'custom_field' => array(),
			'incident_active' => '',
			'incident_verified' => '',
			'incident_source' => '',
			'incident_information' => ''
		);

		// Initialize Default Values
		if ($defaults)
		{
			$form['locale'] = Kohana::config('locale.language');
			$form['latitude'] = Kohana::config('settings.default_lat');
			$form['longitude'] = Kohana::config('settings.default_lon');
			$form['country_id'] = Kohana::config('settings.default_country');
			$form['incident_date'] = date("m/d/Y",time());
			$form['incident_hour'] = date('g');
			$form['incident_minute'] = date('i');
			$form['incident_ampm'] = date('a');
			// initialize custom field array
			$form['custom_field'] = report::get_custom_form_fields($id,'',true);


			// Initialize Data for existing report
			if ($id)
			{
				$incident = ORM::factory('incident', $id);
				if ($incident->loaded)
				{
					// Retrieve Categories
					$categories = array();
					foreach($incident->incident_category as $category) 
					{ 
						$categories[] = $category->category_id;
					}

					// Retrieve Media
					$incident_news = array();
					$incident_video = array();
					$incident_photo = array();
					foreach($incident->media as $media) 
					{
						if ($media->media_type == 4)
						{
							$incident_news[] = $media->media_link;
						}
						elseif ($media->media_type == 2)
						{
							$incident_video[] = $media->media_link;
						}
						elseif ($media->media_type == 1)
						{
							$incident_photo[] = $media->media_link;
						}
					}

					// Combine Everything
					$form_existing = array
					(
						'location_id' => $incident->location->id,
						'form_id' => $incident->form_id,
						'locale' => $incident->locale,
						'incident_title' => $incident->incident_title,
						'incident_description' => $incident->incident_description,
						'incident_date' => date('m/d/Y', strtotime($incident->incident_date)),
						'incident_hour' => date('h', strtotime($incident->incident_date)),
						'incident_minute' => date('i', strtotime($incident->incident_date)),
						'incident_ampm' => date('A', strtotime($incident->incident_date)),
						'latitude' => $incident->location->latitude,
						'longitude' => $incident->location->longitude,
						'location_name' => $incident->location->location_name,
						'country_id' => $incident->location->country_id,
						'categories' => $categories,
						'incident_news' => $incident_news,
						'incident_video' => $incident_video,
						'incident_photo' => $incident_photo,
						'person_first' => $incident->incident_person->person_first,
						'person_last' => $incident->incident_person->person_last,
						'person_email' => $incident->incident_person->person_email,
						'custom_field' => report::get_custom_form_fields($id,$incident->form_id,true),
						'incident_active' => $incident->incident_active,
						'incident_verified' => $incident->incident_verified,
						'incident_source' => $incident->incident_source,
						'incident_information' => $incident->incident_information
					);

					// Merge To Form Array For Display
					$form = arr::overwrite($form, $form_existing);
				}
			}
		}
		
		return $form;
	}
	
	
	/**
	* Get available custom forms to display on report submit page
	* @return array forms
	*/
	static function get_custom_forms()
	{
		$forms = array();
		
		foreach (ORM::factory('form')->find_all() as $custom_forms)
		{
			$forms[$custom_forms->id] = $custom_forms->form_title;
		}
		
		return $forms;
	}
	
	
	/**
	 * Retrieve Custom Form Fields
	 * @param bool|int $incident_id The unique incident_id of the original report
	 * @param int $form_id The unique form_id. Uses default form (1), if none selected
	 * @param bool $field_names_only Whether or not to include just fields names, or field names + data
	 * @param bool $data_only Whether or not to include just data
	 * @return array - return the array of fields available for selected form
	 */
	static function get_custom_form_fields($incident_id = false, $form_id = 1, $data_only = false)
	{
		$fields_array = array();
		
		if (!$form_id)
		{
			$form_id = 1;
		}
		$custom_form = ORM::factory('form', $form_id)->orderby('field_position','asc');
		foreach ($custom_form->form_field as $custom_formfield)
		{
			if ($data_only)
			{ // Return Data Only
				$fields_array[$custom_formfield->id] = '';
				
				foreach ($custom_formfield->form_response as $form_response)
				{
					if ($form_response->incident_id == $incident_id)
					{
						$fields_array[$custom_formfield->id] = $form_response->form_response;
					}
				}
			}
			else
			{ // Return Field Structure
				$fields_array[$custom_formfield->id] = array(
					'field_id' => $custom_formfield->id,
					'field_name' => $custom_formfield->field_name,
					'field_type' => $custom_formfield->field_type,
					'field_required' => $custom_formfield->field_required,
					'field_maxlength' => $custom_formfield->field_maxlength,
					'field_height' => $custom_formfield->field_height,
					'field_width' => $custom_formfield->field_width,
					'field_isdate' => $custom_formfield->field_isdate,
					'field_response' => ''
					);
			}
		}
		
		return $fields_array;
	}


	/**
	 * Validate Custom Form Fields
	 * @param array $custom_fields Array
	 * @return bool Valid Values in Custom Form
	 */
	static function validate_custom_form_fields($custom_fields = array())
	{
		$custom_fields_error = "";
		
		foreach ($custom_fields as $field_id => $field_response)
		{
			// Get the parameters for this field
			$field_param = ORM::factory('form_field', $field_id);
			if ($field_param->loaded == true)
			{
				// Validate for required
				if ($field_param->field_required == 1 && $field_response == "")
				{
					return false;
				}

				// Validate for date
				if ($field_param->field_isdate == 1 && $field_response != "")
				{
					$myvalid = new Valid();
					return $myvalid->date_mmddyyyy($field_response);
				}
			}
		}
		return true;
	}

	/**
	 * Includes Messages (Twitter, Email, SMS etc in Report Submit Form),
	 * if we're creating a report from a message
	 * @param array $form - current form array before modification
	 * @param object $message - Message object if messages available
	 * @return array $form - Form array updated with messages
	 */
	static function messages($old_form = NULL, $message = NULL)
	{		
		if ($message->loaded AND $message->message_type == 1)
		{	
			$form = array();		
			// Has a report already been created for this Message?
			if ($message->incident_id != 0) {
				// Redirect to report
				url::redirect('admin/reports/edit/'. $message->incident_id);
			}

			$this->template->content->show_messages = true;
			$incident_description = $message->message;
			if (!empty($message->message_detail))
			{
				$incident_description .= "\n\n~~~~~~~~~~~~~~~~~~~~~~~~~\n\n"
					. $message->message_detail;
			}
			$form['incident_description'] = $incident_description;
			$form['incident_date'] = date('m/d/Y', strtotime($message->message_date));
			$form['incident_hour'] = date('h', strtotime($message->message_date));
			$form['incident_minute'] = date('i', strtotime($message->message_date));
			$form['incident_ampm'] = date('a', strtotime($message->message_date));
			$form['person_first'] = $message->reporter->reporter_first;
			$form['person_last'] = $message->reporter->reporter_last;
			
			return arr::overwrite($old_form, $form);
		}
		else
		{
			return $old_form;
		}
	}
	
	
	/**
	 * Includes Feed Item, if we're creating a report from a feed
	 * @param array $form - current form array before modification
	 * @param object $feed_item - Feed Item object if feeds availablea
	 * @return array $form - Form array updated with feed
	 */
	static function feeds($old_form = NULL, $feed_item = NULL)
	{		
		if ($feed_item->loaded)
		{				
			// Has a report already been created for this Feed item?
			if ($feed_item->incident_id != 0)
			{
				// Redirect to report
				url::redirect('admin/reports/edit/'. $feed_item->incident_id);
			}
			
			$form['incident_title'] = $feed_item->item_title;
			$form['incident_description'] = $feed_item->item_description;
			$form['incident_date'] = date('m/d/Y', strtotime($feed_item->item_date));
			$form['incident_hour'] = date('h', strtotime($feed_item->item_date));
			$form['incident_minute'] = date('i', strtotime($feed_item->item_date));
			$form['incident_ampm'] = date('a', strtotime($feed_item->item_date));
			
			// News Link
			$form['incident_news'][0] = $feed_item->item_link;
			
			// Does this newsfeed have a geolocation?
			if ($feed_item->location_id)
			{
				$form['location_id'] = $feed_item->location_id;
				$form['latitude'] = $feed_item->location->latitude;
				$form['longitude'] = $feed_item->location->longitude;
				$form['location_name'] = $feed_item->location->location_name;
			}
			
			return arr::overwrite($old_form, $form);
		}
		else
		{
			return $old_form;
		}
	}
	
	/**
	 * 
	 * @param string $keyword_raw
	 */
	static function get_searchstring($keyword_raw = NULL)
	{
		$or = '';
		$where_string = '';
		
		
		// Stop words that we won't search for
		// Add words as needed!!
		$stop_words = array('the', 'and', 'a', 'to', 'of', 'in', 'i', 'is', 'that', 'it', 
		'on', 'you', 'this', 'for', 'but', 'with', 'are', 'have', 'be', 
		'at', 'or', 'as', 'was', 'so', 'if', 'out', 'not');
		
		$keywords = explode(' ', $keyword_raw);
		if (is_array($keywords) AND ! empty($keywords)) {
			array_change_key_case($keywords, CASE_LOWER);
			$i = 0;
			foreach($keywords as $value) {
				if ( ! in_array($value,$stop_words) AND ! empty($value))
				{
					$chunk = mysql_real_escape_string($value);
					if ($i > 0) {
						$or = ' OR ';
					}
					$where_string = $where_string.$or."incident_title LIKE '%$chunk%' OR incident_description LIKE '%$chunk%'  OR location_name LIKE '%$chunk%'";
					$i++;
				}
			}
		}
		
		if ($where_string)
		{
			return $where_string;
		}
		else
		{
			return "1=1";
		}
	}
}