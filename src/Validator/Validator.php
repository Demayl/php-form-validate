<?php

// vim: ts=4:sw=4:noexpandtab
namespace Validator;
use DateTime;
use Exception;
use ReflectionMethod;

/**
 * Validation Class
 *
 * Validates form input
 *
 * @package Validator::Validator
 * @author  Denis Kanchev <denis@demayl.com>
 * @link	https://github.com/Demayl
 * @copyright   Copyright (c) 2017 Denis Kanchev
 * @version 1.1.3
 */

// That class can be used in any other Exception
// Uses pattern for handled Exceptions
// Goal is to avoid auto casting
// A Failure is a soft or unthrown exception, that throws when left unhandled. It acts as a wrapper around an Exception object.
// Idea is to force handling all errors ( not ignoring them )

class Failure extends Exception {

	private $field	 = null;  // Field name
	private $handled  = false; // Handled flag

	public function __construct($message, $field, $code = 0, Exception $previous = null) {
		$this->field   = $field;
		parent::__construct($message, $code, $previous); // Construct Exception
	}

	// Throw Exception if some are unhandled after instance goes out of scope
	public function __destruct(){
		if( !$this->handled ){
			$this->raise();
		}
	}

	// Throw error as Exception
	public function raise(){
		throw $this;
	}

	// Mark as handled
	// @return void
	public function handle(){
		$this->handled = true;
	}

	// Check if handled
	// @return bool
	public function handled() {
		return $this->handled;
	}

	// Mark error as unhandled
	public function unhandle() {
		$this->handled = false;
		return true;
	}

	// Get the field and mark handled
	public function field(){
		$this->handled = true;
		return $this->field;
	}

	// Return error messages and mark them as handled
	public function message(){
		$this->handled = true;
		$last_trace = end($this->getTrace());
		$errorMsg = 'Error on line '.$last_trace['line'].' in '.$last_trace['file']
			.': "'.$this->field().'" invalid field';
		return $errorMsg;
	}

	public function __toString_old(){ // @TODO represent Exception as stirng for stringification ?
		return 'Failure';
	}

}

abstract class ValidatorShared {

	// Check for public methods in current class
	protected function can($method){
		if( method_exists( $this, $method ) ){
			$refl = new ReflectionMethod($this, $method); // Avoid protected method problem
			return $refl->isPublic();
		}

		return false;
	}

	// Throw Exception error
	// Internal use
	protected function throwError( $error, $field = null ){
		throw new Failure( $error, $field );
	}

	// Get Exception error
	protected function exception( $error, $field = null ){
		return new Failure( $error, $field );
	}
}


// Here add new types as methods
class ValidatorTypes extends ValidatorShared {
	const MAIL	    = "/^[\pL\pN_-]+(\.[\pL\pN_-]+)*@[\pL\pN-]+(\.[\pL\pN-]+)*(\.[\pL]{2,12})$/u"; // @TODO add full email validator
	const DATE	    = '/^(\d{4})-(\d{2})-(\d{2})$/';
	const DATETIME  = '/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/'; // MySQL format YYYY-MM-DD hh:mm:ss
	const TIME	    = '/^(?:[01][0-9]|2[0-3])\:(?:[0-5][0-9])$/'; // 24 hour time format
	const UNIX_TIME = '/^d{10}$/';

	public function int($what){
		return (bool) preg_match( "/^\d+$/", $what ); // Evade auto casting problems
	}

	public function float($what){
		return (bool) preg_match( "/^\d+(\.\d+)?$/", $what ); // Evade auto casting problems
	}

	public function numeric($what){
		return is_numeric($what);
	}

	public function string($what){
		return (bool) is_string($what);
	}

	public function char($what){
		return (bool) preg_match("/\w/",$what);
	}

	public function charnum($what){
		return (bool) preg_match("/[\w\d]/",$what);
	}

	public function email($what){
		return preg_match(self::MAIL, $what);
	}

