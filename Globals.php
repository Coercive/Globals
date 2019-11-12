<?php
namespace Coercive\Utility\Globals;

/**
 * Globals
 *
 * @package 	Coercive\Utility\Globals
 * @link		https://github.com/Coercive/Globals
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   2016 - 2018 Anthony Moral
 * @license 	MIT
 *
 * @method 		Globals|mixed 	COOKIE($name = null, $value = null)
 * @method 		Globals|mixed 	ENV($name = null, $value = null)
 * @method 		Globals|mixed 	FILE($name = null, $value = null)
 * @method 		Globals|mixed 	GET($name = null, $value = null)
 * @method 		Globals|mixed 	POST($name = null, $value = null)
 * @method 		Globals|mixed 	REQUEST($name = null, $value = null)
 * @method 		Globals|mixed 	SERVER($name = null, $value = null)
 * @method 		Globals|mixed 	SESSION($name = null, $value = null)
 */
class Globals
{
	const FILTER_OCTAL = 1;

	/** @var string */
	private $_sGlobal = '';

	/** @var bool */
	private $_bFilter = true;

	/** @var int */
	private $_iFilterType = 0;

	/** @var int */
	private $_iSpecialFilterType = 0;

	/**
	 * SET GLOBAL
	 *
	 * @param string $name
	 * @return bool
	 */
	private function _setGlobal(string $name): bool
	{
		# PREPARE
		$name = '_'.strtoupper($name);
		global $$name;

		# VERIFY
		if(!is_array($$name)) return false;

		# SET
		$this->_sGlobal = $name;
		return true;
	}

