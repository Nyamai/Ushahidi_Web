<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Reports Controller.
 * This controller will take care of adding and editing reports in the Admin section.
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author     Ushahidi Team <team@ushahidi.com> 
 * @package    Ushahidi - http://source.ushahididev.com
 * @module     Admin Reports Controller  
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license    http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL) 
 */

class Reports_Controller extends Admin_Controller
{
	function __construct()
	{
		parent::__construct();
	
		$this->template->this_page = 'reports';
	}
	
	
	/**
	* Lists the reports.
    * @param int $page
    */
	function index($page = 1)
	{
		$this->template->content = new View('admin/reports');
		$this->template->content->title = Kohana::lang('ui_admin.reports');
		
		// Status Tab Selection
		$status = strtolower($this->input->get('status'));
		if ($status == "a")
		{
			$filter = 'incident_active = 0';
		}
		elseif ($status == "v")
		{
			$filter = 'incident_verified = 0';
		}
		else
		{
			$filter = "1=1";
		}
		
		$filter .= ($this->input->get('k')) ? 
			" AND (".report::get_searchstring($this->input->get('k')).")" : "";
			

		$form_error = FALSE;
		$form_saved = FALSE;
		$form_action = "";
		
		// Has the form been submitted?
	    if ($_POST)
	    {
			$post = Validation::factory($_POST);
			
	         //  Add some filters
	        $post->pre_filter('trim', TRUE);

	        // Add some rules, the input field, followed by a list of checks, carried out in order
			$post->add_rules('action','required', 'alpha', 'length[1,1]');
			$post->add_rules('incident_id.*','required','numeric');
			
			if ($post->validate())
	        {
				foreach($post->incident_id as $item)
				{
					$update = ORM::factory('incident',$item);
					$form_action = $update->action($post->action);
				}
				
				$form_saved = TRUE;
			}
			else
			{
				$form_error = TRUE;
			}
		}
		
		// Pagination
		$pagination = new Pagination(array(
			'query_string'    => 'page',
			'items_per_page' => (int) Kohana::config('settings.items_per_page_admin'),
			'total_items'    => ORM::factory('incident')
				->where($filter)
				->join('location', 'incident.location_id', 'location.id','INNER')
				->count_all()
		));
		
		// Get All Reports
		$incidents = ORM::factory('incident')
			->where($filter)->orderby('incident_dateadd', 'desc')
			->join('location', 'incident.location_id', 'location.id','INNER')
			->find_all((int) Kohana::config('settings.items_per_page_admin'), $pagination->sql_offset);
				
		$this->template->content->incidents = $incidents;
		$this->template->content->pagination = $pagination;
		$this->template->content->form_error = $form_error;
		$this->template->content->form_saved = $form_saved;
		$this->template->content->form_action = $form_action;
		
		// Total Reports
		$this->template->content->total_items = $pagination->total_items;
		
		// Status Tab
		$this->template->content->status = $status;
		
		// Javascript Header
		$this->template->js = new View('admin/reports_js');		
	}
	
	
	/**
	* Create / Edit a report
    * @param bool|int $id The id no. of the report
    * @param bool|string $saved
    */
	function edit($id = false, $saved = false)
	{
		$this->template->content = new View('admin/reports_edit');
		$this->template->content->title = Kohana::lang('ui_admin.create_report');
		
		// Locale (Language) Array
		$this->template->content->locale_array = Kohana::config('locale.all_languages');
		
        // Create Categories
        $this->template->content->categories = $this->_get_categories();	
		$this->template->content->new_categories_form = $this->_new_categories_form_arr();
		 
		// Time formatting
	    $this->template->content->hour_array = $this->_hour_array();
	    $this->template->content->minute_array = $this->_minute_array();
        $this->template->content->ampm_array = $this->_ampm_array();
		
		//GET available custom forms
		$this->template->content->forms = report::get_custom_forms();
		
		// Build Form and Add Default Values
		$form = report::build_form($id, TRUE);
		
		// Copy the form as errors, so the errors will be stored with keys corresponding to the form field names
	    $errors = report::build_form();
		$form_error = FALSE;
		$form_saved = ($saved == 'saved') ? TRUE : FALSE;
		
		// Retrieve thumbnail photos (if edit);
		$this->template->content->thumbs = ($id) ? 
			ORM::factory('incident', $id)->media : "";
		
		// Are we creating this report from SMS/Email/Twitter?
		if ( ! $id AND $message_id = $this->input->get('mid'))
		{
			$message = ORM::factory('message', $message_id);
			$form = report::messages($form, $message);
			
			// Retrieve Last 5 Messages From the sender
			$this->template->content->last_5_messages = $message
				->where('reporter_id', $message->reporter_id)
				->orderby('message_date', 'desc')
				->limit(5)
				->find_all();
		}
		else
		{
			$this->template->content->last_5_messages = "";
		}
		
		// Are we creating this report from a NewsFeed?
		if ( ! $id AND $feed_item_id = $this->input->get('fid'))
		{
			$feed_item = ORM::factory('feed_item', $feed_item_id);
			$form = report::feeds($form, $feed_item);
		}
	
		// Has the form been submitted, if so, setup validation
		if ($post = array_merge($_POST,$_FILES))
        {
			$incident = ORM::factory('incident', $id);
			if ($incident->validate($post))
			{
				$incident->save_report($post);
				
				// SAVE AND CLOSE?
				if ($post->save == 1)		// Save but don't close
				{
					url::redirect('admin/reports/edit/'. $incident->id .'/saved');
				}
				else 						// Save and close
				{
					url::redirect('admin/reports/');
				}
			}
			else
			{
				// repopulate the form fields
				$form = arr::overwrite($form, $post->as_array());
				// populate the error fields, if any
				$errors = arr::overwrite($errors, $post->errors('report'));				
				$form_error = TRUE;
			}
		}
	
		$this->template->content->id = $id;
		$this->template->content->form = $form;
	    $this->template->content->errors = $errors;
		$this->template->content->form_error = $form_error;
		$this->template->content->form_saved = $form_saved;
		
		// Retrieve Custom Form Fields Structure
		$disp_custom_fields = report::get_custom_form_fields($id,$form['form_id'],false);
		$this->template->content->disp_custom_fields = $disp_custom_fields;
		
		// Retrieve Previous & Next Records
		$previous = ORM::factory('incident')->where('id < ', $id)->orderby('id','desc')->find();
		$previous_url = ($previous->loaded ? 
				url::base().'admin/reports/edit/'.$previous->id : 
				url::base().'admin/reports/');
		$next = ORM::factory('incident')->where('id > ', $id)->orderby('id','desc')->find();
		$next_url = ($next->loaded ? 
				url::base().'admin/reports/edit/'.$next->id : 
				url::base().'admin/reports/');
		$this->template->content->previous_url = $previous_url;
		$this->template->content->next_url = $next_url;
		
		// Javascript Header
		$this->template->map_enabled = TRUE;
        $this->template->colorpicker_enabled = TRUE;
		$this->template->treeview_enabled = TRUE;
		$this->template->js = new View('admin/reports_edit_js');
		$this->template->js->default_map = Kohana::config('settings.default_map');
		$this->template->js->default_zoom = Kohana::config('settings.default_zoom');
		$this->template->js->latitude = $form['latitude'];
		$this->template->js->longitude = $form['longitude'];
		
		// Inline Javascript
		$this->template->content->date_picker_js = $this->_date_picker_js();
        $this->template->content->color_picker_js = $this->_color_picker_js();
        $this->template->content->new_category_toggle_js = $this->_new_category_toggle_js();
	}


