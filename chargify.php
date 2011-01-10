<?php 
/*
* Charigy Component
*
* A full library of Chargify API methods
* 
* @link         https://github.com/robmcvey/charigfy_cakephp
* @author       Rob McVey
* @version      0.3
* @license      MIT
*/

class ChargifyComponent extends Object {

	/*
	* Chargify account API key
	*
	* @var string
	* @access public
	*/
	var $apiKey = '';

	/**
	* Password for authentication (default is x) 
	* http://docs.chargify.com/api-authentication
	*
	* @var string
	* @access public
	*/
	var $password = '';

	/**
	*  Chargify site shared key
	*/
	var $siteSharedKey = ''; 

	/**
	* Your subdomain for Chargify
	*
	* @var string
	* @access public
	*/
	var $chargifyUrl = 'https://yourdomain.chargify.com/';

	/**
	*  REST format
	*  The default format. 'json' or 'xml' 
	*/
	var $restFormat = 'json';

	/**
	* @var array
	*/
	var $messages =  array();

	/**
	* Startup. Brpp. Brrrrpppppp. BRRPPPPPPPPPP!!!
	*/
	function initialize(&$controller){
	 $this->data = $controller->data;
	  $this->params = $controller->params;
	}

	/**
	* List Products
	* 
	* @return array of current prodcuts 
	*/
	function listProducts() {
	  $result = $this->sendRequest('products');
	    return $result;
	  }


	/**
	* List subscriptions
	* 
	* @return array of current subscriptions 
	*/
	function listSubscriptions() {
	  $result = $this->sendRequest('subscriptions');
	  return $result;
	}

	/**
	* Delete a subscription
	* 
	* @param int $id Chargify subscription id to cancel
	* @return array of canceled subscription
	*/
	function deleteSubscription($id) {
	  $result = $this->sendRequest('subscriptions/'.$id);
	 return $result;
	}


	/**
	* read one subscription
	* 
	* @param int $id Chargify subscription id to read
	* @return array of canceled subscription
	*/
	function readSubscription($id) {
		$result = $this->sendRequest('subscriptions/'.$id);
		return $result;
	}


	/**
	* create subscription
	* 
	* @param int $id Chargify subscription id to read
	* @return array of canceled subscription
	*/
	function createSubscription() {
		$data = $this->buildSubscription(array()); 
		$result = $this->sendRequest('subscriptions','POST',$data);
		return $result;
	}


	/**
	* update product - changes the product for a given subscritption id
	* 
	* @param int $id Chargify subscription id to read
	* @param $data mixed json/xml The new subscription, with new product
	* @return array of updated subscription
	* 
	*/
	function changeProduct($id,$data) {
		$result = $this->sendRequest('subscriptions/'.$id,'PUT',$data);
	  	return $result;
	}

	/**
	 * update subscription
	 * 
	 * @param int $id Chargify subscription id to read
	 * @param $subscription mixed JSON/XML subscription
	 * @return array of canceled subscription
	 */
	function updateSubscription($id,$subscription) { 
		$result = $this->sendRequest('subscriptions/'.$id,'PUT',$subscription);
		return $result;
	}

	/**
	 *  Make Hosted Page token
	 * @param $page_url string which hosted page?
	 * @param $SubId int subscription to update
	 */
	function makeSecretToken($page_url,$subId) {
		$msg = $page_url.'--'.$subId.'--'.$this->siteSharedKey;  
		$digest = sha1($msg);
		return substr($digest, 0, 10);
	}

	/**
	 *  Make a CURL request to Chargify
	 * 
	 * @param string $uri The uri/functiuon set we are accessing
	 * @param string $format optional JSON or XML accepted
	 * @param string $method optional, GET,POST,PUT etc default GET
	 * @param mixed (json or xml) $data the data to send if post/put
	 */  		
	function sendRequest($uri, $method = 'GET', $data = '') {
   
		//$extension = strtoupper($format) == 'XML' ? '.xml' : '.json';
		$targetUrl = $this->chargifyUrl . $uri . '.' . $this->restFormat;

		// Curly wurly!
		$ch = curl_init();

		// more curly wurley!
		curl_setopt($ch, CURLOPT_URL, $targetUrl);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// XML? Pah! no thanks (but just incase!)
		if ($this->restFormat == 'xml') {
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		  'Content-Type: application/xml',
		  'Accept: application/xml'));
		} else {
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		  'Content-Type: application/json',
		  'Accept: application/json'));
		}

		curl_setopt($ch, CURLOPT_USERPWD, $this->apiKey . ':' . $this->password);

		$method = strtoupper($method);
		
		if($method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		elseif ($method == 'PUT') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		elseif($method != 'GET') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}

		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);

		// Build the result object
		$result = new StdClass();
		$result->response = curl_exec($ch);
		$result->code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$result->meta = curl_getinfo($ch);

		$curl_error = ($result->code > 0 ? null : curl_error($ch) . ' (' . curl_errno($ch) . ')');

		curl_close($ch);

		// Any Curl errors?
		if ($curl_error) {
			$this->messages =  array('class' => 'error' , 'message' => $curl_error);
		}
		// 200 response, something has #hadabarry
		elseif($result->code != '200' && empty($result->response)) {
			$this->messages =  array(
			  'class' => 'error' , 
			  'message' => $result->code. ' ' . __('There was an error with processing your request',true));
		}
		// 422 response, request ok but known errors (things missing etc.)
		elseif($result->code == '422' && !empty($result->response)) {
			$errors = json_decode($result->response,true); 
			$this->messages =  array(
			  'class' => 'error' , 
			  'message' => $errors['errors']);
		}
		// 200 BINGO! everything ok!
		elseif($result->code == '200' && !empty($result->response)) {
			$this->messages =  array(
			  'class' => 'success' , 
			  'message' => $result->code. ' ' . __('Great Success!',true));
		}
		// 201 BINGO! everything ok! (201 returned on creation success after a POST)
		elseif($result->code == '201' && !empty($result->response)) {
			$this->messages =  array(
			  'class' => 'success' , 
			  'message' => $result->code. ' ' . __('Great Success!',true));
		}

		return $result->response;
  
	}
    
}
?>