<?php
// vim: set ai ts=4 sw=4 ft=php:
//
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2018 Sangoma Technologies
//
namespace FreePBX\modules\Certman;

class Logger { 
	function __call($name, $arguments) { 
		dbug(date('Y-m-d H:i:s')." [$name] ${arguments[0]}"); 
	}
}
