<?php

include "../bootstrap.php";

use \Tester\Assert;
use \OndraKoupil\Testing\Assert as OKAssert;
use \OndraKoupil\Models\RelationControllerWithData;


class RelationControllerWithDataTest extends \OndraKoupil\Testing\NetteDatabaseTestCase {

	/**
	 * @var RelationControllerWithData
	 */
	protected $rc;

	function setUp() {
		parent::setUp();
		$this->rc=new RelationControllerWithData($this->db,"relationcontroller_with_data_test","idprodukt","idkategorie",array("valid","priorita","nazev"));
	}

	function testBasics() {
		Assert::type('\Nette\Database\Context', $this->rc->getDb());
		Assert::type('\Nette\Database\Table\Selection', $this->rc->getTable());
		OKAssert::arrayEqual(array("valid","priorita","nazev"), $this->rc->getDataCols());
	}

	function testGet() {

		$out=$this->rc->getProdukt(8);
		OKAssert::arrayEqual(array(1,3,10), array_keys($out));
		Assert::equal(array("valid"=>'0',"priorita"=>'8',"nazev"=>"admin"), $out[3]);
		Assert::equal(array("valid"=>'0',"priorita"=>'-5',"nazev"=>"hi'tech"), $out[10]);
		$this->assertQueriesSince(1);

		$out=$this->rc->getProdukt(8,"valid");
		OKAssert::arrayEqual(array(1,3,10), array_keys($out));
		Assert::equal(array(1=>'1',3=>'0',10=>'0'), $out);
		$this->assertQueriesSince(1);

		$out=$this->rc->getProdukt(8,array("priorita","nazev"));
		OKAssert::arrayEqual(array(1,3,10), array_keys($out));
		Assert::equal(array("nazev"=>"admin","priorita"=>'8'), $out[3]);
		$this->assertQueriesSince(1);

		$out=$this->rc->getKategorie(array(1,2,3),true);
		$this->assertQueriesSince(2);
		Assert::equal(array(), $out[2]);
		OKAssert::arrayEqual(array(1,2,5,8), $out[1]);
		OKAssert::arrayEqual(array(2,8), $out[3]);

		$out=$this->rc->getKategorie(false,true);
		$this->assertQueriesSince(3);
		OKAssert::arrayEqual(array(2,8), $out[3]);
		OKAssert::arrayEqual(array(8), $out[10]);

		$out=$this->rc->getKategorie(3,"valid");
		$this->assertQueriesSince(3);
		OKAssert::arrayEqual(array(2=>'1',8=>'0'), $out);

		// Nesmysl
		$this->resetQueriesCount();
		$this->rc->clearProdukt();
		$out=$this->rc->getProdukt(100);
		Assert::same(array(),$out);
	}

	function testList() {
		$out=$this->rc->listProdukt(8);
		OKAssert::arrayEqual(array(1,3,10), $out);

		$out=$this->rc->listProdukt(false);
		Assert::equal(4,count($out));
		OKAssert::arrayEqual(array(1,3,10), $out[8]);
		OKAssert::arrayEqual(array(1), $out[1]);
		OKAssert::arrayEqual(array(1,3), $out[2]);
		$this->assertQueriesSince(2);

		$out=$this->rc->listProdukt(array(1,2));
		Assert::equal(2,count($out));
		OKAssert::arrayEqual(array(1,3), $out[2]);
		OKAssert::arrayEqual(array(1), $out[1]);
		$this->assertQueriesSince(2);

		$out=$this->rc->listKategorie(3);
		OKAssert::arrayEqual(array(2,8), $out);
		$this->assertQueriesSince(3);
	}

	function testData() {
		$data = $this->rc->dataProdukt(8,3);
		OKAssert::arrayEqual(array(
			"valid" => '0',
			"priorita" => '8',
			"nazev" => "admin"
		), $data);

		$data = $this->rc->dataKategorie(3,2, array("valid", "priorita"));
		OKAssert::arrayEqual(array(
			"valid" => '1',
			"priorita" => '0'
		), $data);

		$data = $this->rc->dataKategorie(10,8, "priorita");
		Assert::equal('-5', $data);

		$data = $this->rc->dataKategorie(3,2, "valid");
		Assert::equal('1', $data);

		$this->assertQueriesCount(3);

		$data = $this->rc->dataKategorie(1,6);
		Assert::same(null, $data);
		$data = $this->rc->dataKategorie(1,6, "valid");
		Assert::same(null, $data);
		$data = $this->rc->dataKategorie(1,6, array("valid","priorita"));
		Assert::same(null, $data);
		$data = $this->rc->dataKategorie(100,100);
		Assert::same(null, $data);

	}

	function testSorting() {
		$this->rc->setupOrder("priorita DESC", "priorita");

		Assert::equal(
			array(3, 1, 10),
			$this->rc->listProdukt(8)
		);

		Assert::equal(
			array(3, 1, 10),
			array_keys($this->rc->getProdukt(8))
		);

		Assert::equal(
			array(2, 8, 1, 5),
			$this->rc->listKategorie(1)
		);

	}