	public function json($what){
		return (bool) @json_decode($what);
	}

	public function date($what){
		return (bool) preg_match( self::DATE, $what, $match ) && checkdate( $match[2], $match[3], $match[1] ) ;
	}

	public function datetime($what){
		$d = DateTime::createFromFormat('Y-m-d H:i:s', $what);
		return (bool) preg_match( self::DATETIME, $what ) && $d->format('Y-m-d H:i:s') == $what;
	}

	public function time($what){
		return (bool) preg_match( self::TIME, $what );
	}

	public function unix_time($what){
		return (bool) preg_match( self::UNIX_TIME, $what ) && (int) $what === $what && $what > 0 && $what <= PHP_INT_MAX;
	}

	// List of integers ( 1,2,3 )
	public function int_list($what){
		return $what && preg_match("/^(\d+(?:,|$))+$/", $what);
	}

	public function any() {
		return true;
	}
}


// Add here methods for filter
// @TODO add lambda option as filter
class ValidatorFilter extends ValidatorShared {


	public function non_digit($what){
		return preg_replace("/\D+/",'',$what);
	}

	public function trim($what){
		return trim($what);
	}

	public function html($what){
		return strip_tags($what);
	}
	
	public function lc($what){		
		return strtolower($what);
	}
}

class ValidatorOptions extends ValidatorShared {

	private static $fields = ['type', 'required', 'range', 'length', 'multiple', 'match', 'disabled', 'value', 'msg', 'msg_miss', 'default', 'requires', 'regex', 'filter'];
	private $tests  = [ 'match', 'length', 'regex', 'range' ];
	private $stored = [];

	public function store( array $fields ){
		$this->stored = $fields;
	}

	public function throwIfMissing( array $fields ){
		$diff = array_diff( array_keys($fields), self::$fields );
		if( count( $diff ) ){
			$this->throwError( 'Unknown options: ' . implode(', ', $diff) );
		}
	}

	public function testOptions( array $options, $value ){
		$check = null;
		foreach( $this->tests as $_test ){
			if( !array_key_exists( $_test, $options ) ) continue; // Skip missing field
			$check = $this->$_test( $value, $options[$_test], $options );

			if( !$check ) break; // Found error - skip other tests @NOTE save which test failed ?
		}

		return $check;
	}

	private function range( $value, $opt_value, $options ){

		if( $options['type'] === 'int' || $options['type'] === 'float' ){
			return $this->inRange( $opt_value, $value, $options );
		}

		$this->throwError( 'Invalid type '.$options['type'].' for range. Allowed types are int or float' );
		return null;
	}

	private function regex( $value, $opt_value ){
		return preg_match( $opt_value, $value );
	}

	private function length( $value, $opt_value, $options ){
		$length = mb_strlen( $value );
		return $this->inRange( $opt_value, $length, $options );
	}

	private function match( $value, $opt_value, array $options ){

		$match = is_array($opt_value) ? $opt_value : [ $opt_value ];
		$check = false;

		foreach( $match as $_match ){
			if( !is_string($_match) && is_object($_match) && is_callable( $_match ) ){
				$check = $_match( $value );
				if( $check ) break;
			}
			elseif( preg_match('/^([\\/#%]).*\1$/', $_match ) ) { // Looks like regex
				$check = (bool) preg_match( $_match,$value );
				if( $check ) break;
			}
			elseif( 
				($options['type'] === 'string' && (string) $_match === (string) $value ) || 
				($options['type'] === 'int' && (int) $_match === (int) $value) || 
				($options['type'] === 'float' && (float) $_match === (float) $value) 
			){
				$check = true;
				break; // Found match for this value
			}
		}
		return $check;
	}

