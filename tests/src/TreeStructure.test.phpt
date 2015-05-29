<?php


include "../bootstrap.php";

use \Tester\TestCase;
use \Tester\Assert;
use \OndraKoupil\Models\TreeStructure;
use \OndraKoupil\Tools\Arrays;
use \OndraKoupil\Testing\Assert as OKAssert;

class TreeStructureTestCase extends TestCase {

	// See the .jpg picture
	protected $data=array(
		array("id"=>1,"master"=>3),
		array("id"=>2,"master"=>9),
		array("id"=>3,"master"=>0),
		array("id"=>4,"master"=>3),
		array("id"=>5,"master"=>15),
		array("id"=>15,"master"=>0),
		array("id"=>6,"master"=>0),
		array("id"=>7,"master"=>19),
		array("id"=>8,"master"=>5),
		array("id"=>9,"master"=>6),
		array("id"=>10,"master"=>4),
		array("id"=>11,"master"=>4),
		array("id"=>12,"master"=>5),
		array("id"=>13,"master"=>17),
		array("id"=>14,"master"=>15),
		array("id"=>16,"master"=>9),
		array("id"=>17,"master"=>19),
		array("id"=>18,"master"=>17),
		array("id"=>19,"master"=>5),
		array("id"=>20,"master"=>16)
	);

	public function testParent() {
		$ts=new TreeStructure($this->data);

		Assert::equal(5, $ts->parent(19));
		Assert::equal(0, $ts->parent(15));
		Assert::equal(0, $ts->parent(0));
		Assert::exception(function() use ($ts) {
			$ts->parent(100);
		},'\InvalidArgumentException');
	}

	public function testChildren() {
		$ts=new TreeStructure($this->data);

		OKAssert::arrayEqual(array(14,5), $ts->children(15));
		OKAssert::arrayEqual(array(3,15,6), $ts->children(0));
		Assert::equal(array(), $ts->children(2));
		Assert::exception(function() use ($ts) {
			$ts->children(100);
		},'\InvalidArgumentException');
	}

	public function testExists() {
		$ts=new TreeStructure($this->data);

		Assert::true($ts->exists(14));
		Assert::false($ts->exists(100));
		Assert::false($ts->exists(0));
	}

	public function testIsParentOf() {
		$ts=new TreeStructure($this->data);

		Assert::true($ts->isParentOf(17,19));
		Assert::false($ts->isParentOf(17,5));
		Assert::false($ts->isParentOf(9,2));
	}

	public function testIsAncestorOf() {
		$ts=new TreeStructure($this->data);

		Assert::true($ts->isAncestorOf(17,19));
		Assert::true($ts->isAncestorOf(17,5));
		Assert::false($ts->isAncestorOf(9,2));
		Assert::true($ts->isAncestorOf(18,0));
	}

	public function testIsChildOf() {
		$ts=new TreeStructure($this->data);

		Assert::true($ts->isChildOf(19,17));
		Assert::false($ts->isChildOf(19,18));
		Assert::false($ts->isChildOf(9,6));
	}

	public function testIsDescendantOf() {
		$ts=new TreeStructure($this->data);

		Assert::true($ts->isDescendantOf(19,17));
		Assert::true($ts->isDescendantOf(19,13));
		Assert::true($ts->isDescendantOf(0,18));
		Assert::false($ts->isDescendantOf(15,9));
		Assert::false($ts->isDescendantOf(9,6));
	}

	public function testSiblings() {
		$ts=new TreeStructure($this->data);

		OKAssert::arrayEqual(array(19,8), $ts->siblings(12, false));
		OKAssert::arrayEqual(array(19,8,12), $ts->siblings(12, true));
		OKAssert::arrayEqual(array(3,15,6), $ts->siblings(6, true));
		OKAssert::arrayEqual(array(20), $ts->siblings(20, true));
		Assert::equal(array(), $ts->siblings(20, false));
		Assert::exception(function() use ($ts) {
			$ts->siblings(100);
		},'\InvalidArgumentException');
	}

	public function testIsSiblingOf() {
		$ts=new TreeStructure($this->data);

		Assert::true($ts->isSiblingOf(8,19));
		Assert::true($ts->isSiblingOf(19,8));
		Assert::true($ts->isSiblingOf(3,6));
		Assert::false($ts->isSiblingOf(15,14));
		Assert::false($ts->isSiblingOf(7,6));

		Assert::exception(function() use ($ts) {
			$ts->isSiblingOf(100,2);
		},'\InvalidArgumentException');
	}

	public function testAncestor() {
		$ts=new TreeStructure($this->data);

		Assert::equal(3, $ts->ancestor(11, 1));
		Assert::equal(4, $ts->ancestor(11, 2));
		Assert::equal(11, $ts->ancestor(11, 3));
		Assert::equal(11, $ts->ancestor(11, 4));
		Assert::equal(0, $ts->ancestor(13, 0));
		Assert::equal(17, $ts->ancestor(13, 4));
	}

	public function testLevel() {
		$ts=new TreeStructure($this->data);

		Assert::equal(3, $ts->level(19));
		Assert::equal(3, $ts->level(8));
		Assert::equal(1, $ts->level(3));
		Assert::equal(5, $ts->level(18));
		Assert::equal(0, $ts->level(0));
		Assert::exception(function() use ($ts) {
			$ts->level(100);
		},'\InvalidArgumentException');
	}

	public function testPath() {
		$ts=new TreeStructure($this->data);

		Assert::same(array(15,5,19,17), $ts->path(17));
		Assert::same(array(17,19,5,15), $ts->path(17,false));

		Assert::same(array(3), $ts->path(3));
		Assert::exception(function() use ($ts) {
			$ts->level(100);
		},'\InvalidArgumentException');
	}

	public function testAllIds() {
		$ts=new TreeStructure($this->data);
		Assert::equal(Arrays::valuePicker($this->data, "id"), $ts->allIds());
	}

	public function testDescendants() {
		$ts=new TreeStructure($this->data);
		OKAssert::arrayEqual(array(8,12,19,7,17,13,18), $ts->descendants(5));
		OKAssert::arrayEqual(array(2,16,20), $ts->descendants(9));
		Assert::equal(array(), $ts->descendants(2));
	}

	public function testLinearize() {
		$ts=new TreeStructure($this->data);
		Assert::same(array(3,1,4,10,11,15,5,8,12,19,7,17,13,18,14,6,9,2,16,20), $ts->linearize());
		Assert::same(array(7,17,13,18), $ts->linearize(19));
	}

	public function testBogusId() {
		$data=$this->data;
		$data[]=array("id"=>101,"master"=>100);
		$data[]=array("id"=>102,"master"=>100);

		$ts=new TreeStructure($data);
		OKAssert::arrayEqual(array(101,102), $ts->bogusIds());
	}

	public function testUnstandardConstruct() {
		$data=array(
			array("a"=>3,"b"=>0),
			array("a"=>1,"b"=>3),
			array("a"=>4,"b"=>3),
			array("a"=>10,"b"=>4),
			array("a"=>11,"b"=>4)
		);

		$ts=new TreeStructure($data,"a","b");
		Assert::equal(3, $ts->parent(4));
		Assert::equal(3, $ts->level(10));
		Assert::equal(2, $ts->level(1));
		Assert::equal(array(1,4), $ts->children(3));
	}

}

$a=new TreeStructureTestCase();
$a->run();