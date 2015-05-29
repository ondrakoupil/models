<?php

include "../bootstrap.php";

use \Tester\TestCase;
use \Tester\Assert;
use \OndraKoupil\Testing\NetteDatabaseTestCase;
use \OndraKoupil\Models\DataManager;
use \OndraKoupil\Models\Exceptions\StopActionException;

class DataManagerBasicsTest extends NetteDatabaseTestCase {

	/**
	 * @var DataManager
	 */
	protected $dm;

	function setUp() {
		parent::setUp();
		$this->dm=new DataManager($this->db,"datamanager_test_with_name");
	}

	function testBasics() {

		// Tests return types
		Assert::type("\Nette\Database\Context", $this->dm->getDb());
		Assert::type("\Nette\Database\Table\Selection", $this->dm->getTable());

		// Tests correct content of testing database table
		$count=$this->dm->getTable()->count();
		Assert::equal(3, $count); // There should be three rows

		// getTableName
		Assert::equal("datamanager_test_with_name",$this->dm->getTableName());

		// isValueAnId()
		Assert::true($this->dm->isValueAnId(2));
		Assert::true($this->dm->isValueAnId("30"));
		Assert::false($this->dm->isValueAnId("a"));
		Assert::false($this->dm->isValueAnId(-2));
	}


	function testFindBy() {
		$a=$this->dm->findBy("name","One")->fetch();
		Assert::equal($a["name"], "One");

		$a=$this->dm->findBy("name = ?","Two")->fetch();
		Assert::equal($a["name"], "Two");

		$a=$this->dm->findBy(array("name"=>"Three"))->fetch();
		Assert::equal($a["name"], "Three");

	}

	function testName() {
		$this->dm=new DataManager($this->db,"datamanager_test_with_name");
		$data=$this->dm->getTable()->where("id",2)->fetch();

		$name=$this->dm->name($data);
		Assert::equal("Two", $name);

		$name=$this->dm->name(array("id"=>"6","some"=>"else"));
		Assert::equal("ID 6", $name);

		$name=$this->dm->name(array("lorem"=>"ipsum","some"=>"else"));
		Assert::equal("Unknown item", $name);
	}

	function testFetch() {
		$dm=$this->dm;

		Assert::equal( "Two" , $dm->fetch(2, "name") );

		$a=$dm->fetch(3);
		Assert::equal("Three", $a["name"] );
		Assert::true(isset($a["desc"]));

		$a=$dm->fetch(array(1,3),"name");
		Assert::equal("One", $a[1] );
		Assert::equal("Three", $a[3] );

		$a=$dm->fetch(array(1,3),array("name","number"));
		Assert::equal("One", $a[1]["name"] );
		Assert::equal(3, $a[3]["number"] );
		Assert::equal("Three", $a[3]["name"] );
		Assert::false(isset($a[1]["desc"]));

		$a=$dm->fetch(false,array("name","number"));
		Assert::equal(3, count($a));
		Assert::equal("One", $a[1]["name"]);
		Assert::equal(2, $a[2]["number"]);

		$a=$dm->fetch();
		Assert::equal(3, count($a));
		Assert::equal("One", $a[1]["name"]);
		Assert::equal(3, $a[3]["number"]);
		Assert::equal("Second number", $a[2]["desc"]);

		$a=$dm->fetchWhere(array("name"=>"One"));
		Assert::equal(1, $a[1]["id"]);
		Assert::equal(1, $a[1]["number"]);

		$a=$dm->fetchWhere(array("name"=>"Two"),"number");
		Assert::equal(2, $a[2]);

		$a=$dm->fetchWhere(array("name"=>"Two"),array("number","name"));
		Assert::equal(2, $a[2]["number"]);
		Assert::equal("Two", $a[2]["name"]);
		Assert::false(isset($a[2]["desc"]));

	}