	/* Test value in range. Used for string length too
	* @example
	* 2-10 : From 2 to 10
	* -9   : From 0 to 9
	* 10-  : From 10 till infinite
	* 11   : Exact 11
	*/
	private function inRange( $range, $value, $options ){

		preg_match('/^(\d+(?:\.\d+)?)?(-)?(\d+(?:\.\d+)?)?$/', $range, $matches);

		$start   = $matches[1];
		$sep	 = isset($matches[2]) ? $matches[2] : null ;
		$end	 = isset($matches[3]) ? $matches[3] : null ;

		if( !isset($start) && !isset($end) ){
			$this->throwError( 'Invalid range ! Format is from-to ( 1-10 for example )' );
		} else if( isset($start) && isset($end) && $start > $end ){
			$this->throwError( 'Right side of range is smaller that left one!' );
		}
		
		if( isset($start) && isset($end) ){ // 1-10
			return $start <= $value && $end >= $value ;
		}
		else if( isset($start) && !isset($end) && $sep ){ // 1-
			return $start <= $value;
		}
		else if( !isset($start) && isset($end) && $sep ){ // -11
			return $end >= $value;
		}
		else if( !$sep && $start ){ // Exact number
			if( $options['type'] === 'int' )   return (int) $value === (int) $start ;
			if( $options['type'] === 'float' ) return (float) $value === (float) $start ;
		}
	}

}

class Validator extends ValidatorShared {

	private $types 	 = null;
	private $filter  = null;
	private $options = null; // ValidatorOptions
	private $use_exc = null;
	public $invalid  = []; // Invalid params
	public $valid	 = []; // Valid params
	public $params 	 = []; // All params
	public $errors 	 = []; // k => v with field name and error message
	public $fields 	 = []; // Array from field_name => field_value

	private $requires = [];

	const FIELDS_REQUIRED = true; // By default all fields are required
	const DEFAULT_TYPE	= 'string';

	// TODO add params parse here
	public function __construct($fields, $use_exceptions = true){
		$this->types   = new ValidatorTypes;
		$this->filter  = new ValidatorFilter;
		$this->options = new ValidatorOptions;
		$this->fields  = $fields; // Actiaully $_REQUEST
		$this->use_exc = $use_exceptions;

		$this->options->store( $fields );
	}

	// Clear anything about 1 or all fields
	public function clear( $what=null ){
		if( !$what ){
			$this->invalid = [];
			$this->valid   = [];
			$this->errors  = [];

			$this->requires = [];
		} else {
			unset($this->valid[$what]);
			unset($this->invalid[$what]);
			unset($this->errors[$what]);
		}
	}

	// Check if errors
	// @return bool
	public function hasErrors(){
		return !empty($this->errors);
	}

	// @TODO add multiple field match by regex
	public function validateAll(array $fields){

		$this->clear();

		foreach( $fields as $row => $options ){
			if( isset($options['disabled']) && $options['disabled'] ) continue; // Skip disabled fields
			$this->options->throwIfMissing( $options );

			if( preg_match('/^([\\/#%]).*\1$/', $row) ){ // Field name looks like regex
				if( isset($this->fields[$row]) ) $this->throwError('Field is regex, but exists literally!');

				$this->fields[$row] = [];

				foreach( $this->fields as $field => $value ){
					if( !preg_match( $row, $field ) ) continue; // SKIP non-matches

					$this->validate( $field, $options );

					// Agregate result into 'regex' named
					if( isset($this->valid[$field]) ){
						if( !isset($this->valid[$row]) ) $this->valid[$row] = [];
						$this->valid[$row][$field]   = $this->valid[$field];
					}

					if( isset($this->invalid[$field]) ){
						if( !isset($this->invalid[$row]) ) $this->invalid[$row] = [];
						$this->invalid[$row][$field] = $this->invalid[$field];
					}

					if( isset($this->errors[$field]) ){
						if( !isset($this->errors[$row]) ) $this->errors[$row] = [];
						$this->errors[$row][$field]  = $this->errors[$field];
					}

					if( isset($options['remove_original']) && $options['remove_original'] ){ // @TODO document this option
						$this->clear( $field );
					}
				}
			} else {
				$this->validate( $row, $options );
			}
		}

		$this->testRequires(); // If some fields are required by other
		return $this;
	}

