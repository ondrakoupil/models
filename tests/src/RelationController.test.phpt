<?php


include "../bootstrap.php";

use \Tester\Assert;
use \OndraKoupil\Testing\Assert as OKAssert;
use \OndraKoupil\Models\RelationController;


class RelationControllerTest extends \OndraKoupil\Testing\NetteDatabaseTestCase {

	/**
	 * @var RelationController
	 */
	protected $rc;

	function setUp() {
		parent::setUp();
		$this->rc=new RelationController($this->db, "relationcontroller_test","idprodukt","idkategorie");
	}

	function testBasics() {
		Assert::type('\Nette\Database\Context', $this->rc->getDb());
		Assert::type('\Nette\Database\Table\Selection', $this->rc->getTable());
	}

	function testGet() {
		$n=count($this->queries);
		$out=$this->rc->getProdukt(1);
		OKAssert::arrayEqual(array(1,3,7,8), $out);
		Assert::equal(1, count($this->queries)-$n);

		$out=$this->rc->getProdukt(1);
		OKAssert::arrayEqual(array(1,3,7,8), $out);
		Assert::equal(1, count($this->queries)-$n);

		$out=$this->rc->getProdukt(4);
		OKAssert::arrayEqual(array(4), $out);
		Assert::equal(2, count($this->queries)-$n);

		$out=$this->rc->getProdukt(100);
		Assert::same(array(),$out);

		$n=count($this->queries);
		$out=$this->rc->getProdukt(array(1,4));
		OKAssert::arrayEqual(array(1=>array(1,3,7,8),4=>array(4)), $out);
		Assert::equal(0, count($this->queries)-$n);

		$out=$this->rc->getProdukt(array(1,4,5));
		OKAssert::arrayEqual(array(1=>array(1,3,7,8),4=>array(4),5=>array(3,7,8)), $out);
		Assert::equal(1, count($this->queries)-$n);

		$n=count($this->queries);
		$out=$this->rc->getKategorie(array(1,4));
		OKAssert::arrayEqual(array(1=>array(1),4=>array(2,4)), $out);
		Assert::equal(1, count($this->queries)-$n);
	}

	function testSet() {
		$n=count($this->queries);
		$out=$this->rc->setProdukt(1,array(1,2,3,4));
		Assert::same($this->rc,$out);
		Assert::equal(2,count($this->queries)-$n);

		$out=$this->rc->getProdukt(1);
		OKAssert::arrayEqual(array(1,2,3,4), $out);
		Assert::equal(2,count($this->queries)-$n);

		$out=$this->rc->getKategorie(2);
		OKAssert::arrayEqual(array(1), $out);
	}

	function testAdd() {

		$out=$this->rc->getKategorie(1);
		OKAssert::arrayEqual(array(1), $out);

		$n=count($this->queries);

		$out=$this->rc->addProdukt(2,array(1,2,3,4));
		Assert::same($this->rc,$out);
		Assert::equal(1,count($this->queries)-$n);

		$out=$this->rc->getProdukt(2);
		OKAssert::arrayEqual(array(1,2,3,4,8), $out);
		Assert::equal(2,count($this->queries)-$n);

		$this->rc->addProdukt(2,array(100,101));
		$out=$this->rc->getProdukt(2);
		OKAssert::arrayEqual(array(1,2,3,4,8,100,101), $out);
		Assert::equal(3,count($this->queries)-$n);

		$out=$this->rc->getKategorie(1);
		OKAssert::arrayEqual(array(1,2), $out);

	}

	function testDelete() {
		$out=$this->rc->getKategorie(3);
		OKAssert::arrayEqual(array(1,5), $out);

		$out=$this->rc->getProdukt(1);
		OKAssert::arrayEqual(array(1,3,7,8), $out);

		$out=$this->rc->deleteProdukt(1);
		Assert::same($this->rc,$out);

		$out=$this->rc->getKategorie(3);
		OKAssert::arrayEqual(array(5), $out);

		$out=$this->rc->getProdukt(1);
		Assert::same(array(),$out);

		$out=$this->rc->getProdukt(5);
		OKAssert::arrayEqual(array(3,7,8), $out);

		$this->rc->deleteProdukt(5,array(3,8));
		$out=$this->rc->getProdukt(5);
		OKAssert::arrayEqual(array(7), $out);

		$this->rc->deleteProdukt(5,array(3,8));
		$out=$this->rc->getProdukt(5);
		OKAssert::arrayEqual(array(7), $out);

		$this->rc->addProdukt(5,array(3,8));
		$this->rc->clearProdukt(5);
		$this->rc->deleteProdukt(5, 3);
		$out=$this->rc->getProdukt(5);
		OKAssert::arrayEqual(array(7, 8), $out);

	}

	function testDuplicate() {
		$out=$this->rc->getProdukt(10);
		Assert::same(array(), $out);

		$out=$this->rc->duplicateProdukt(7,10);
		Assert::same($this->rc,$out);

		$out=$this->rc->getProdukt(10);
		OKAssert::arrayEqual(array(7,8), $out);
	}


	function testCache() {

		$this->rc->cacheProdukt(2);
		OKAssert::arrayEqual(array(4,8), $this->rc->getProdukt(2));

		try {
			$this->db->table("relationcontroller_test")->insert(array("idprodukt"=>"2","idkategorie"=>"1"));
		} catch (\PDOException $e) {
			// Might fails because NDB tries to fetch column ID after inserting
			;
		}

		OKAssert::arrayEqual(array(4,8), $this->rc->getProdukt(2));

		$this->rc->clearProdukt(2);
		OKAssert::arrayEqual(array(1,4,8), $this->rc->getProdukt(2));

		$this->rc->loadProdukt(false);

		$n=count($this->queries);

		$this->rc->getProdukt(array(1,2));
		$this->rc->getProdukt(1);
		$this->rc->getProdukt(5);
		$this->rc->getProdukt(7);
		$this->rc->getProdukt(1000);

		Assert::same(0,count($this->queries)-$n);

		$this->rc->clearProdukt(5);

		Assert::same(0,count($this->queries)-$n);

		$out=$this->rc->getProdukt(5);;
		OKAssert::arrayEqual(array(3,7,8), $out);
		Assert::same(1,count($this->queries)-$n);
	}

	function testCount() {
		$out=$this->rc->countKategorie(7);
		Assert::equal(3,$out);

		$out=$this->rc->countKategorie(1);
		Assert::equal(1,$out);

		$out=$this->rc->countKategorie(100);
		Assert::equal(0,$out);

		$out=$this->rc->countKategorie(array(7,1,100));
		Assert::equal(array(
			7=>3,
			1=>1,
			100=>0
		),$out);

	}

	function testIs() {
		Assert::true($this->rc->isProdukt(1,3));
		Assert::false($this->rc->isProdukt(1,4));
		Assert::false($this->rc->isProdukt(100,4));
		Assert::false($this->rc->hasKategorie(4,1));
		Assert::true($this->rc->hasKategorie(4,2));
		Assert::true($this->rc->is(1,8));
		Assert::false($this->rc->is(5,1));

		$this->rc->clearProdukt();
		$this->rc->clearKategorie();
		$this->resetQueriesCount();
		$this->rc->isProdukt(1,6);
		$this->rc->isProdukt(1,7);
		$this->rc->isProdukt(1,8);
		$this->assertQueriesSince(1);
		$this->rc->isKategorie(4,1);
		$this->rc->isKategorie(4,2);
		$this->rc->isKategorie(4,3);
		$this->assertQueriesSince(2);

	}
}

$testCase=new RelationControllerTest(require("../db.php"), "initdb-rc.sql");
$testCase->run();