	function testSet() {

		$out=$this->rc->getProdukt(1,"nazev");
		Assert::equal(array(1=>""), $out);
		$this->assertQueriesSince(1);

		$this->resetQueriesCount();

		$out=$this->rc->setProdukt(1,array(5=>array("valid"=>0,"priorita"=>-4,"nazev"=>"alt'\"name"), 3=>array("valid"=>1,"priorita"=>5,"nazev"=>"secondname") ));
		Assert::same($this->rc,$out);
		$this->assertQueriesSince(2);

		$out=$this->rc->getProdukt(1,"nazev");
		Assert::equal(array(5=>"alt'\"name",3=>"secondname"), $out);
		$this->assertQueriesSince(2);

		$out=$this->rc->getKategorie(5);
		Assert::equal(array(1=>array("valid"=>'0',"priorita"=>'-4',"nazev"=>"alt'\"name")), $out);
		$this->assertQueriesSince(3);

		// Skoro Delete
		$this->resetQueriesCount();
		$this->rc->setProdukt(5,array());
		$out=$this->rc->getProdukt(5);
		Assert::same(array(),$out);
		$this->assertQueriesSince(1);

		// Default values
		$this->resetQueriesCount();
		$this->rc->setProdukt(5,array(1=>array("nazev"=>"nullnamed")));
		$this->assertQueriesSince(2);
		$this->rc->clearProdukt();
		$out=$this->rc->getProdukt(5);
		Assert::equal(array(1=>array("valid"=>"1","nazev"=>"nullnamed","priorita"=>"0")), $out);

		// Null values
		$this->rc->setProdukt(5,array(1=>array("priorita"=>-5)));
		$this->rc->clearProdukt();
		$out=$this->rc->getProdukt(5);
		Assert::equal(array(1=>array("valid"=>"1","priorita"=>"-5","nazev"=>null)), $out);
	}

	function testAdd() {

		$out=$this->rc->getProdukt(5);
		Assert::equal(count($out),1);
		Assert::equal("5",$out[1]["priorita"]);
		$this->assertQueriesSince(1);

		$this->resetQueriesCount();

		$this->rc->addProdukt(5,array(3=>array("priorita"=>10)));

		$out=$this->rc->getProdukt(5);
		Assert::equal(count($out),2);
		Assert::equal(array("valid"=>"0","priorita"=>"5","nazev"=>"alt"), $out[1]);
		Assert::equal(array("valid"=>"1","priorita"=>"10","nazev"=>null), $out[3]);
		$this->assertQueriesSince(3);

		$this->resetQueriesCount();

		$this->rc->addProdukt(5,array(3=>array("valid"=>5)));
		$out=$this->rc->getProdukt(5);
		Assert::equal(count($out),2);
		Assert::equal(array("valid"=>"0","priorita"=>"5","nazev"=>"alt"), $out[1]);
		Assert::equal(array("valid"=>"5","priorita"=>"0","nazev"=>null), $out[3]);
		$this->assertQueriesSince(3);

	}

	function testDelete() {
		$out=$this->rc->getKategorie(3);
		Assert::equal(2, count($out));

		$this->resetQueriesCount();
		$out=$this->rc->deleteKategorie(3,2);
		Assert::same($out,$this->rc);

		$out=$this->rc->getKategorie(3);
		Assert::equal(1, count($out));
		Assert::equal(array("valid"=>"0","priorita"=>"8","nazev"=>"admin"), $out[8]);
		$this->assertQueriesSince(1);

		$this->resetQueriesCount();
		$this->rc->deleteKategorie(3);
		$out=$this->rc->getKategorie(3);
		Assert::equal(0, count($out));
		$this->assertQueriesSince(1);
	}

	function testDuplicate() {
		$out=$this->rc->getProdukt(10);
		Assert::same(array(), $out);

		$out=$this->rc->duplicateProdukt(2,10);
		Assert::same($this->rc,$out);

		$out=$this->rc->getProdukt(10);
		Assert::equal(2, count($out));
		Assert::equal(array("valid"=>"1","priorita"=>"-1","nazev"=>""), $out[1]);
		Assert::equal(array("valid"=>"1","priorita"=>"0","nazev"=>""), $out[3]);
	}

	function testCount() {
		$out=$this->rc->countKategorie(10);
		Assert::equal(1,$out);
		$this->assertQueriesSince(1);

		$out=$this->rc->countKategorie(3);
		Assert::equal(2,$out);
		$this->assertQueriesSince(2);

		$this->rc->countKategorie(10);
		$this->assertQueriesSince(2);

	}

	function testIs() {
		Assert::true($this->rc->hasProdukt(1,1));
		Assert::true($this->rc->isKategorie(3,8));
		Assert::true($this->rc->isKategorie(3,2));
		$this->assertQueriesSince(2);

		$this->resetQueriesCount();
		Assert::false($this->rc->isKategorie(3,100));
		Assert::true($this->rc->isKategorie(10,8));
		Assert::false($this->rc->isKategorie(10,80));
		Assert::false($this->rc->isKategorie(10,100));
		Assert::false($this->rc->isKategorie(10,101));
		Assert::false($this->rc->isKategorie(10,102));
		Assert::false($this->rc->isKategorie(10,103));
		$this->assertQueriesSince(1);
	}

	function testUpdate() {
		$out=$this->rc->getProdukt(8);
		Assert::equal(3, count($out));
		Assert::equal("admin", $out[3]["nazev"]);
		Assert::equal("8", $out[3]["priorita"]);
		Assert::equal("0", $out[3]["valid"]);

		$this->rc->updateProdukt(8,3,array("priorita"=>9));
		$out=$this->rc->getProdukt(8);
		Assert::equal("admin", $out[3]["nazev"]);
		Assert::equal(9, $out[3]["priorita"]*1);
		Assert::equal("0", $out[3]["valid"]);

		$this->rc->clearProdukt(8);
		$out=$this->rc->getProdukt(8);
		Assert::equal("admin", $out[3]["nazev"]);
		Assert::equal(9, $out[3]["priorita"]*1);
		Assert::equal("0", $out[3]["valid"]);

	}

}

$testCase=new RelationControllerWithDataTest(require("../db.php"), "initdb-rc-withdata.sql");
$testCase->run();