	// Validate field
	// Required and missing values  : Error
	// Required and invalid values  : Error
	// Not required and Missing 	: Nothing ( missing is null or not set )
	// Not required and invalid 	: Error
	public function validate($field, array $params) {

		if( isset($type['disabled']) && $params['disabled'] ) {
			return null;
		}

		// FILTER here values

		$params = $this->filterField($field,$params); // It can handle array values. Modifies $this->fields
		$valid  = $this->valid($field, $params);

		if( !isset($params['required']) ){ // Set default behaviour for required or not fields
			$params['required'] = self::FIELDS_REQUIRED;
		}

		$value	  = isset($params['value']) 	? $params['value'] 		: ( isset($this->fields[$field]) ? $this->fields[$field] : null );
		$msg	  = isset($params['msg']) 		? $params['msg'] 		: 'Invalid field ' . $field; // Default message for invalid field
		$msg_miss = isset($params['msg_miss']) 	? $params['msg_miss'] 	: $msg; // Message for missing field ( when required )

		if( (!isset($value) || $value === '') && isset($params['default']) && !$params['required'] ){
			$value = $params['default'];
		}

		if( $valid ) {
			$this->valid[$field] = $this->typeCast($value, isset($params['type']) ? $params['type'] : self::DEFAULT_TYPE); // type cast when valid

			if( isset($params['requires']) ){
				$this->requires($params['requires'], $field);
			}
		} 
		else if( $valid === false ) { // Not a valid val
			$this->setInvalid($field,$msg,$value);
		} 
		else if( $valid === null && $params['required'] ) {  // Missing, but required val
			$this->setInvalid($field,$msg_miss);
		}
		else if( $valid === null )
		{
			$this->valid[$field] = ''; // To evade PHP warning for missing array key in valid array
		}


	}

	public function valid($field, array $params) {
		if( !is_string($field) && !is_int($field) )		   return $this->throwError('Parameter name must be a string!');
		if( isset($type['disabled']) && $params['disabled'] ) return null;

		if( array_key_exists($field,$this->fields) && isset($this->fields[$field]) ){
			$value = $this->fields[$field];
		} 
		else {
			$value = isset($params['value']) ? $params['value'] : null ;
		}

		if( (!isset($value) || $value === '') && isset($params['default']) && isset($params['required']) && !$params['required'] ){ // Empty string is actualy a empty field
			$value = $params['default'];
		}
		elseif( !isset($value) || $value === ''){ // Empty string is actualy a empty field
			return null;
		} 
		elseif( isset($params['multiple']) && isset($params['required']) && $params['required'] && !is_array($value) ){ // When multiple field is required, but one value - invalid
			return null;
		}

		$type = isset($params['type']) ? $params['type'] : self::DEFAULT_TYPE;
		$params['type'] = $type;

		if( is_array($value) && (!isset($params['multiple']) || !$params['multiple']) ){
			return false;
		}

		if(!is_array($value)) $value = [$value];

		foreach( $value as $_value ){

			// Check its type
			// @TODO option for default val
			if( $type && $this->types->can( $type ) ){
				$check = $this->types->$type( $_value ); // Validate type first

				if( !$check ) return false;

				$check = $this->options->testOptions( $params, $_value ); // Perform tests

				if( $check === false ) return false;

			} else if( $type ){ // Unknown type - fatal
				$this->throwError( 'Unknown type: ' . $type );
			} else {
				$this->throwError( 'Type option is required' );
			}
		}

		// Consume parameters
		$types = [ 'match', 'length', 'regex', 'range' ];
		foreach( $types as $_type ){
			if( isset($params[$_type]) ) unset($params[$_type]); // Unset here
		}

		$this->testConsumedParams($params);

		return true;
	}

