<?php
	/* 
	* inlineXHR - Copyright Eaktion.com - All rights reserved
    * http://www.eaktion.com/inlinexhr/
    * 
    * This PHP code is designed to work together with a YUI 3 module to 
    * straighten out the AJAX pattern for you.
    *
    * Find the YUI 3 module in the YUILibrary: 
    * https://github.com/tribis/yui3-gallery/tree/master/build/gallery-inline-xhr
    * 
    * The PHP code of this PHP/JavaScript package is based on 
    * http://www.satyam.com.ar/yui/PhpJson.htm
    * trimmed and modified into a pattern that allows a response object being passed 
    * to functions or methods.
    *
    * Released under BSD licence
    * Redistribution and use in source and binary forms, with or without
    * modification, are permitted provided that the following conditions are met:
    *     * Redistributions of source code must retain the above copyright
    *       notice, this list of conditions and the following disclaimer.
    *     * Redistributions in binary form must reproduce the above copyright
    *       notice, this list of conditions and the following disclaimer in the
    *       documentation and/or other materials provided with the distribution.
    *     * Neither the name of Eaktion.com / Eaktion ApS nor the
    *       names of its contributors may be used to endorse or promote products
    *       derived from this software without specific prior written permission.
    *
    * THIS SOFTWARE IS PROVIDED BY Eaktion ApS ``AS IS'' AND ANY
    * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    * DISCLAIMED. IN NO EVENT SHALL Eaktion ApS BE LIABLE FOR ANY
    * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
    * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
    */
abstract class Dispatcher {
        protected function &accessObject (self $pObj) {
            return $pObj;
        }
        protected function invokeMethod ($pObj, $pName, $pArgs) {
            return call_user_func_array(array($pObj, $pName), $pArgs);
        }
    }

class ajaxResponse extends Dispatcher
{
	
	private $return = array();
	
	function __construct()
	{		

	}

	/**
	 * Adds to the xhr reply a call to a method of Y. 
	 * It might also be a private function, in which case 
	 * the client script must register it as private with 
	 * the inlinexhr object.
	 * 
	 *
	 * @param string $sName the function/method name
	 * @param mixed $sArg
	 */
	public function callMethod($sName, $mArg)
	{
		$this->return[] = array('f'=>(string)$sName , 'a'=>$mArg);
	}	
	
	/**
	 * 
	 * The processor object must call this directly:
	 * Using a dispatcher interface to invoke it from processor 
	 * and keeping it protected at the same time, thanks to
	 * http://www.php.net/manual/en/language.oop5.visibility.php#91850
	 * 
	 * Build a reply with 
	 * 		c the reply code
	 * 		t the reply text
	 * 		ff an optional 2 dim array of functionName => args, where 
	 * functionName is a string and args is a JSON structure. 
	 * self::callMethod takes case of this
	 *
	 * @param int $replyCode
	 * @param string $replyText
	 */
	protected function _reply($replyCode = 200, $replyText = 'Ok') 
	{
		
	    $s = '';
	    for ($iArg = 2;$iArg < func_num_args();$iArg++) {
	        $arg = func_get_arg($iArg);
	        if (is_array($arg)) {
	            $arg = json_encode($arg);
	            $s .= ',"ff":' . $arg;
	            
	        } else {
	            trigger_error("ajaxReply: optional argument at position $iArg value '$arg' is invalid, 
	                only arrays allowed",E_USER_ERROR);
	        }
	    }
	    echo '{"c":' , $replyCode , ',"t":"' , $replyText , '"' , $s, '}';
    	exit;
	}
	
	/**
	 * All proceded well, a function or method asks to send its data
	 *
	 */
	public function reply()
	{
		if($this->return){
			$this->_reply(200, 'Ok', $this->return);
		}else{
			$this->_reply(200, 'Ok', $this->return);
		}
		exit();
	}
}


/**
 * ajaxProcess class, processes the incoming request and 
 * dispatches it to the correct function or method
 * If no matching function is found, it outputs an own reply
 * with JSON formatted error messages apt to be recevied by inlineXHR on the client.
 * 
 * An error handler is also set to catch runtime errors and output them to the browser
 * as JSON payloads
 *
 */

class ajaxProcessor extends Dispatcher
{
	
	private $response;	
	private $debug;
	private $ajaxAction 	= false;
	private $data 			= false;
	private $VALID_FUNC_NAME_REG = '/^[a-zA-Z_0-9]+$/';
	private $CLASS_NAME 	= false;
	private $METHOD 		= 'POST';
	private $DEBUG 			= false;

	/**
	 * Costructor
	 *
	 * @param bool $debug
	 * @param false or string $className
	 */
	function __construct($className = false, $debug = false)
	{		
		set_error_handler(array($this,'ajaxErrorHandler'));
		
		if(is_string($className)){
			$this->configure('classname', $className);
		}
		
		$debug = (bool)$debug;
		if ($debug) {
			$this->configure('debug', $debug);
		}			
		$this->response =& $this->accessObject(new ajaxResponse());
	}
	