	function testCache() {
		$dm=$this->dm;

		$dm->cache(1);
		Assert::equal(1, count($dm->getCachedData()));

		$dm->cache(1);
		Assert::equal(1, count($dm->getCachedData()));

		$dm->cache(2);
		Assert::equal(2, count($dm->getCachedData()));

		$dm->clearCache(1);
		Assert::equal(1, count($dm->getCachedData()));

		$dm->clearCache();
		Assert::equal(0, count($dm->getCachedData()));

		$dm->cache(array(1,3));
		Assert::equal(2, count($dm->getCachedData()));
		$a=$dm->getCachedData();
		Assert::equal("Three", $a[3]["name"]);

		$dm->clearCache();
		Assert::equal(array(), $dm->getCachedData());

		$dm->cache();
		Assert::equal(3, count($dm->getCachedData()));
		$a=$dm->getCachedData();
		Assert::equal("Two", $a[2]["name"]);
		Assert::equal("Dolor sit amen", $a[2]["long_something"]);

		$dm->clearCache();
		Assert::equal(false,$dm->getCacheableCols());
		$dm->setCacheableCols(array("name","number","desc"));
		Assert::contains("number",$dm->getCacheableCols());
		Assert::notContains("long_something",$dm->getCacheableCols());
		$dm->cache();
		$a=$dm->getCachedData();
		Assert::false(isset($a[2]["long_something"]));
		Assert::true(isset($a[2]["name"]));

		$dm->clearCache();
		$dm->setCacheableCols(array());
		Assert::equal(array(),$dm->getCacheableCols());
		$dm->addCacheableCols("number");
		$dm->cache();
		$a=$dm->getCachedData();
		Assert::equal(array("number"),$dm->getCacheableCols());
		Assert::true(isset($a[3]["number"]));
		Assert::true(isset($a[3]["id"]));
		Assert::false(isset($a[3]["long_something"]));
		Assert::false(isset($a[3]["name"]));

		$dm->clearCache();
		$qn=count($this->queries);
		$dm->cache(1);
		$qn2=count($this->queries);
		Assert::equal($qn2-$qn, 1);
		$dm->cache(1);
		$qn2=count($this->queries);
		Assert::equal($qn2-$qn, 1);
		$dm->cache(2);
		$qn2=count($this->queries);
		Assert::equal($qn2-$qn, 2);
		$dm->cache(array(1,2));
		$qn2=count($this->queries);
		Assert::equal($qn2-$qn, 2);
		$dm->cache(3);
		$qn2=count($this->queries);
		Assert::equal($qn2-$qn, 3);
		$dm->cache(4);
		$qn2=count($this->queries);
		Assert::equal($qn2-$qn, 4);
		$dm->cache(4);
		$qn2=count($this->queries);
		Assert::equal($qn2-$qn, 4);
		$dm->cache();
		$qn2=count($this->queries);
		Assert::equal($qn2-$qn, 5);
		$dm->cache(array(4,1,2,3));
		$qn2=count($this->queries);
		Assert::equal($qn2-$qn, 5);
		$dm->cache(5);
		$qn2=count($this->queries);
		Assert::equal($qn2-$qn, 5);
		$dm->cache(1);
		$qn2=count($this->queries);
		Assert::equal($qn2-$qn, 5);
		$dm->cache();
		$qn2=count($this->queries);
		Assert::equal($qn2-$qn, 5);
	}

	function testUpdateCache() {
		$dm=$this->dm;

		$qn=count($this->queries);
		$dm->cache(2);
		Assert::equal(2, $dm->get(2,"number"));
		$qn2=count($this->queries);
		Assert::equal(1, $qn2-$qn);

		$qn2=count($this->queries);
		Assert::equal(2, $dm->get(2,"number"));
		Assert::equal(1, $qn2-$qn);

		$dm->updateCache(2, array("number"=>5));
		Assert::equal(5, $dm->get(2,"number"));
		$qn2=count($this->queries);
		Assert::equal(1, $qn2-$qn);

		$dm->updateCache(3, array("number"=>10));
		$qn2=count($this->queries);
		Assert::equal(1, $qn2-$qn);
		Assert::equal(3, $dm->get(3,"number"));
		$qn2=count($this->queries);
		Assert::equal(2, $qn2-$qn);
	}