	// Set field invalid
	// @param str $field_name
	// @param str $message
	// @param any $value DEFAULT=null
	public function setInvalid($field,$msg,$value=null){ // @TODO keep field options

		if(isset($this->valid[$field])) unset($this->valid[$field]);

		$this->invalid[$field] = $value;
		$this->errors[$field]  = $this->use_exc ? new Failure( $msg, $field ) : $msg;
		if( method_exists($this, 'addedCustomError') ) $this->addedCustomError( $msg ); // @TODO add option for custom error
	}


	private function filterField($field,$params){

		if( array_key_exists('filter',$params) ){ // have filter
			$filter = $params['filter'];
			if( !$filter ){
				$this->throwError( 'Empty filter' );
			}
			if( !is_array($filter) ){
				$filter = [$filter];
			}

			foreach( $filter as $_filter ){
				if( $_filter && $this->filter->can( $_filter ) ){

					if( array_key_exists('value',$params) ){
						$this->fields[$field] = $params['value'] = $this->filter->$_filter($params['value']);
					} else {

						if( isset($this->fields[$field]) && is_array($this->fields[$field]) ){ // Field is array
							if( !$params['multiple'] ) $this->throwError( 'Field '.$field.' is array, but not specified in params as multiple. Possible logic error.' );
							$this->fields[$field] = array_map( [$this->filter,$_filter], $this->fields[$field] );
						} elseif( isset($this->fields[$field]) ) {
							$this->fields[$field] = $this->filter->$_filter( $this->fields[$field] );
						}
					}
				} elseif( $_filter && !$this->filter->can( $_filter ) ){
					$this->throwError( 'Missing filter ' . $_filter );
				}
			}
		}

		return $params;
	}

	// Cast value to provided type
	private function typeCast($value, $type = self::DEFAULT_TYPE){
		$value_is_array = true;
		if(!is_array($value)) {
			$value_is_array = false;
			$value = [$value];
		}

		$end_value = [];

		if( !isset($type) ) $type = self::DEFAULT_TYPE;

		foreach( $value as $_value ){
			if( $_value !== null && $type){
				switch($type){
					case 'int':
						$_value = (int) $_value;
						break;
					case 'float':
						$_value = (float) $_value;
						break;
					case 'string':
						$_value = (string) $_value;
						break;
					case 'char':
						$_value = (string) $_value;
						break;
					case 'bool':
						$_value = (bool) $_value;
						break;
					case 'numeric': // numeric is dual type var - int|float
						$_value = preg_match("/^\d+$/", $_value) ? (int) $_value : (float) $_value;
						break;
					default:
						$_value = (string) $_value;
				}
			}

			$end_value[] = $_value;
		}

		return $value_is_array ? $end_value : $end_value[0];
	}

	// Test wrong used params @TODO remote it
	private function testConsumedParams($params){
		
		$wrong   = ['range', 'regex'];
		$matches = array_intersect(array_keys($params),$wrong);
		if( $matches && count($matches) > 0 ){
			$this->throwError( "Not consumed parameters: " . implode(", ", $matches) );
		}
	}

	// One field requires another to be valid
	private function requires($what,$field){
		if( !isset($this->requires[$field]) ) $this->requires[$field] = [];
		if( !is_array($what) ) $what = [ $what ];

		$this->requires[$field] = array_merge($this->requires[$field], $what);
	}

	private function testRequires(){
		if( count($this->requires) === 0 ){
			return false;
		}

		foreach( $this->requires as $field => $required ){
			foreach( $required as $required_field ){
				if(!isset($this->valid[$required_field])){
					$this->setInvalid($field, "Missing required field: " . $required_field, $this->valid[$field]); // @TODO dynamic msg ?
					unset($this->valid[$field]);
					break; // Finished this row
				}
			}
		}
	}
}
