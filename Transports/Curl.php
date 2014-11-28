<?php
namespace ServerPilot\Transports;

// load main Transport class for extending
require_once 'Transport.php';

// now use it
use ServerPilot\Transports\Transport;

class Curl extends Transport
{
	/**
	 * core request function
	 *
	 * used as the main communication layer between API and local code
	 *
	 * @param string $method defines the method for the request
	 *
	 * @return void
	 */
	public function request($path, $data=array(), $method=Transport::SP_HTTP_METHOD_GET) {
		$url = Transport::SP_API_ENDPOINT . $path;
		
		$ch = curl_init();
		$options = array(
			// general
			CURLOPT_URL => $url,
			CURLOPT_TIMEOUT => $this->requestTimeout,
			CURLOPT_USERAGENT => Transport::SP_USERAGENT,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_ENCODING => 'gzip',
			
			// ssl
			CURLOPT_SSL_VERIFYPEER => 0,
			CURLOPT_SSL_VERIFYHOST => 0,
			
			// auth
			CURLOPT_USERPWD => "$this->client_id:$this->api_key",
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
		);
		
		// send the data
		if (!empty($data) || $method == Transport::SP_HTTP_METHOD_DELETE) {
			switch ($method) {
				case Transport::SP_HTTP_METHOD_GET:
					$options[CURLOPT_URL] = $url . '?' . implode('&', $data);
				break;
				case Transport::SP_HTTP_METHOD_POST: 
					$data = json_encode($data);
					
					$options[CURLOPT_CUSTOMREQUEST] = Transport::SP_HTTP_METHOD_POST;
					$options[CURLOPT_POST] = TRUE;
					$options[CURLOPT_POSTFIELDS] = $data;
					
					$options[CURLOPT_HTTPHEADER] = array(                                                                          
					    'Content-Type: application/json',                                                                                
					    'Content-Length: ' . strlen($data)                                                                      
					);
				break;
				case Transport::SP_HTTP_METHOD_DELETE: 
					$options[CURLOPT_CUSTOMREQUEST] = Transport::SP_HTTP_METHOD_DELETE;
				break;
			}
		}
		
		// set the options
		curl_setopt_array($ch, $options);
		
		// response
        $response = curl_exec($ch);
	
	$code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	switch ($code) {
		case 200: break;
		case 400: throw new \Exception('We could not understand your request. Typically missing a parameter or header.'); break;
		case 401: throw new \Exception('Either no authentication credentials were provided or they are invalid.'); break;
		case 402: throw new \Exception('Method is restricted to users on the Coach or Business plan.'); break;
		case 403: throw new \Exception('Typically when trying to alter or delete protected resources.'); break;
		case 404: throw new \Exception('You requested a resource that does not exist.'); break;
		case 409: throw new \Exception('Typically when trying creating a resource that already exists.'); break;
		case 500: throw new \Exception('Internal server error. Try again at a later time'); break;
		default:  break;
	}
        //$info = curl_getinfo($ch);
		
        curl_close($ch);
        
        if(empty($response)) {
			throw new \Exception('ServerPilot Error: Empty Response');
        }
        
        // if we get here, assume we have a JSON string - decode
        if($response = json_decode($response)) {
	        // check for any SP specific errors
	        if(!empty($response->error)) {
		        throw new \Exception('ServerPilot Error: ' . $response->error);
	        }
        }
        
        // need to check headers/response and ensure no errors
        // last fallback
        // testing for 200 only is dangerous - what about the other success responses?
        /*if($status_code !== 200) {
	        throw new \Exception('HTTP Error ' . $status_code);
        }*/
        
        return $response;
	}
}