	/**
	* Download Reports in CSV format
    */
    
	function download()
	{
		$this->template->content = new View('admin/reports_download');
		$this->template->content->title = Kohana::lang('ui_admin.download_reports');
		
		$form = array(
			'data_point'      => '',
			'data_include'      => '',
			'from_date'    => '',
			'to_date'    => ''
		);
		$errors = $form;
		$form_error = FALSE;
		
		// check, has the form been submitted, if so, setup validation
	    if ($_POST)
	    {
            // Instantiate Validation, use $post, so we don't overwrite $_POST fields with our own things
			$post = Validation::factory($_POST);

	         //  Add some filters
	        $post->pre_filter('trim', TRUE);

	        // Add some rules, the input field, followed by a list of checks, carried out in order
	        $post->add_rules('data_point.*','required','numeric','between[1,4]');
			$post->add_rules('data_include.*','numeric','between[1,5]');
			$post->add_rules('from_date','date_mmddyyyy');
			$post->add_rules('to_date','date_mmddyyyy');
			
			// Validate the report dates, if included in report filter
			if ( ! empty($_POST['from_date']) OR ! empty($_POST['to_date']))
			{	
				// Valid FROM Date?
				if (empty($_POST['from_date']) OR (strtotime($_POST['from_date']) > strtotime("today"))) {
					$post->add_error('from_date','range');
				}
				
				// Valid TO date?
				if (empty($_POST['to_date']) OR (strtotime($_POST['to_date']) > strtotime("today"))) {
					$post->add_error('to_date','range');
				}
				
				// TO Date not greater than FROM Date?
				if (strtotime($_POST['from_date']) > strtotime($_POST['to_date'])) {
					$post->add_error('to_date','range_greater');
				}
			}
			
			// Test to see if things passed the rule checks
	        if ($post->validate())
	        {
				// Add Filters
				$filter = " ( 1=1";
				// Report Type Filter
				foreach($post->data_point as $item)
				{
					if ($item == 1) {
						$filter .= " OR incident_active = 1 ";
					}
					if ($item == 2) {
						$filter .= " OR incident_verified = 1 ";
					}
					if ($item == 3) {
						$filter .= " OR incident_active = 0 ";
					}
					if ($item == 4) {
						$filter .= " OR incident_verified = 0 ";
					}
				}
				$filter .= ") ";
				
				// Report Date Filter
				if ( ! empty($post->from_date) AND ! empty($post->to_date)) 
				{
					$filter .= " AND ( incident_date >= '" . date("Y-m-d H:i:s",strtotime($post->from_date)) . "' AND incident_date <= '" . date("Y-m-d H:i:s",strtotime($post->to_date)) . "' ) ";					
				}
				
				// Retrieve reports
				$incidents = ORM::factory('incident')->where($filter)->orderby('incident_dateadd', 'desc')->find_all();
				
				// Column Titles
				$report_csv = "#,INCIDENT TITLE,INCIDENT DATE";
				foreach($post->data_include as $item)
				{
					if ($item == 1) {
						$report_csv .= ",LOCATION";
					}
					if ($item == 2) {
						$report_csv .= ",DESCRIPTION";
					}
					if ($item == 3) {
						$report_csv .= ",CATEGORY";
                                        }
                                        if ($item == 4) {
                                                $report_csv .= ",LATITUDE";
                                        }
                                        if($item == 5) {
                                                $report_csv .= ",LONGITUDE";
                                        }
				}
				$report_csv .= ",APPROVED,VERIFIED";
				$report_csv .= "\n";
				
				foreach ($incidents as $incident)
				{
					$report_csv .= '"'.$incident->id.'",';
					$report_csv .= '"'.htmlspecialchars($incident->incident_title).'",';
					$report_csv .= '"'.$incident->incident_date.'"';
					
					foreach($post->data_include as $item)
					{
						if ($item == 1) {
                                                        $report_csv .= ',"'.htmlspecialchars($incident->location->location_name).'"';
						}
						if ($item == 2) {
							$report_csv .= ',"'.htmlspecialchars($incident->incident_description).'"';
						}
						if ($item == 3) {
							$report_csv .= ',"';
							foreach($incident->incident_category as $category) 
							{ 
								$report_csv .= htmlspecialchars($category->category->category_title) . ", ";
							}
							$report_csv .= '"';
                                                }
                                                if ($item == 4) {
                                                        $report_csv .= ',"'.htmlspecialchars($incident->location->latitude).'"';
                                                }
                                                if ($item == 5) {
                                                        $report_csv .= ',"'.htmlspecialchars($incident->location->longitude).'"';
                                                }
					}
					if ($incident->incident_active) {
						$report_csv .= ",YES";
					}
					else
					{
						$report_csv .= ",NO";
					}
					if ($incident->incident_verified) {
						$report_csv .= ",YES";
					}
					else
					{
						$report_csv .= ",NO";
					}
					$report_csv .= "\n";
				}
				
				// Output to browser
				header("Content-type: text/x-csv");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header("Content-Disposition: attachment; filename=" . time() . ".csv");
				header("Content-Length: " . strlen($report_csv));
				echo $report_csv;
				exit;
				
	        }
			// No! We have validation errors, we need to show the form again, with the errors
	        else   
			{
	            // repopulate the form fields
	            $form = arr::overwrite($form, $post->as_array());

	            // populate the error fields, if any
	            $errors = arr::overwrite($errors, $post->errors('report'));
				$form_error = TRUE;
	        }
		}
		
		$this->template->content->form = $form;
	    $this->template->content->errors = $errors;
		$this->template->content->form_error = $form_error;
		
		// Javascript Header
		$this->template->js = new View('admin/reports_download_js');
		$this->template->js->calendar_img = url::base() . "media/img/icon-calendar.gif";
	}
	
	
    public function upload()
	{
		if($_SERVER['REQUEST_METHOD'] == 'GET') {
			$this->template->content = new View('admin/reports_upload');
			$this->template->content->title = 'Upload Reports';
			$this->template->content->form_error = false;
		}
		if($_SERVER['REQUEST_METHOD']=='POST') {
			$errors = array();
			$notices = array();
			 if( ! $_FILES['csvfile']['error']) {
			if(file_exists($_FILES['csvfile']['tmp_name'])) {
			if($filehandle = fopen($_FILES['csvfile']['tmp_name'], 'r')) {
			$importer = new ReportsImporter;
			if($importer->import($filehandle)) {
			$this->template->content = new View('admin/reports_upload_success');
			$this->template->content->title = 'Upload Reports';
			$this->template->content->rowcount = $importer->totalrows;
			$this->template->content->imported = $importer->importedrows;
			$this->template->content->notices = $importer->notices;
			}
			else {
			$errors = $importer->errors;
			}
			}
			else {
			$errors[] = Kohana::lang('ui_admin.file_open_error');
			}
			} // file exists?
			else {
			$errors[] = Kohana::lang('ui_admin.file_not_found_upload');
			}
			} // upload errors?
			else {
			$errors[] = $_FILES['csvfile']['error'];
			}
			if(count($errors)) {
				$this->template->content = new View('admin/reports_upload');
				$this->template->content->title = Kohana::lang('ui_admin.upload_reports');		
				$this->template->content->errors = $errors;
				$this->template->content->form_error = 1;
			}
		} // _POST
	}