	function testGet() {
		$n=count($this->queries);
		$dm=$this->dm;
		$data=$dm->get(array(1,3));
		Assert::equal( 1, count($this->queries) - $n );

		Assert::equal("Three", $data[3]["name"]);
		Assert::equal("One", $data[1]["name"]);
		Assert::false(isset($data[2]));

		$data=$dm->get(3,"number");
		Assert::equal(3, $data);
		Assert::equal( 1, count($this->queries) - $n );

		$data=$dm->get(false,"name");
		Assert::equal( 2, count($this->queries) - $n );
		Assert::equal( array(1=>"One",2=>"Two",3=>"Three"), $data );

		$data=$dm->get(array(1,2),array("name","number"));
		Assert::equal( 2, count($this->queries) - $n );
		Assert::equal( array(
			1=>array("name"=>"One","number"=>1),
			2=>array("name"=>"Two","number"=>2)
		), $data );

		$data=$dm->get(2);
		Assert::equal( 2, $data["number"]);
		Assert::equal( 2, $data["id"]);
		Assert::equal( "Two", $data["name"]);
	}

	function testGetNull() {
		$dm=$this->dm;

		$out=$dm->get(array(null,null,null),"name");
		Assert::equal(array(null=>null),$out);
		$this->assertQueriesCount(0);

		$out=$dm->get(array(null,null,2),"name");
		$this->assertQueriesCount(1);
		Assert::equal(array(null=>null,2=>"Two"),$out);
	}

	function testExists() {
		$dm=$this->dm;

		$n=count($this->queries);
		Assert::true($dm->exists(1));
		Assert::false($dm->exists(5));
		Assert::equal(2,count($this->queries)-$n);

		Assert::equal(array(1=>true,2=>true,4=>false,5=>false),$dm->exists(array(1,2,4,5)));
		Assert::equal(3,count($this->queries)-$n);

		$dm->cache(2);
		Assert::equal(4,count($this->queries)-$n);
		Assert::true($dm->exists(2));
		Assert::equal(4,count($this->queries)-$n);

		$dm->cache();
		Assert::equal(5,count($this->queries)-$n);
		Assert::true($dm->exists(1));
		Assert::false($dm->exists(5));
		Assert::equal(array(3=>true,4=>false,9=>false),$dm->exists(array(3,4,9)));
		Assert::equal(5,count($this->queries)-$n);
	}

	function testGetAllIds() {
		$dm=$this->dm;
		$allIds=array(1,2,3);

		$n=count($this->queries);
		Assert::equal( $allIds , $dm->getAllIds() );
		Assert::equal( 1 , count($this->queries)-$n );

		$dm->cache();
		Assert::equal( 2 , count($this->queries)-$n );
		Assert::equal( $allIds , $dm->getAllIds() );
		Assert::equal( 2 , count($this->queries)-$n );
	}

	function testGetRandomId() {
		$dm=$this->dm;
		$allIds=array(1,2,3);

		$n=count($this->queries);
		Assert::true( in_array($dm->getRandomId(),$allIds) );
		Assert::equal( 1 , count($this->queries)-$n );
		Assert::true( in_array($dm->getRandomId(),$allIds) );
		Assert::equal( 2 , count($this->queries)-$n );
		Assert::equal( 2 , count($dm->getRandomId(2)) );
		Assert::type("array", $dm->getRandomId(2) );
		Assert::equal( 3 , count($dm->getRandomId(5)) );
		Assert::equal( 5 , count($this->queries)-$n );

		$dm->cache();
		Assert::true( in_array($dm->getRandomId(),$allIds) );
		Assert::true( in_array($dm->getRandomId(),$allIds) );
		Assert::true( in_array($dm->getRandomId(),$allIds) );
		Assert::type("array", $dm->getRandomId(2) );
		Assert::type("int", $dm->getRandomId() );
		Assert::equal( 2 , count($dm->getRandomId(2)) );
		Assert::equal( 3 , count($dm->getRandomId(5)) );
		Assert::equal( 6 , count($this->queries)-$n );
	}

