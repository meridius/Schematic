<?php

namespace Schematic;

interface IEntry
{
	/**
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name);


	/**
	 * @param string $name
	 * @return bool
	 */
	public function __isset($name);


	public function __wakeup();
}
