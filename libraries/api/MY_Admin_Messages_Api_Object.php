<?php defined('SYSPATH') or die('No direct script access.');
/**
 * This class handles GET request for Messages via the API.
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

require_once Kohana::find_file('libraries/api', Kohana::config('config.extension_prefix').'Messages_Api_Object');

class Admin_Messages_Api_Object extends Messages_Api_Object {
	
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
					$this->response_data = $this->_get_messages($where);
					break;

				case "incidentid":
					if ( ! $this->api_service->verify_array_index($this->request, 'id'))
					{
						$this->set_error_message(array(
							"error" => $this->api_service->get_error_msg(001, 'id')
						));
					}
					
					$where = array('i.incident_id = '.$this->check_id_value($this->request['id']));
					$this->response_data = $this->_get_messages($where);
				break;
				
				case "reporterid":
					if ( ! $this->api_service->verify_array_index($this->request, 'id'))
					{
						$this->set_error_message(array(
							"error" => $this->api_service->get_error_msg(001, 'id')
						));
					}
					
					$where = array('i.reporter_id = '.$this->check_id_value($this->request['id']));
					$this->response_data = $this->_get_messages($where);
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
			$this->message_action();
			return;
		}

		// id request (ask for a specific message)
		else if ($this->api_service->verify_array_index($this->request, 'id'))
		{

			$this->response_data = $this->_get_message($this->check_id_value($this->request['id']));
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
	public function message_action()
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
			// Delete report
			case "add" :
				$this->_add_message();
				break;

			// Edit report
			case "edit" :
				$this->_edit_message();
				break;

			// Delete report
			case "delete" :
				$this->_delete_message();
				break;

			default :
				$this->set_error_message(array(
					"error" => $this->api_service->get_error_msg(002)
				));
		}
	}


	/**
	 * Add new message
	 *
	 * @return array
	 */
	private function _add_message()
	{
 		$ret_value =  $this->_submit_message();
	}


	/**
	 * Edit existing message
	 *
	 * @return array
	 */
	private function _edit_message()
	{
		$form = array(
			'message_id' => '', 
			'parent_id' => '',
			'incident_id' => '',
			'reporter_id' => '',
			'service_messageid' => '',			
			'message_from' => '',			
			'message_to' => '',			
			'message_text' => '',			
			'message_detail' => '',			
			'message_type' => '',			
			'message_date' => '',			
			'message_level' => '',			
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
			$post->add_rules('message_id', 'required', 'numeric');
			$post->add_rules('message_text', 'required', 'length[3,80]');

			if ($post->validate())
			{
				$message_id = $post->message_id;
				$message = new Message_Model($message_id);
				$message->parent_id = $post->parent_id;
				$message->incident_id = $post->incident_id;
				$message->reporter_id = $post->reporter_id;
				$message->service_messageid = $post->service_messageid;
				$message->message_from = $post->message_from;
				$message->message_to = $post->message_to;
				$message->message = $post->message_text;
				$message->message_detail = $post->message_detail;
				$message->message_type = $post->message_type;
				$message->message_date = $post->message_date;
				$message->message_level = $post->message_level;
				$message->latitude = $post->latitude;
				$message->longitude = $post->longitude;
				$message->save();
			}
			else
			{
				//TODO i18nize the string
				$this->error_messages .= "Message ID is required.";
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
	private function _submit_message()
	{

		// setup and initialize form field names
		$form = array(
			'parent_id' => '',
			'incident_id' => '',
			'reporter_id' => '',
			'service_messageid' => '',			
			'message_from' => '',			
			'message_to' => '',			
			'message_text' => '',			
			'message_detail' => '',			
			'message_type' => '',			
			'message_date' => '',			
			'message_level' => '',			
			'latitude' => '',			
			'longitude' => ''		
			);

		// copy the form as errors, so the errors will be stored
		//with keys corresponding to the form field names
		$errors = $form;
		$ret_value = 0;
		$message_id = 0;

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
				$message = new Message_Model();
				$message->parent_id = $post->parent_id;
				$message->incident_id = $post->incident_id;
				$message->reporter_id = $post->reporter_id;
				$message->service_messageid = $post->service_messageid;
				$message->message_from = $post->message_from;
				$message->message_to = $post->message_to;
				$message->message = $post->message_text;
				$message->message_detail = $post->message_detail;
				$message->message_type = $post->message_type;
				$message->message_date = $post->message_date;
				$message->message_level = $post->message_level;
				$message->latitude = $post->latitude;
				$message->longitude = $post->longitude;
				$message_id = $message->save();

				// Empty $form array
				array_fill_keys($form, '');
			}
			// No! We have validation errors, we need to show the form again, with the errors
			else
			{
				// populate the error fields, if any
				$errors = arr::overwrite($errors, $post->errors('message'));
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
		$this->response_data = $this->response($ret_value, $this->error_messages, (string) $message_id);

	}



	/**
	 * Delete existing message
	 *
	 * @param int message_id - the id of the message to be deleted.
	 */
	private function _delete_message()
	{
		$form = array('message_id' => '', );

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
			$post->add_rules('message_id', 'required', 'numeric');

			if ($post->validate(FALSE))
			{
				$message_id = $post->message_id;
				$update = new Message_Model($message_id);

				if ($update->loaded)
				{
					$update->delete();
				}
			}
			else
			{
				//TODO i18nize the string
				$this->error_messages .= "Message ID is required.";
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
