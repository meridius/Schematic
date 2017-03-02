<?php

namespace SchematicTests;

require_once __DIR__ . '/bootstrap.php';

use Schematic\Entries;
use Schematic\Entry;
use Tester\Assert;
use Tester\TestCase;


/**
 * @testCase
 */
class IndexedEntriesTest extends TestCase
{
	const SERVICE_3 = 3;
	const SERVICE_7 = 7;
	const SERVICE_9 = 9;

	private $data = [
		'services' => [
			self::SERVICE_3 => [
				'ENABLED' => 'Y',
			],
			self::SERVICE_7 => [
				'ENABLED' => 'N',
				'LABEL' => 'Something',
			],
			self::SERVICE_9 => [
				'ENABLED' => 'Y',
				'LABEL' => 'Something something',
			],
		],
		'supplierName' => 'Some name',
	];


	public function testIndexes()
	{
		$productInfo = new ProductInfo($this->data);

		$status = $this->getServiceSevenStatus($productInfo->services);
		Assert::same('N', $status);

		Assert::true($productInfo->services->isServiceEnabled(self::SERVICE_9));
		Assert::false($productInfo->services->isServiceEnabled(self::SERVICE_7));
	}


	public function testInvalidAssociations()
	{
		Assert::exception(
			function () {
				new ProductInfoInvalidAssocFirst($this->data);
			},
			\InvalidArgumentException::class,
			"First parameter of association for 'services[]' must be instance of 'Schematic\\IEntry'."
		);

		Assert::exception(
			function () {
				new ProductInfoInvalidAssocSecond($this->data);
			},
			\InvalidArgumentException::class,
			"Second parameter of association for 'services[]' must be instance of 'Schematic\\IEntries'."
		);

		Assert::exception(
			function () {
				new ProductInfoInvalidAssocNumberOfParams($this->data);
			},
			\InvalidArgumentException::class,
			"Number of custom association parameters for 'services[]' must be exactly 2."
		);
	}


	private function getServiceSevenStatus(ProductServices $productServices)
	{
		$service = $productServices->get(self::SERVICE_7);
		Assert::type(ProductService::class, $service);

		return $service->ENABLED;
	}
}


/**
 * @property-read ProductServices services
 * @property-read string supplierName
 */
class ProductInfo extends Entry
{
	protected static $associations = [
		'services[]' => [
			ProductService::class,
			ProductServices::class,
		],
	];
}


/**
 * @property-read ProductServices services
 * @property-read string supplierName
 */
class ProductInfoInvalidAssocFirst extends Entry
{
	protected static $associations = [
		'services[]' => [
			'foo',
			ProductServices::class,
		],
	];
}


/**
 * @property-read ProductServices services
 * @property-read string supplierName
 */
class ProductInfoInvalidAssocSecond extends Entry
{
	protected static $associations = [
		'services[]' => [
			ProductService::class,
			'foo',
		],
	];
}


/**
 * @property-read ProductServices services
 * @property-read string supplierName
 */
class ProductInfoInvalidAssocNumberOfParams extends Entry
{
	protected static $associations = [
		'services[]' => [
			ProductService::class,
		],
	];
}


/**
 * This class is defined so typehinting could be used for whole collection (Entries)
 */
class ProductServices extends Entries
{
	/**
	 * @param int $key
	 * @return ProductService
	 */
	public function get($key)
	{
		return parent::get($key);
	}


	public function isServiceEnabled($key)
	{
		return $this->has($key) && $this->get($key)->ENABLED === 'Y';
	}
}


/**
 * @property-read string ENABLED
 * @property-read string LABEL
 */
class ProductService extends Entry
{
}


(new IndexedEntriesTest())->run();
