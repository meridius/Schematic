<?php

namespace Schematic;

use InvalidArgumentException;


class Entry implements IEntry
{

	const INDEX_ENTRYCLASS = 0;
	const INDEX_MULTIPLICITY = 1;
	const INDEX_EMBEDDING = 2;
	const INDEX_NULLABLE = 3;
	const INDEX_ENTRIESCLASS = 4;

	const INDEX_ENTRY_INFO_ENTRY = 0;
	const INDEX_ENTRY_INFO_ENTRIES = 1;

	/**
	 * @var array
	 */
	protected static $associations = [];

	/**
	 * @var string
	 */
	protected static $entriesClass = Entries::class;

	/**
	 * @var array entryClass => [
	 *     INDEX_ENTRYCLASS => relatedEntryClass,
	 *     INDEX_MULTIPLICITY => multiplicity,
	 *     INDEX_EMBEDDING => embedding,
	 *     INDEX_NULLABLE => null value allowed,
	 * ]
	 */
	private static $parsedAssociations = [];

	/**
	 * @var array
	 */
	private $initializedAssociations = [];

	/**
	 * @var array
	 */
	private $data;


	/**
	 * @param array $data
	 * @param string $entriesClass
	 */
	public function __construct(array $data, $entriesClass = null)
	{
		$this->data = $data;

		if ($entriesClass) {
			if (!is_a($entriesClass, IEntries::class, TRUE)) {
				throw new InvalidArgumentException(sprintf(
					'Entries class must implement %s interface.',
					IEntries::class
				));
			}

			static::$entriesClass = $entriesClass;
		}

		$this->initParsedAssociations();
	}


	/**
	 * @param string $class
	 */
	private static function parseAssociations($class)
	{
		self::$parsedAssociations[$class] = [];

		foreach (static::$associations as $association => $entryInfo) {
			$matches = [];
			$result = preg_match('#^(\?)?([^.[\]]+)(\.[^.[\]]*)?(\[\])?$#', $association, $matches);

			if ($result === 0 || (!empty($matches[3]) && !empty($matches[4]))) {
				throw new InvalidArgumentException('Invalid association definition given: ' . $association);
			}

			if (is_array($entryInfo)) {
				if (count($entryInfo) !== 2) {
					throw new InvalidArgumentException(sprintf(
						"Number of custom association parameters for '%s' must be exactly %d.",
						$association,
						2
					));
				}

				$entryClass = $entryInfo[self::INDEX_ENTRY_INFO_ENTRY];
				$entriesClass = $entryInfo[self::INDEX_ENTRY_INFO_ENTRIES];

				self::validateCustomMultiplicityAssociation($entryClass, $association, self::INDEX_ENTRY_INFO_ENTRY);
				self::validateCustomMultiplicityAssociation($entriesClass, $association, self::INDEX_ENTRY_INFO_ENTRIES);
			} else {
				$entryClass = $entryInfo;
				$entriesClass = null;
			}

			self::$parsedAssociations[$class][$matches[2]] = [
				self::INDEX_ENTRYCLASS => $entryClass,
				self::INDEX_MULTIPLICITY => !empty($matches[4]),
				self::INDEX_EMBEDDING => !empty($matches[3])
					? ($matches[3] === '.' ? $matches[2] . '_' : substr($matches[3], 1))
					: FALSE,
				self::INDEX_NULLABLE => !empty($matches[1]),
				self::INDEX_ENTRIESCLASS => $entriesClass ?: static::$entriesClass,
			];
		}
	}


	/**
	 * @param string $classGiven
	 * @param string $association
	 * @param int $index
	 */
	private static function validateCustomMultiplicityAssociation($classGiven, $association, $index)
	{
		$params = [
			self::INDEX_ENTRY_INFO_ENTRY => ['First', IEntry::class],
			self::INDEX_ENTRY_INFO_ENTRIES => ['Second', IEntries::class],
		];

		if (!is_a($classGiven, $params[$index][1], TRUE)) {
			throw new InvalidArgumentException(sprintf(
				"%s parameter of association for '%s' must be instance of '%s'.",
				$params[$index][0],
				$association,
				$params[$index][1]
			));
		}
	}


	/**
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name)
	{
		$calledClass = static::class;

		if (!isset(self::$parsedAssociations[$calledClass][$name]) || isset($this->initializedAssociations[$name])) {
			return $this->readData($name);
		}

		$this->initializedAssociations[$name] = TRUE;

		$association = self::$parsedAssociations[$calledClass][$name];

		$data = $association[self::INDEX_EMBEDDING] !== FALSE
			? $this->readEmbeddedEntry($association[self::INDEX_EMBEDDING])
			: $this->readData($name);

		if ($data === NULL || ($association[self::INDEX_NULLABLE] && static::isEmpty($data))) {
			return $this->data[$name] = NULL;
		}

		$entryClass = $association[self::INDEX_ENTRYCLASS];
		$entriesClass = $association[self::INDEX_ENTRIESCLASS];

		return $this->data[$name] = $association[self::INDEX_MULTIPLICITY]
			? new $entriesClass($data, $entryClass)
			: new $entryClass($data, $entriesClass);
	}


	public function __wakeup()
	{
		$this->initParsedAssociations();
	}


	/**
	 * @param string $name
	 * @return bool
	 */
	public function __isset($name)
	{
		return isset($this->data[$name]);
	}


	/**
	 * @param mixed $value
	 * @return bool
	 */
	protected static function isEmpty($value)
	{
		return empty($value);
	}


	/**
	 * @param string $prefix
	 * @return array|NULL
	 */
	private function readEmbeddedEntry($prefix)
	{
		$values = [];
		$isEmpty = TRUE;
		foreach ($this->data as $field => $value) {
			if (strpos($field, $prefix) !== 0 || strlen($field) <= strlen($prefix)) {
				continue;
			}
			$values[substr($field, strlen($prefix))] = $value;

			if ($value !== NULL) {
				$isEmpty = FALSE;
			}
		}

		return $isEmpty ? NULL : $values; // unfortunately this is still just estimation
	}


	/**
	 * @param string $field
	 * @return mixed
	 */
	private function readData($field)
	{
		if (!array_key_exists($field, $this->data)) {
			throw new InvalidArgumentException(sprintf(
			    "Object '%s' is missing field '%s'.",
                static::class,
                $field
            ));
		}

		return $this->data[$field];
	}


	private function initParsedAssociations()
	{
        $calledClass = static::class;

		if (!array_key_exists($calledClass, self::$parsedAssociations)) {
			self::parseAssociations($calledClass);
		}
	}

}