		/**
	 * 
	 * @param int $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param int $errline
	 * @return JSON
	 */
	public function ajaxErrorHandler($errno, $errstr, $errfile, $errline)  
	{
		
		$errstr = str_replace("\n","",$errstr);
		$errstr = str_replace("\r","",$errstr);
		$errstr = str_replace("\t","",$errstr);
		
	    switch ($errno) {
	        case E_USER_ERROR:
	            echo '{"c":612,"t":"User Error: ' 
	                , $errstr . '","errno":', $errno;
	            break;
	        case E_USER_WARNING:
	            echo '{"c":611,"t":"User Warning: ' 
	                , $errstr . '","errno":', $errno;
	            break;
	        case E_USER_NOTICE:
	        case E_NOTICE:
	        	return true;
	        default:
	            echo '{"c":610,"t":"' 
	                , $errstr . '","errno":', $errno;
	            break;
	    }
	    if ($errfile) {
	        echo ',"errfile":"' , $errfile ,'"';
	    }
	    if ($errline) {
	        echo ',"errline":"', $errline ,'"';
	    }
	    echo '}';
	    die();
	}
	
	/**
	 * Process the request, find the function or 
	 * method requested 
	 * Add header to the response
	 * and execute with data from either GET or POST as configured.
	 * Pass a response object together with the data and let it
	 * emit the resonse. If everything goes well it will exit afterward.
	 * Otherwise send an error response and exit.
	 *
	 * @todo add capability to provide a catch all func/method
	 * @return exit or objResponse
	 */
	public function process()
	{
		if ($this->parseRequest()) {
			
			$debug 		= $this->DEBUG;
			$className 	= $this->CLASS_NAME;
			$func 		= $this->ajaxAction;
			$data 		= $this->data;
			
			if ($className && method_exists($this->CLASS_NAME, $func)) {
				$this->add_headers();
				/**
				* http://www.satyam.com.ar/yui/PhpJson.htm
				* Modified into a pattern where a response object is passed to the funcs or methods 
				*/
				$obj = new $className();
				$this->add_headers();
				$obj->$func($data,$this->response);//response should terminate, however continuing below just in case
				$msgArgs = array(604,'improper setup: did you forget to call ajaxResponse::reply()?');
				$this->invokeMethod(
										$this->response,
										'_reply',
										$msgArgs);

			} elseif (!$className && function_exists($func)) {

				$this->add_headers();
				$func($data,$this->response);//response should terminate, however continuing below just in case
				$msgArgs = array(604,'improper setup: did you forget to call ajaxResponse::reply()?');
				$this->invokeMethod(
										$this->response,
										'_reply',
										$msgArgs);
			}
			/**
			 * @todo catch all?
			 */
			
			//using dispatcher to circumvent restriction on use of protected _reply
			$msgArgsClass = array(601,
									"Method not defined" . ($this->DEBUG ? ": " . $func . " for class: " . $this->CLASS_NAME . "." : ".")
									);
			$msgArgsFunc = array(601,
									"Function not defined" . ($this->DEBUG ? ": " . $func . "." : ".")
									);

			$this->CLASS_NAME ? 	$this->invokeMethod(
														$this->response,
														'_reply',
														$msgArgsClass) 
									:
									$this->invokeMethod(
														$this->response,
														'_reply',$msgArgsFunc);			

		}
		//no AJAX request found, parseRequest returns false 
	}

	private function add_headers()
	{
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); 
		header('Content-type: application/json; charset=utf-8');
	}

	
	private function parseRequest()
	{
		$useGET = false;

		if ('GET' === $this->METHOD) {
			$useGET = true;
		}
		
		if ($useGET) {
			$this->ajaxAction = trim($_GET['ajaxAction']);
			unset($_GET['ajaxAction']);
		} else {
			$this->ajaxAction = trim($_POST['ajaxAction']);
			unset($_POST['ajaxAction']);
		}
		
		//only intervene if this is an ajax transaction
		if (strlen($this->ajaxAction)) {
			if(!preg_match($this->VALID_FUNC_NAME_REG,$this->ajaxAction)){
				$this->response->_reply(602,"action name " . 
										($this->DEBUG ? ": " . $this->ajaxAction : "") . 
										" contains invalid characters.");
			}else{
				if($useGET){
					$this->data = $_GET;
				} else {
					$this->data = $_POST;
				}
			}
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Method to configure the processor to work in specific ways
	 * Options (values) are: 
	 * 	method ('POST','GET')
	 *  debug (false, true)
	 *  classname (aClassNameString)
	 *
	 * @param string $option
	 * @param mixed $value
	 */
	public function configure($option, $value)
	{		
		
		$option = strtolower($option);
		
		if (is_string($value)) {
			$value 	= strtolower($value);
		} else {
			$value 	= (bool)($value);
		}
		
		switch ($option) {
			case 'method':
				if('get' === $value){
					$this->METHOD = 'GET';
				}
				break;
			case 'debug':
				if(true === $value){
					$this->DEBUG = true;
				}
				break;
			case 'classname':
				if(preg_match($this->VALID_FUNC_NAME_REG,trim($value))){
					$this->CLASS_NAME = trim($value);
				}
				break;				
		}
	}
}