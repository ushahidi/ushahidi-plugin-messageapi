<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Incidents_Api_Object
 *
 * This class handles messages activities via the API.
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

class Messages_Api_Object extends Api_Object_Core {
	/**
	 * Record sorting order ASC or DESC
	 * @var string
	 */
	protected $sort;

	/**
	 * Column name by which to order the records
	 * @var string
	 */
	protected $order_field;

	/**
	 * Constructor
	 */
	public function __construct($api_service)
	{
		parent::__construct($api_service);
	}

	/**
	 * Implementation of abstract method in parent
	 *
	 * Handles the API task parameters
	 */
	public function perform_task()
	{
	}


	/**
	 * Checks for optional parameters in the request and sets the values
	 * in the respective class members
	 */
	protected function _check_optional_parameters()
	{
		// Check if the sort parameter has been specified
		if ($this->api_service->verify_array_index($this->request, 'sort'))
		{
			$this->sort = ($this->request['sort'] == '0') ? 'ASC' : 'DESC';
		}
		else
		{
			$this->sort = 'DESC';
		}

		// Check if the limit parameter has been specified
		if ($this->api_service->verify_array_index($this->request, 'limit'))
		{
			$this->set_list_limit($this->request['limit']);
		}

		// Check if the orderfield parameter has been specified
		if ($this->api_service->verify_array_index($this->request, 'orderfield'))
		{
			switch ($this->request['orderfield'])
			{
				case "incidentid":
					$this->order_field = 'i.incident_id';
				break;

				case "reporterid":
					$this->order_field = 'i.reporter_id';
				break;

				case "messagedate":
					$this->order_field = 'i.message_date';
				break;

				default:
					$this->order_field = 'i.message_date';
			}
		}
		else
		{
			$this->order_field = 'i.message_date';
		}

	}

	/**
	 * Generic function to get reports by given set of parameters
	 *
	 * @param string $where SQL where clause
	 * @return string XML or JSON string
	 */
	public function _get_message($id)
	{
		// STEP 1.
		// Get the incidents
		$item = ORM::factory('message', $id);

		//No record found.
		if ($item->loaded == false)
		{
			return $this->response(4, $this->error_messages);
		}

		// Will hold the XML/JSON string to return
		$ret_json_or_xml = '';

		$json_message = array();

		//XML elements
		$xml = new XmlWriter();
		$xml->openMemory();
		$xml->startDocument('1.0', 'UTF-8');
		$xml->startElement('response');
		$xml->startElement('payload');
		$xml->writeElement('domain',$this->domain);
		$xml->startElement('message');

		// Build xml file
		$xml->writeElement('message_id',$item->id);
		$xml->writeElement('parent_id',$item->parent_id);
		$xml->writeElement('incident_id',$item->incident_id);
		$xml->writeElement('reporter_id',$item->reporter_id);
		$xml->writeElement('service_messageid',$item->service_messageid);
		$xml->writeElement('message_from',$item->message_from);
		$xml->writeElement('message_to',$item->message_to);
		$xml->writeElement('message_text',$item->message);
		$xml->writeElement('message_detail',$item->message_detail);
		$xml->writeElement('message_type',$item->message_type);
		$xml->writeElement('message_date',$item->message_date);
		$xml->writeElement('message_level',$item->message_level);
		$xml->writeElement('latitude',$item->latitude);
		$xml->writeElement('longitude',$item->longitude);

		// Check for response type
		if ($this->response_type == 'json' OR $this->response_type == 'jsonp')
		{
			$json_message[] = array(
				"message" => array(
					"message_id" => $item->id,
					"parent_id" => $item->parent_id,
					"incident_id" => $item->incident_id,
					"reporter_id" => $item->reporter_id,
					"service_messageid" => $item->service_messageid,
					"message_from" => $item->message_from,
					"message_to" => $item->message_to,
					"message_text" => $item->message,
					"message_detail" => $item->message_detail,
					"message_type" => $item->message_type,
					"message_date" => $item->message_date,
					"message_level" => $item->message_level,
					"locationlatitude" => $item->latitude,
					"locationlongitude" => $item->longitude
				)
			);
		}

		// Create the JSON array
		$data = array(
			"payload" => array(
				"domain" => $this->domain,
				"message" => $json_message
			),
			"error" => $this->api_service->get_error_msg(0)
		);

		if ($this->response_type == 'json' OR $this->response_type == 'jsonp')
		{
			return $this->array_as_json($data);

		}
		else
		{
			$xml->endElement(); //end incidents
			$xml->endElement(); // end payload
			$xml->startElement('error');
			$xml->writeElement('code',0);
			$xml->writeElement('message','No Error');
			$xml->endElement();//end error
			$xml->endElement(); // end response
			return $xml->outputMemory(true);
		}
	}