    /**
    * Save newly added dynamic categories
    */
	function save_category()
	{
		$this->auto_render = FALSE;
		$this->template = "";
		
		// check, has the form been submitted, if so, setup validation
	    if ($_POST)
	    {
	        // Instantiate Validation, use $post, so we don't overwrite $_POST fields with our own things
			$post = Validation::factory($_POST);
			
	         //  Add some filters
	        $post->pre_filter('trim', TRUE);

	        // Add some rules, the input field, followed by a list of checks, carried out in order
			$post->add_rules('category_title','required', 'length[3,200]');
			$post->add_rules('category_description','required');
			$post->add_rules('category_color','required', 'length[6,6]');
			
			
			// Test to see if things passed the rule checks
	        if ($post->validate())
	        {
				// SAVE Category
				$category = new Category_Model();
				$category->category_title = $post->category_title;
				$category->category_description = $post->category_description;
				$category->category_color = $post->category_color;
				$category->save();
				$form_saved = TRUE;

				echo json_encode(array("status"=>"saved", "id"=>$category->id));
	        }
            
	        else
	        
			{
	            echo json_encode(array("status"=>"error"));
	        }
	    }
		else
		{
			echo json_encode(array("status"=>"error"));
		}
	}

	/** 
    * Delete Photo 
    * @param int $id The unique id of the photo to be deleted
    */
	public function deletePhoto ( $id )
	{
		$this->auto_render = FALSE;
		$this->template = "";
		
		if ( $id )
		{
			$photo = ORM::factory('media', $id);
			$photo_large = $photo->media_link;
			$photo_thumb = $photo->media_thumb;
			
			// Delete Files from Directory
			if ( ! empty($photo_large))
				unlink(Kohana::config('upload.directory', TRUE) . $photo_large);
			if ( ! empty($photo_thumb))
				unlink(Kohana::config('upload.directory', TRUE) . $photo_thumb);

			// Finally Remove from DB
			$photo->delete();
		}
	}
	
	
	// Return thumbnail photos
	private function _get_thumbnails( $id )
	{
		$incident = ORM::factory('incident', $id);
		
		if ( $id )
		{
			$incident = ORM::factory('incident', $id);
			
			return $incident;
		
		}
		return "0";
	}
	
