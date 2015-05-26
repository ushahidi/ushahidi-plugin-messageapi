<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Incidents_Api_Object
 *
 * This class handles reporters activities via the API.
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

class Reporters_Api_Object extends Api_Object_Core {
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
				case "serviceid":
					$this->order_field = 'i.service_id';
				break;

				case "levelid":
					$this->order_field = 'i.level_id';
				break;

				case "reporterdate":
					$this->order_field = 'i.reporter_date';
				break;

				default:
					$this->order_field = 'i.reporter_date';
			}
		}
		else
		{
			$this->order_field = 'i.reporter_date';
		}

	}

	/**
	 * Generic function to get reports by given set of parameters
	 *
	 * @param string $where SQL where clause
	 * @return string XML or JSON string
	 */
	public function _get_reporter($id)
	{
		// STEP 1.
		// Get the incidents
		$item = ORM::factory('reporter', $id);
		$loc = ORM::factory('location', $item->location_id);

		//No record found.
		if ($item->loaded == false)
		{
			return $this->response(4, $this->error_messages);
		}

		// Will hold the XML/JSON string to return
		$ret_json_or_xml = '';

		$json_reporter = array();

		//XML elements
		$xml = new XmlWriter();
		$xml->openMemory();
		$xml->startDocument('1.0', 'UTF-8');
		$xml->startElement('response');
		$xml->startElement('payload');
		$xml->writeElement('domain',$this->domain);
		$xml->startElement('reporter');

		// Build xml file
		$xml->writeElement('reporter_id',$item->id);
		$xml->writeElement('service_id',$item->service_id);
		$xml->writeElement('level_id',$item->level_id);
		$xml->writeElement('service_account',$item->service_account);
		$xml->writeElement('reporter_first',$item->reporter_first);
		$xml->writeElement('reporter_last',$item->reporter_last);
		$xml->writeElement('reporter_date',$item->reporter_date);
		$xml->writeElement('latitude',$loc->latitude);
		$xml->writeElement('longitude',$loc->longitude);

		// Check for response type
		if ($this->response_type == 'json' OR $this->response_type == 'jsonp')
		{
			$json_reporter[] = array(
				"reporter" => array(
					"reporter_id" => $item->id,
					"service_id" => $item->service_id,
					"level_id" => $item->level_id,
					"service_account" => $item->service_account,
					"reporter_first" => $item->reporter_first,
					"reporter_last" => $item->reporter_last,
					"reporter_date" => $item->reporter_date,
					"locationlatitude" => $loc->latitude,
					"locationlongitude" => $loc->longitude
				)
			);
		}

		// Create the JSON array
		$data = array(
			"payload" => array(
				"domain" => $this->domain,
				"reporter" => $json_reporter
			),
			"error" => $this->api_service->get_error_msg(0)
		);

		if ($this->response_type == 'json' OR $this->response_type == 'jsonp')
		{
			return $this->array_as_json($data);

		}
		else
		{
			$xml->endElement(); //end reporters
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
	public function _get_reporters($where = array())
	{
		// STEP 1.
		// Get the incidents
		$items = Reporter_Model::get_reporters($where, $this->list_limit, $this->order_field, $this->sort);

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

		$json_reporters = array();

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
			$xml->startElement('reporter');
			$xml->writeElement('reporter_id',$item->reporter_id);
			$xml->writeElement('service_id',$item->service_id);
			$xml->writeElement('level_id',$item->level_id);
			$xml->writeElement('service_account',$item->service_account);
			$xml->writeElement('reporter_first',$item->reporter_first);
			$xml->writeElement('reporter_last',$item->reporter_last);
			$xml->writeElement('reporter_date',$item->reporter_date);
			$xml->writeElement('latitude',$item->latitude);
			$xml->writeElement('longitude',$item->longitude);
			$xml->endElement(); // End message

			// Check for response type
			if ($this->response_type == 'json' OR $this->response_type == 'jsonp')
			{
				$json_reporters[] = array(
					"reporter" => array(
						"reporter_id" => $item->reporter_id,
						"service_id" => $item->service_id,
						"level_id" => $item->level_id,
						"service_account" => $item->service_account,
						"reporter_first" => $item->reporter_first,
						"reporter_last" => $item->reporter_last,
						"reporter_date" => $item->reporter_date,
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
				"reporters" => $json_reporters
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
	public function get_reporter_count()
	{
		$json_count = array();

		$this->query = 'SELECT COUNT(*) as count FROM '.$this->table_prefix.'reporter';

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