	/**
	 * Generic function to get reports by given set of parameters
	 *
	 * @param string $where SQL where clause
	 * @return string XML or JSON string
	 */
	public function _get_messages($where = array())
	{
		// STEP 1.
		// Get the incidents
		$items = Message_Model::get_messages($where, $this->list_limit, $this->order_field, $this->sort);

		//No record found.
		if ($items->count() == 0)
		{
			return $this->response(4, $this->error_messages);
		}

		// Records found - proceed

		// Set the no. of records returned
		$this->record_count = $items->count();

		// Will hold the XML/JSON string to return
		$ret_json_or_xml = '';

		$json_messages = array();

		//XML elements
		$xml = new XmlWriter();
		$xml->openMemory();
		$xml->startDocument('1.0', 'UTF-8');
		$xml->startElement('response');
		$xml->startElement('payload');
		$xml->writeElement('domain',$this->domain);
		$xml->startElement('messages');


		//
		// STEP 5.
		// Return XML
		//
		foreach ($items as $item)
		{
			// Build xml file
			$xml->startElement('message');

			$xml->writeElement('message_id',$item->message_id);
			$xml->writeElement('parent_id',$item->parent_id);
			$xml->writeElement('incident_id',$item->incident_id);
			$xml->writeElement('reporter_id',$item->reporter_id);
			$xml->writeElement('service_messageid',$item->service_messageid);
			$xml->writeElement('message_from',$item->message_from);
			$xml->writeElement('message_to',$item->message_to);
			$xml->writeElement('message_text',$item->message_text);
			$xml->writeElement('message_detail',$item->message_detail);
			$xml->writeElement('message_type',$item->message_type);
			$xml->writeElement('message_date',$item->message_date);
			$xml->writeElement('message_level',$item->message_level);
			$xml->writeElement('latitude',$item->latitude);
			$xml->writeElement('longitude',$item->longitude);
			$xml->endElement(); // End message

			// Check for response type
			if ($this->response_type == 'json' OR $this->response_type == 'jsonp')
			{
				$json_messages[] = array(
					"message" => array(
						"message_id" => $item->message_id,
						"parent_id" => $item->parent_id,
						"incident_id" => $item->incident_id,
						"reporter_id" => $item->reporter_id,
						"service_messageid" => $item->service_messageid,
						"message_from" => $item->message_from,
						"message_to" => $item->message_to,
						"message_text" => $item->message_text,
						"message_detail" => $item->message_detail,
						"message_type" => $item->message_type,
						"message_date" => $item->message_date,
						"message_level" => $item->message_level,
						"locationlatitude" => $item->latitude,
						"locationlongitude" => $item->longitude
					)
				);
			}
		}

		// Create the JSON array
		$data = array(
			"payload" => array(
				"domain" => $this->domain,
				"messages" => $json_messages
			),
			"error" => $this->api_service->get_error_msg(0)
		);

		if ($this->response_type == 'json' OR $this->response_type == 'jsonp')
		{
			return $this->array_as_json($data);

		}
		else
		{
			$xml->endElement(); //end incidents
			$xml->endElement(); // end payload
			$xml->startElement('error');
			$xml->writeElement('code',0);
			$xml->writeElement('message','No Error');
			$xml->endElement();//end error
			$xml->endElement(); // end response
			return $xml->outputMemory(true);
		}
	}