	/**
	 * AUTO FILTER
	 *
	 * @param mixed $item
	 * @return mixed
	 */
	private function _autoFilter($item)
	{
		# NULL
		if(null === $item) { return null; }

		# WHAT ELSE
		if(is_object($item) || is_resource($item)) {
			return $item;
		}

		# BOOL
		if(is_bool($item) || $item === 'false' || $item === 'true') {
			if($item === 'false') { $item = false; }
			elseif($item === 'true') { $item = true; }
			return $item;
		}

		# ARRAY
		if(is_array($item)) {
			foreach ($item as $k => $v) {
				$item[$k] = $this->_autoFilter($v);
			}
			return $item;
		}

		# INT
		$int = filter_var($item, FILTER_VALIDATE_INT);
		if($int !== false) { return $int; }

		# FLOAT
		if(preg_match('#^([\+\-])?(?!0[0-9]+)[0-9]+\.[0-9]+$#', $item)) {
			return floatval($item);
		}

		# STRING
		return filter_var($item, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
	}

	/**
	 * GETTER
	 *
	 * @param string $name
	 * @return null|mixed
	 */
	private function _get(string $name)
	{
		global ${$this->_sGlobal};

		# EXIT
		if(!isset(${$this->_sGlobal}[$name])) return null;

		/** @var mixed $var */
		$var = ${$this->_sGlobal}[$name];
		if(!is_array($var)) { $var = [$var]; }

		foreach ($var as $k => $v) {
			# INNER FILTER
			if($this->_iSpecialFilterType) {
				switch ($this->_iSpecialFilterType) {
					case self::FILTER_OCTAL:
						$var[$k] = octdec($v);
						break;
				}
			}
			# FILTER
			elseif($this->_iFilterType) {
				$var[$k] = filter_var($v, $this->_iFilterType);
			}
			# AUTO
			else {
				$var[$k] = $this->_bFilter ? $this->_autoFilter($v) : $v;
			}
		}

		$this->_iSpecialFilterType = 0;
		$this->_iFilterType = 0;
		return $var;
	}

	/**
	 * SETTER
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return bool
	 */
	private function _set(string $name, $value): bool
	{
		global ${$this->_sGlobal};

		# SKIP
		if(!is_array(${$this->_sGlobal})) { return false; }

		# ASSIGN
		${$this->_sGlobal}[$name] = $value;

		return true;
	}

	/**
	 * PUBLIC AUTO FILTER VAR
	 * 
	 * @param mixed $var
	 * @return mixed
	 */
	public function autoFilterManualVar($var)
	{
		return $this->_autoFilter($var);
	}

	/**
	 * (DES)ACTIVATE FILTER
	 *
	 * @param bool $state
	 * @return Globals
	 */
	public function filter(bool $state): Globals
	{
		$this->_bFilter = (bool) $state;
		return $this;
	}

	/**
	 * FILTER ALL
	 *
	 * @return array
	 */
	public function filterAll(): array
	{
		global ${$this->_sGlobal};
		if(!is_array(${$this->_sGlobal})) { return []; }

		# Processing
		$global = [];
		foreach (${$this->_sGlobal} as $name => $value) {
			$global[$name] = $this->_autoFilter($value);
		}

		return $global;
	}

	/**
	 * AUTO CALL $_[ITEM]
	 *
	 * @param string $name Global Name
	 * @param array $arguments [0] Field Name, [1] Set Value [optional]
	 * @return mixed
	 */
	public function __call(string $name, array $arguments = [])
	{
		# EXIT
		if(!$name || !$this->_setGlobal($name)) { return null; }

		# CHAINABILITY
		if(empty($arguments[0])) { return $this; }

		# SET
		if(isset($arguments[1])) { return $this->_set($arguments[0], $arguments[1]); }

		# GET
		return $this->_get($arguments[0]);
	}

	/**
	 * FILTER_OCTAL
	 *
	 * @return Globals
	 */
	public function octal(): Globals
	{
		$this->_iSpecialFilterType = self::FILTER_OCTAL;
		return $this;
	}

	/**
	 * FILTER_VALIDATE_INT
	 *
	 * @return Globals
	 */
	public function int(): Globals
	{
		$this->_iFilterType = FILTER_VALIDATE_INT;
		return $this;
	}

	/**
	 * FILTER_VALIDATE_FLOAT
	 *
	 * @return Globals
	 */
	public function float(): Globals
	{
		$this->_iFilterType = FILTER_VALIDATE_FLOAT;
		return $this;
	}

	/**
	 * FILTER_VALIDATE_BOOLEAN
	 *
	 * @return Globals
	 */
	public function bool(): Globals
	{
		$this->_iFilterType = FILTER_VALIDATE_BOOLEAN;
		return $this;
	}

	/**
	 * FILTER_VALIDATE_IP
	 *
	 * @return Globals
	 */
	public function ip(): Globals
	{
		$this->_iFilterType = FILTER_VALIDATE_IP;
		return $this;
	}

	/**
	 * FILTER_FLAG_IPV4
	 *
	 * @return Globals
	 */
	public function ipv4(): Globals
	{
		$this->_iFilterType = FILTER_FLAG_IPV4;
		return $this;
	}

	/**
	 * FILTER_FLAG_IPV6
	 *
	 * @return Globals
	 */
	public function ipv6(): Globals
	{
		$this->_iFilterType = FILTER_FLAG_IPV6;
		return $this;
	}

	/**
	 * FILTER_CALLBACK
	 *
	 * @return Globals
	 */
	public function callback(): Globals
	{
		$this->_iFilterType = FILTER_CALLBACK;
		return $this;
	}

	/**
	 * FILTER_REQUIRE_ARRAY
	 *
	 * @return Globals
	 */
	public function array(): Globals
	{
		$this->_iFilterType = FILTER_REQUIRE_ARRAY;
		return $this;
	}

	/**
	 * FILTER_VALIDATE_EMAIL
	 *
	 * @return Globals
	 */
	public function email(): Globals
	{
		$this->_iFilterType = FILTER_VALIDATE_EMAIL;
		return $this;
	}

	/**
	 * FILTER_VALIDATE_URL
	 *
	 * @return Globals
	 */
	public function url(): Globals
	{
		$this->_iFilterType = FILTER_VALIDATE_URL;
		return $this;
	}

	/**
	 * FILTER_VALIDATE_URL
	 *
	 * @return Globals
	 */
	public function mac(): Globals
	{
		$this->_iFilterType = FILTER_VALIDATE_MAC;
		return $this;
	}

	/**
	 * FILTER_SANITIZE_SPECIAL_CHARS
	 *
	 * @return Globals
	 */
	public function string(): Globals
	{
		$this->_iFilterType = FILTER_SANITIZE_SPECIAL_CHARS;
		return $this;
	}

	/**
	 * FILTER_SANITIZE_FULL_SPECIAL_CHARS
	 *
	 * @return Globals
	 */
	public function stringFull(): Globals
	{
		$this->_iFilterType = FILTER_SANITIZE_FULL_SPECIAL_CHARS;
		return $this;
	}

	/**
	 * FILTER_DEFAULT
	 *
	 * @return Globals
	 */
	public function noFilter(): Globals
	{
		$this->_iFilterType = FILTER_DEFAULT;
		return $this;
	}

}