	function testGetIdsWhere() {
		$dm=$this->dm;
		$n=count($this->queries);

		$a=$dm->getIdsWhere(array("number"=>2));
		Assert::equal(array(2), $a);

		$a=$dm->getIdsWhere("number <= 2");
		Assert::equal(array(1,2), $a);

		$a=$dm->getIdsWhere("number >= ?",2);
		Assert::equal(array(2,3), $a);

		$a=$dm->getIdsWhere("number = 5");
		Assert::equal(array(), $a);

		Assert::equal(4,count($this->queries) - $n);
	}

	function testDelete() {
		$dm=$this->dm;

		$dm->cache();
		$out=$dm->delete(1);
		Assert::false($dm->exists(1));
		Assert::falsey($dm->getTable()->where("id",1)->fetch());
		Assert::equal(1, $out);

		$out=$dm->delete(array(1,2,3));
		Assert::falsey($dm->getTable()->fetch());
		Assert::equal(2, $out);

		$out=$dm->delete(array(1,2,3));
		Assert::falsey($dm->getTable()->fetch());
		Assert::equal(0, $out);
	}

	function testOnDelete() {
		$dm=$this->dm;

		$calledOnDelete=array();
		$dm->onDelete[]=function($id,$dataManager) use ($dm,&$calledOnDelete) {
			Assert::same($dm, $dataManager);
			$calledOnDelete[]=$id;
		};

		$dm->delete(2);
		Assert::equal(array(2),$calledOnDelete);

		$dm->delete(array(1,2,3,4,5));
		Assert::equal(array(2,1,2,3,4,5),$calledOnDelete);

		Assert::falsey($dm->getTable()->fetch());
	}

	function testOnBeforeDelete() {
		$dm=$this->dm;

		$calledOnBeforeDelete=array();
		$shouldPrevent=false;

		$dm->onBeforeDelete[]=function($id,$dataManager) use ($dm,&$calledOnBeforeDelete,&$shouldPrevent) {
			$calledOnBeforeDelete[]=$id;
			if ($shouldPrevent) {
				throw new StopActionException;
			}
		};

		$dm->delete(2);
		Assert::same(array(2),$calledOnBeforeDelete);
		Assert::falsey($dm->getTable()->where("id",2)->fetch());

		$shouldPrevent=true;

		$dm->delete(3);
		Assert::same(array(2,3),$calledOnBeforeDelete);
		Assert::truthy($dm->getTable()->where("id",3)->fetch());

		$shouldPrevent=false;

		$dm->delete(1);
		Assert::same(array(2,3,1),$calledOnBeforeDelete);
		Assert::falsey($dm->getTable()->where("id",1)->fetch());
	}