	/**
	 * Get messages within a certain lat,lon bounding box
	 *
	 * @param double $sw is the southwest lat,lon of the box
	 * @param double $ne is the northeast lat,lon of the box
	 * @param int $c is the categoryid
	 * @return string XML or JSON string containing the fetched incidents
	 */
	private function _get_messages_by_bounds($sw, $ne, $c)
	{
		// Break apart location variables, if necessary
		$southwest = array();
		if (isset($sw))
		{
			$southwest = explode(",",$sw);
		}

		$northeast = array();
		if (isset($ne))
		{
			$northeast = explode(",",$ne);
		}

		// To hold the parameters
		$params = array();
		if ( count($southwest) == 2 AND count($northeast) == 2 )
		{
			$lon_min = (float) $southwest[0];
			$lon_max = (float) $northeast[0];
			$lat_min = (float) $southwest[1];
			$lat_max = (float) $northeast[1];

			// Add parameters
			array_push($params,
				'l.latitude >= '.$lat_min,
				'l.latitude <= '.$lat_max,
				'l.longitude >= '.$lon_min,
				'l.longitude <= '.$lon_max
			);
		}

		return $this->_get_messages($params);
	}

	/**
	 * Gets the number of messages
	 *
	 * @param string response_type - XML or JSON
	 * @return string
	 */
	public function get_message_count()
	{
		$json_count = array();

		$this->query = 'SELECT COUNT(*) as count FROM '.$this->table_prefix.'message';

		$items = $this->db->query($this->query);

		foreach ($items as $item)
		{
			$count = $item->count;
			break;
		}

		if ($this->response_type == 'json' OR $this->response_type == 'jsonp')
		{
			$json_count[] = array("count" => $count);
		}
		else
		{
			$json_count['count'] = array("count" => $count);
			$this->replar[] = 'count';
		}

		// Create the JSON array
		$data = array(
				"payload" => array(
				"domain" => $this->domain,
				"count" => $json_count
			),
			"error" => $this->api_service->get_error_msg(0)
		);

		$this->response_data = ($this->response_type == 'json' OR $this->response_type == 'jsonp')
			? $this->array_as_json($data)
			: $this->array_as_xml($data, $this->replar);
	}

	/**
	 * Get an approximate geographic midpoint of al approved reports.
	 *
	 * @param string $response_type - XML or JSON
	 * @return string
	 */
	public function get_geographic_midpoint()
	{
		$json_latlon = array();

		$this->query = 'SELECT AVG( latitude ) AS avglat, AVG( longitude )
					AS avglon FROM '.$this->table_prefix.'location WHERE id IN
					(SELECT location_id FROM '.$this->table_prefix.'incident WHERE
					incident_active = 1)';

		$items = $this->db->query($this->query);

		foreach ($items as $item)
		{
			$latitude = $item->avglat;
			$longitude = $item->avglon;
			break;
		}

		if ($this->response_type == 'json' OR $this->response_type == 'jsonp')
		{
			$json_latlon[] = array(
				"latitude" => $latitude,
				"longitude" => $longitude
			);
		}
		else
		{
			$json_latlon['geographic_midpoint'] = array(
				"latitude" => $latitude,
				"longitude" => $longitude
			);

			$replar[] = 'geographic_midpoint';
		}

		// Create the JSON array
		$data = array(
			"payload" => array(
				"domain" => $this->domain,
				"geographic_midpoint" => $json_latlon
			),
			"error" => $this->api_service->get_error_msg(0)
		);

		// Return data
		$this->response_data =  ($this->response_type == 'json' OR $this->response_type == 'jsonp')
			? $this->array_as_json($data)
			: $this->array_as_xml($data, $replar);
	}

	protected function check_cordinate_value($cord)
	{
		return floatval($cord);
	}

}

