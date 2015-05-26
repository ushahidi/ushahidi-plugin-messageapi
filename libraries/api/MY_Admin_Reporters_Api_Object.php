<?php defined('SYSPATH') or die('No direct script access.');
/**
 * This class handles GET request for Reporters via the API.
 *
 * @version 1 Sara-Jayne Terp, using API code by Emmanuel Kala 2010-10-25
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi - http://source.ushahididev.com
 * @module     API Controller
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license    http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL)
 */

require_once Kohana::find_file('libraries/api', Kohana::config('config.extension_prefix').'Reporters_Api_Object');

class Admin_Reporters_Api_Object extends Reporters_Api_Object {
	
	public function __construct($api_service)
	{
		parent::__construct($api_service);
	}

	/**
	 *  Handles admin report task requests submitted via the API service
	 */
	public function perform_task()
	{
		// Authenticate the user
		if ( ! $this->api_service->_login(TRUE))
		{
			$this->set_error_message($this->response(2));
			return;
		}

		// by request
		if ($this->api_service->verify_array_index($this->request, 'by'))
		{
			$this->_check_optional_parameters();
			
			$this->by = $this->request['by'];

			switch ($this->by)
			{
				case "all" :
					$where = array();
					$this->response_data = $this->_get_reporters($where);
					break;

				case "serviceid":
					if ( ! $this->api_service->verify_array_index($this->request, 'id'))
					{
						$this->set_error_message(array(
							"error" => $this->api_service->get_error_msg(001, 'id')
						));
					}
					
					$where = array('i.service_id = '.$this->check_id_value($this->request['id']));
					$this->response_data = $this->_get_reporters($where);
				break;
				
				case "levelid":
					if ( ! $this->api_service->verify_array_index($this->request, 'id'))
					{
						$this->set_error_message(array(
							"error" => $this->api_service->get_error_msg(001, 'id')
						));
					}
					
					$where = array('i.level_id = '.$this->check_id_value($this->request['id']));
					$this->response_data = $this->_get_reporters($where);
				break;
				
				default :
					$this->set_error_message(array(
						"error" => $this->api_service->get_error_msg(002)
					));
			}
			return;
		}

		// action request
		else if ($this->api_service->verify_array_index($this->request, 'action'))
		{
			$this->reporter_action();
			return;
		}

		// id request (ask for a specific message)
		else if ($this->api_service->verify_array_index($this->request, 'id'))
		{

			$this->response_data = $this->_get_reporter($this->check_id_value($this->request['id']));
			return;
		}

		// Everything else
		else
		{
			$this->set_error_message(array("error" => $this->api_service->get_error_msg(001, 'by or action')));
			return;
		}
	}

	/**
	 * Handles report actions performed via the API service
	 */
	public function reporter_action()
	{
		$action = '';
		// Will hold the report action
		$message_id = 0;
		// Will hold the ID of the incident/report to be acted upon

		// Authenticate the user
		if ( ! $this->api_service->_login())
		{
			$this->set_error_message($this->response(2));
			return;
		}

		// Check if the action has been specified
		if ( ! $this->api_service->verify_array_index($this->request, 'action'))
		{
			$this->set_error_message(array(
				"error" => $this->api_service->get_error_msg(001, 'action')
			));

			return;
		}
		else
		{
			$action = $this->request['action'];
		}

		// Route report actions to their various handlers
		switch ($action)
		{
			// Add new report
			case "add" :
				$this->_add_reporter();
				break;

			// Edit report
			case "edit" :
				$this->_edit_reporter();
				break;

			// Delete report
			case "delete" :
				$this->_delete_reporter();
				break;

			default :
				$this->set_error_message(array(
					"error" => $this->api_service->get_error_msg(002)
				));
		}
	}


	/**
	 * Add service
	 *
	 * @return array
	 */
	private function _add_reporter()
	{
		$reporter_id =  $this->_submit_reporter();
		//$this->response_data = $this->response($ret_value, $this->error_messages);
	}



	/**
	 * Edit existing service
	 *
	 * @return array
	 */
	private function _edit_reporter()
	{

		$form = array(
			'reporter_id' => '', 
			'service_id' => '',
			'service_account' => '',			
			'level_id' => '',
			'reporter_first' => '',
			'reporter_last' => '',
			'reporter_email' => '',
			'reporter_phone' => '',
			'reporter_ip' => '',
			'reporter_date' => '',
			'location_id' => '',
			'location_name' => '',
			'latitude' => '',
			'longitude' => ''
			);

		$errors = $form;
		$ret_value = 0;
 
		if ($_POST)
		{
			$post = Validation::factory($_POST);

			//  Add some filters
			$post->pre_filter('trim', TRUE);

			// Add some rules, the input field, followed by a list
			// of checks, carried out in order
			$post->add_rules('reporter_id', 'required', 'numeric');
			$post->add_rules('level_id','required','numeric');

			if ($post->validate())
			{
				$reporter_id = $post->reporter_id;
				$reporter = new Reporter_Model($reporter_id);

				if ($post->location_id <> '')
				{
					//Check location_name, latitude, longitude
					$location = new Location_Model($post->location_id);
					$location->location_name = $post->location_name;
					$location->latitude = $post->latitude;
					$location->longitude = $post->longitude;
					$location->location_date = date("Y-m-d H:i:s",time());
					$location->save();
				}
				else
				{
					//Add new location, then allocate id to reporter
					$location = new Location_Model();
					$location->location_name = $post->location_name;
					$location->latitude = $post->latitude;
					$location->longitude = $post->longitude;
					$location->location_date = date("Y-m-d H:i:s",time());
					$location->save();
				}

				//Save reporter
				$reporter->service_id = $post->service_id;
				$reporter->service_account = $post->service_account;
				$reporter->level_id = $post->level_id;
				$reporter->reporter_first = $post->reporter_first;
				$reporter->reporter_last = $post->reporter_last;
				$reporter->reporter_email = $post->reporter_email;
				$reporter->reporter_phone = $post->reporter_phone;
				$reporter->reporter_ip = $post->reporter_ip;
				$reporter->reporter_date = $post->reporter_date;
				$reporter->save();
			}
			else
			{
				//TODO i18nize the string
				$this->error_messages .= "Reporter ID is required.";
				$ret_value = 1;
			}
		}
		else
		{
			$ret_value = 3;
		}

		// Set the reponse info to be sent back to client
		$this->response_data = $this->response($ret_value, $this->error_messages);

	}