	function testSaveEdit() {
		$dm=$this->dm;

		$calledOnEdit=array();
		$calledOnBeforeEdit=array();
		$shouldPrevent=false;
		$assertedChanges=array();
		$assertedNewName="";
		$assertedOrigName="";

		$dm->onEdit[]=function($id,$changes,$dmI,$orig,$news) use ($dm,&$calledOnEdit,&$shouldPrevent,&$assertedChanges,&$assertedNewName,&$assertedOrigName) {
			Assert::same($dm, $dmI);
			Assert::equal($assertedChanges, $changes);
			Assert::equal($assertedNewName, $news["name"]);
			Assert::equal($assertedOrigName, $orig["name"]);
			$calledOnEdit[]=$id;
		};
		$dm->onBeforeEdit[]=function($id,$changes,$dmI,$orig,$news) use ($dm,&$calledOnBeforeEdit,&$shouldPrevent,&$assertedChanges,&$assertedNewName,&$assertedOrigName) {
			Assert::same($dm, $dmI);
			Assert::equal($assertedChanges, $changes);
			Assert::equal($assertedNewName, $news["name"]);
			Assert::equal($assertedOrigName, $orig["name"]);
			$calledOnBeforeEdit[]=$id;
			if ($shouldPrevent) throw new StopActionException;
		};

		$testCache=$dm->get(2);

		$assertedChanges=array("name"=>"Twenty-two","number"=>"22");
		$assertedNewName="Twenty-two";
		$assertedOrigName="Two";
		$out=$dm->save(2,array("name"=>"Twenty-two","number"=>"22"));
		Assert::equal(2,$out["id"]);
		Assert::equal("Twenty-two",$out["name"]);
		Assert::equal("Second number",$out["desc"]);
		Assert::equal(array(2),$calledOnBeforeEdit);
		Assert::equal(array(2),$calledOnEdit);
		$inDb=$dm->getTable()->where("id",2)->fetch();
		Assert::equal($inDb["name"],"Twenty-two");

		$shouldPrevent=true;

		$assertedChanges=array("name"=>"Twenty-three","number"=>"23");
		$assertedNewName="Twenty-three";
		$assertedOrigName="Three";
		$out=$dm->save(3,array("name"=>"Twenty-three","number"=>"23"));
		Assert::null($out);
		Assert::equal(array(2,3),$calledOnBeforeEdit);
		Assert::equal(array(2),$calledOnEdit);
		$inDb=$dm->getTable()->where("id",3)->fetch();
		Assert::equal($inDb["name"],"Three");

		Assert::equal("Two", $testCache["name"]);
		Assert::equal("Twenty-two", $dm->get(2,"name"));
	}

	function testSaveNew() {
		$dm=$this->dm;

		$calledOnNew=array();
		$calledOnBeforeNew=array();
		$shouldPrevent=false;
		$assertedNewName="";

		$dm->onNew[]=function($id,$changes,$dmI) use ($dm,&$calledOnNew,&$assertedNewName) {
			Assert::same($dm, $dmI);
			Assert::equal($assertedNewName, $changes["name"]);
			Assert::equal($id, $changes["id"]);
			$calledOnNew[]=$changes;
		};
		$dm->onBeforeNew[]=function($changes,$dmI) use ($dm,&$calledOnBeforeNew,&$shouldPrevent,&$assertedNewName) {
			Assert::same($dm, $dmI);
			Assert::equal($assertedNewName, $changes["name"]);
			$calledOnBeforeNew[]=$changes;
			if ($shouldPrevent) throw new StopActionException;
		};

		$assertedChanges=array("name"=>"Four","number"=>"4","desc"=>"F-f-f-four!");
		$assertedNewName="Four";
		$out=$dm->save(false,$assertedChanges);
		Assert::equal(4,$out["id"]);
		Assert::equal("Four",$out["name"]);
		Assert::equal("4",$calledOnBeforeNew[0]["number"]);
		Assert::equal(4,$calledOnNew[0]["number"]);
		$inDb=$dm->getTable()->where("id",4)->fetch();
		Assert::equal($inDb["name"],"Four");
		Assert::equal($inDb["long_something"],"");

		$shouldPrevent=true;

		$assertedChanges=array("name"=>"Five","number"=>"5","desc"=>"F-f-f-five!");
		$assertedNewName="Five";
		$out=$dm->save(false,$assertedChanges);
		Assert::null($out);
		Assert::equal("5",$calledOnBeforeNew[1]["number"]);
		Assert::false(isset($calledOnNew[1]["number"]));
		$inDb=$dm->getTable()->where("id",5)->fetch();
		Assert::falsey($inDb);

		// Insert instead of update
		$shouldPrevent=false;
		$assertedChanges=array("name"=>"Ten","number"=>"","desc"=>"");
		$assertedNewName="Ten";
		$out=$dm->save(10, array("name"=>"Ten"));
		Assert::equal("Ten", $out["name"]);
		$inDb=$dm->getTable()->where("id",10)->fetch();
		Assert::equal("Ten", $inDb["name"]);
		Assert::equal("Ten",$calledOnNew[1]["name"]);
		Assert::equal("Ten",$calledOnBeforeNew[2]["name"]);

		Assert::equal(5, count($dm->getAllIds()));
	}