    private function _get_categories()
    {
		$categories = ORM::factory('category')
			->where('category_visible', '1')
			->where('parent_id', '0')
			->orderby('category_title', 'ASC')
			->find_all();

		return $categories;		
	}

    // Dynamic categories form fields
    private function _new_categories_form_arr()
    {
        return array
        (
            'category_name' => '',
            'category_description' => '',
            'category_color' => '',
        );
    }

    // Time functions
    private function _hour_array()
    {
        for ($i=1; $i <= 12 ; $i++) 
        { 
		    $hour_array[sprintf("%02d", $i)] = sprintf("%02d", $i); 	// Add Leading Zero
		}
	    return $hour_array;	
	}
									
	private function _minute_array()
	{								
		for ($j=0; $j <= 59 ; $j++) 
		{ 
			$minute_array[sprintf("%02d", $j)] = sprintf("%02d", $j);	// Add Leading Zero
		}
		
		return $minute_array;
	}
	
	private function _ampm_array()
	{								
	    return $ampm_array = array('pm'=>Kohana::lang('ui_admin.pm'),'am'=>Kohana::lang('ui_admin.am'));
	}
	
	// Javascript functions
	 private function _color_picker_js()
    {
     return "<script type=\"text/javascript\">
				$(document).ready(function() {
                $('#category_color').ColorPicker({
                        onSubmit: function(hsb, hex, rgb) {
                            $('#category_color').val(hex);
                        },
                        onChange: function(hsb, hex, rgb) {
                            $('#category_color').val(hex);
                        },
                        onBeforeShow: function () {
                            $(this).ColorPickerSetColor(this.value);
                        }
                    })
                .bind('keyup', function(){
                    $(this).ColorPickerSetColor(this.value);
                });
				});
            </script>";
    }
    