	/**
	 * Submit reporter details
	 *
	 * @return int
	 */
	private function _submit_reporter()
	{

		// setup and initialize form field names
		$form = array(
			'service_id' => '',
			'service_account' => '',			
			'level_id' => '',
			'reporter_first' => '',
			'reporter_last' => '',
			'reporter_email' => '',
			'reporter_phone' => '',
			'reporter_ip' => '',
			'reporter_date' => '',
			'location_id' => '',
			'location_name' => '',
			'latitude' => '',
			'longitude' => ''
			);

		// copy the form as errors, so the errors will be stored
		//with keys corresponding to the form field names
		$errors = $form;
		$ret_value = 0;
		$reporter_id = 0;

		// check, has the form been submitted, if so, setup validation
		if ($_POST)
		{
			// Instantiate Validation, use $post, so we don't
			//overwrite $_POST fields with our own things
			//  Add some filters
			$post = Validation::factory($_POST);
			$post->pre_filter('trim', TRUE);

			// Test to see if things passed the rule checks
			if ($post->validate(FALSE))
			{
				// Save Action
				$reporter = new Reporter_Model();
				//Check location
				if ($post->location_id <> '')
				{
					//Check location_name, latitude, longitude
					$location = new Location_Model($post->location_id);
					$location->location_name = $post->location_name;
					$location->latitude = $post->latitude;
					$location->longitude = $post->longitude;
					$location->location_date = date("Y-m-d H:i:s",time());
					$location->save();
				}
				else
				{
					//Add new location, then allocate id to reporter
					$location = new Location_Model();
					$location->location_name = $post->location_name;
					$location->latitude = $post->latitude;
					$location->longitude = $post->longitude;
					$location->location_date = date("Y-m-d H:i:s",time());
					$location->save();
				}

				//Save reporter
				$reporter->service_id = $post->service_id;
				$reporter->service_account = $post->service_account;
				$reporter->level_id = $post->level_id;
				$reporter->reporter_first = $post->reporter_first;
				$reporter->reporter_last = $post->reporter_last;
				$reporter->reporter_email = $post->reporter_email;
				$reporter->reporter_phone = $post->reporter_phone;
				$reporter->reporter_ip = $post->reporter_ip;
				$reporter->reporter_date = $post->reporter_date;
				$reporter->location_id = $location->id;
				$reporter_id = $reporter->save();

				// Empty $form array
				array_fill_keys($form, '');
			}
			// No! We have validation errors, we need to show the form again, with the errors
			else
			{
				// populate the error fields, if any
				$errors = arr::overwrite($errors, $post->errors('reporter'));
				foreach ($errors as $error_item => $error_description)
				{
					if (!is_array($error_description))
					{
						$this->error_messages .= $error_description;

						if ($error_description != end($errors))
						{
							$this->error_messages .= " - ";
						}
					}
				}

				$ret_value = 1;
				// Validation error
			}
		}
		else
		{
			$ret_value = 3;
			// Not sent by post method.
		}

		// Set the reponse info to be sent back to client
		$this->response_data = $this->response($ret_value, $this->error_messages, (string) $reporter_id);
	}



	/**
	 * Delete existing report
	 *
	 * @param int incident_id - the id of the report to be deleted.
	 */
	private function _delete_reporter()
	{
		$form = array('reporter_id' => '', );

		$ret_value = 0;
		// Return error value; start with no error

		$errors = $form;

		if ($_POST)
		{
			$post = Validation::factory($_POST);

			//  Add some filters
			$post->pre_filter('trim', TRUE);

			// Add some rules, the input field, followed by a list
			// of checks, carried out in order
			$post->add_rules('reporter_id', 'required', 'numeric');

			if ($post->validate(FALSE))
			{
				$message_id = $post->reporter_id;
				$update = new Message_Model($reporter_id);

				if ($update->loaded)
				{
					$update->delete();
				}
			}
			else
			{
				//TODO i18nize the string
				$this->error_messages .= "Reporter ID is required.";
				$ret_value = 1;
			}
		}
		else
		{
			$ret_value = 3;
		}

		// Set the reponse info to be sent back to client
		$this->response_data = $this->response($ret_value, $this->error_messages);

	}

}