	function testMassUpdate() {
		$dm=$this->dm;

		$testCache=$dm->get(array(1,2));
		$out=$dm->massUpdate(array(1,3,5), array("name"=>"Unnamed"));
		Assert::equal(2, $out);

		Assert::equal("One", $testCache[1]["name"]);
		Assert::equal("Unnamed", $dm->get(1,"name"));
		Assert::equal("Two", $dm->get(2,"name"));
		Assert::equal("Unnamed", $dm->get(3,"name"));

		Assert::equal(array(1,3),$dm->getIdsWhere("name","Unnamed"));
	}

	function testDbSchema() {
		$dm=$this->dm;

		$n=count($this->queries);

		Assert::same(array(
			"id",
			"name",
			"desc",
			"number",
			"long_something"
		), $dm->getDbColumns());

		Assert::same(array(
			"id"=>null,
			"name"=>"Unnamed",
			"desc"=>null,
			"number"=>"100",
			"long_something"=>null
		), $dm->getDbDefaults());

		Assert::same(array(
			"id"=>"",
			"name"=>"Some name",
			"desc"=>"",
			"number"=>"Some number",
			"long_something"=>"Is not cached"
		), $dm->getDbComments());

		Assert::same(array(
			"name"=>"Unnamed",
			"desc"=>null,
			"number"=>"100",
			"long_something"=>null
		), $dm->defaultData());

		Assert::equal(1, count($this->queries)-$n);
	}

	function testSort() {
		$dm=$this->dm;

		$sorted=$dm->sort();
		Assert::same(array(1,2,3), $sorted);

		$sorted=$dm->sort(array(3,1,2));
		Assert::same(array(1,2,3), $sorted);

		$sorted=$dm->sort(array(3,1));
		Assert::same(array(1,3), $sorted);
	}

	function testDuplicate() {
		$dm=$this->dm;

		Assert::equal(array("number"=>100), $dm->duplicatePrepareData(array(
			"id"=>1,
			"number"=>100
		), 1));

		$calledOnNew=array();
		$dm->onNew[]=function ($id,$values,$dmInt) use ($dm,&$calledOnNew) {
			$calledOnNew[]=$id;
		};

		$out=$dm->duplicate(1);
		Assert::equal(4, $out["id"]);
		Assert::equal(array(4), $calledOnNew);
		Assert::equal("One",$dm->get(4,"name"));

		$out=$dm->duplicate(array(2,3));
		Assert::equal(5, $out[2]["id"]);
		Assert::equal(6, $out[3]["id"]);
		Assert::equal("Two", $out[2]["name"]);
		Assert::equal("Three", $out[3]["name"]);
		Assert::equal(array(4,5,6), $calledOnNew);
		Assert::equal("Two",$dm->get(5,"name"));
		Assert::equal("Three",$dm->get(6,"name"));

		$dm->onBeforeNew[]=function ($values,$dmInt) use ($dm,&$calledOnNew) {
			if ($values["number"]%2==1) throw new StopActionException;
		};

		$out=$dm->duplicate(array(1,2));
		Assert::null($out[1]);
		Assert::equal("Two", $out[2]["name"]);
		Assert::equal(array(4,5,6,7), $calledOnNew);
		Assert::equal("Two",$dm->get(7,"name"));
		Assert::same(null,$dm->get(8,"name"));

		Assert::equal(7, count($dm->getTable()));
	}
}

$connection = require("../db.php");
$testCase=new DataManagerBasicsTest($connection, "initdb-datamanager.sql");
$testCase->run();