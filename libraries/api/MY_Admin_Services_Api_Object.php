<?php defined('SYSPATH') or die('No direct script access.');
/**
 * This class handles GET request for service via the API.
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

require_once Kohana::find_file('libraries/api', Kohana::config('config.extension_prefix').'Services_Api_Object');

class Admin_Services_Api_Object extends Services_Api_Object {
	
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
					$this->response_data = $this->_get_services($where);
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
			$this->service_action();
			return;
		}

		// id request (ask for a specific message)
		else if ($this->api_service->verify_array_index($this->request, 'id'))
		{

			$this->response_data = $this->_get_service($this->check_id_value($this->request['id']));
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
	public function service_action()
	{
		$action = '';
		// Will hold the report action
		$message_id = -1;
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
				$this->_add_service();
				break;

			// Edit eisting report
			case "edit" :
				$this->_edit_service();
				break;

			// Delete report
			case "delete" :
				$this->_delete_service();
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
	private function _add_service()
	{
		$ret_value =  $this->_submit_service();

		$this->response_data = $this->response($ret_value, $this->error_messages);
	}



	/**
	 * Edit existing service
	 *
	 * @return array
	 */
	private function _edit_service()
	{

		$form = array(
			'service_id' => '', 
			'service_name' => '',
			'service_description' => '',
			'service_url' => '',
			'service_api' => '',			
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
			$post->add_rules('service_id', 'required', 'numeric');

			if ($post->validate())
			{
				$service_id = $post->service_id;
				$service = new Service_Model($service_id);
				$service->service_name = $post->service_name;
				$service->service_description = $post->service_description;
				$service->service_url = $post->service_url;
				$service->service_api = $post->service_api;
				$service->save();
			}
			else
			{
				//TODO i18nize the string
				$this->error_messages .= "Service ID is required.";
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
	 * Submit service details
	 *
	 * @return int
	 */
	private function _submit_service()
	{

		// setup and initialize form field names
		$form = array(
			'service_name' => '',
			'service_description' => '',
			'service_url' => '',
			'service_api' => '',			
			);

		// copy the form as errors, so the errors will be stored
		//with keys corresponding to the form field names
		$errors = $form;
		$ret_value = 0;

		// check, has the form been submitted, if so, setup validation
		if ($_POST)
		{
			// Instantiate Validation, use $post, so we don't
			//overwrite $_POST fields with our own things
			//  Add some filters
			$post = Validation::factory($_POST);
			$post->pre_filter('trim', TRUE);

			// Test to see if things passed the rule checks
			$post->add_rules('service_name', 'required', 'length[3,80]');
			if ($post->validate(FALSE))
			{
				// Save Action
				$service = new Service_Model();
				$service->service_name = $post->service_name;
				$service->service_description = $post->service_description;
				$service->service_url = $post->service_url;
				$service->service_api = $post->service_api;
				$service->save();

				// Empty $form array
				array_fill_keys($form, '');
			}
			// No! We have validation errors, we need to show the form again, with the errors
			else
			{
				// populate the error fields, if any
				$errors = arr::overwrite($errors, $post->errors('service'));
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
		$this->response_data = $this->response($ret_value, $this->error_messages);

	}


	/**
	 * Delete existing report
	 *
	 * @param int incident_id - the id of the report to be deleted.
	 */
	private function _delete_service()
	{
		$form = array('service_id' => '', );

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
			$post->add_rules('service_id', 'required', 'numeric');
			$post->add_rules('service_name', 'required', 'length[3,80]');
			if ($post->validate(FALSE))
			{
				$service_id = $post->service_id;
				$service = new Service_Model($service_id);

				if ($service->loaded)
				{
					$service->delete();
				}
			}
			else
			{
				// Validation error. Populate the error fields, if any
				$errors = arr::overwrite($errors, $post->errors('service'));
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