    private function _date_picker_js() 
    {
        return "<script type=\"text/javascript\">
				$(document).ready(function() {
				$(\"#incident_date\").datepicker({ 
				showOn: \"both\", 
				buttonImage: \"" . url::base() . "media/img/icon-calendar.gif\", 
				buttonImageOnly: true 
				});
				});
			</script>";	
    }
    

    private function _new_category_toggle_js()
    {
        return "<script type=\"text/javascript\">
			    $(document).ready(function() {
			    $('a#category_toggle').click(function() {
			    $('#category_add').toggle(400);
			    return false;
				});
				});
			</script>";
    }


	/**
	 * Checks if translation for this report & locale exists
     * @param Validation $post $_POST variable with validation rules 
	 * @param int $iid The unique incident_id of the original report
	 */
	public function translate_exists_chk(Validation $post)
	{
		// If add->rules validation found any errors, get me out of here!
		if (array_key_exists('locale', $post->errors()))
			return;
		
		$iid = $_GET['iid'];
		if (empty($iid)) {
			$iid = 0;
		}
		$translate = ORM::factory('incident_lang')->where('incident_id',$iid)->where('locale',$post->locale)->find();
		if ($translate->loaded == true) {
			$post->add_error( 'locale', 'exists');		
		// Not found
		} else {
			return;
		}
	}


	/**
	 * Ajax call to update Incident Reporting Form
	 */
	public function switch_form()
    {
		$this->template = "";
		$this->auto_render = FALSE;
		
		isset($_POST['form_id']) ? $form_id = $_POST['form_id'] : $form_id = "1";
		isset($_POST['incident_id']) ? $incident_id = $_POST['incident_id'] : $incident_id = "";
			
		$html = "";
		$fields_array = array();		
		$custom_form = ORM::factory('form', $form_id)->orderby('field_position','asc');
		
		foreach ($custom_form->form_field as $custom_formfield)
		{
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
			
			// Load Data, if Any
			foreach ($custom_formfield->form_response as $form_response)
			{
				if ($form_response->incident_id = $incident_id)
				{
					$fields_array[$custom_formfield->id]['field_response'] = $form_response->form_response;
				}
			}
		}
		
		foreach ($fields_array as $field_property)
		{
			$html .= "<div class=\"row\">";
			$html .= "<h4>" . $field_property['field_name'] . "</h4>";
			if ($field_property['field_type'] == 1)
			{ // Text Field
				// Is this a date field?
				if ($field_property['field_isdate'] == 1)
				{
					$html .= form::input('custom_field['.$field_property['field_id'].']', $field_property['field_response'],
						' id="custom_field_'.$field_property['field_id'].'" class="text"');
					$html .= "<script type=\"text/javascript\">
							$(document).ready(function() {
							$(\"#custom_field_".$field_property['field_id']."\").datepicker({ 
							showOn: \"both\", 
							buttonImage: \"" . url::base() . "media/img/icon-calendar.gif\", 
							buttonImageOnly: true 
							});
							});
						</script>";
				}
				else
				{
					$html .= form::input('custom_field['.$field_property['field_id'].']', $field_property['field_response'],
						' id="custom_field_'.$field_property['field_id'].'" class="text custom_text"');
				}
			}
			elseif ($field_property['field_type'] == 2)
			{ // TextArea Field
				$html .= form::textarea('custom_field['.$field_property['field_id'].']',
					$field_property['field_response'], ' class="custom_text" rows="3"');
			}
			$html .= "</div>";
		}
		
		echo json_encode(array("status"=>"success", "response"=>$html));
    }
}
