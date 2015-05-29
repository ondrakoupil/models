<?php

namespace OndraKoupil\Models;

class TreeStructure extends \Nette\Object {

	protected $data;

	protected $masters=array();
	protected $children=array();
	protected $levels=array();

	protected $colId,$colMaster;

	const MAX_DEPTH=20;
	const BOGUS_LEVEL=-100;

	/**
	 * @param array|\Traversable $sortedData Lineární dvojrozměrné pole, většinou z databáze
	 * @param string $id Název prvku v $sortedData představující ID
	 * @param string $master  Název prvku v $sortedData představující ID předka
	 * @throws \InvalidArgumentException Když $sortedData není správný typ
	 */
	public function __construct($sortedData,$id="id",$master="master") {

		if (!is_array($sortedData) and !($sortedData instanceof \Traversable)) {
			throw new \InvalidArgumentException("\$sortedData in TreeStructure must be Traversable or array!");
		}

		$this->colId=$id;
		$this->colMaster=$master;
		$this->data=$sortedData;

		$this->parse();
	}

	/**
	 * @ignore
	 */
	protected function parse() {
		$this->children=array();
		$this->masters=array();

		foreach($this->data as $row) {
			$id=$row[$this->colId];
			$master=$row[$this->colMaster];
			if (!$master) $master=0;

			if (!isset($this->children[$master])) {
				$this->children[$master]=array();
			}
			$this->children[$master][]=$id;
			$this->masters[$id]=$master;
		}

		$this->parseRecursionLevels(0, 0);
	}

	/**
	 * @ignore
	 */
	protected function parseRecursionLevels($rootId,$level) {
		if ($level>self::MAX_DEPTH) throw new \RuntimeException("Too deep recursion!");

		$this->levels[$rootId]=$level;

		if (isset($this->children[$rootId])) {
			foreach($this->children[$rootId] as $children) {
				$this->parseRecursionLevels($children, $level+1);
			}
		}

		foreach($this->masters as $id=>$master) {
			if (!isset($this->levels[$id])) {
				$this->levels[$id]=self::BOGUS_LEVEL;
			}
		}
	}

	/**
	 * Rodič prvku $id. Root prvek s ID = 0 má mastera také 0.
	 * @param int $id
	 * @return int
	 * @throws \InvalidArgumentException Když prvek s $id chybí.
	 */
	function parent($id) {
		if (isset($this->masters[$id])) return $this->masters[$id];
		elseif ($id==0) return 0;
		else throw new \InvalidArgumentException("ID $id not found");
	}

	/**
	 * Všichni přímí potomci (děti) prvku s ID $id
	 * @param int $id
	 * @return array
	 * @throws \InvalidArgumentException Když ID $id chybí
	 */
	function children($id) {
		if (isset($this->children[$id])) return $this->children[$id];
		if (isset($this->masters[$id])) return array();
		else throw new \InvalidArgumentException("ID $id not found");
	}

	/**
	 * Existuej prvek s ID $id?
	 * @param int $id
	 * @return bool
	 */
	function exists($id) {
		return isset($this->masters[$id]);
	}

	/**
	 * Je $testedParent rodičem (přímým) potomka $child?
	 * @param int $child
	 * @param int $testedParent
	 * @return bool
	 */
	function isParentOf($child,$testedParent) {
		return ($this->masters[$child]==$testedParent);
	}

	/**
	 * Je $testedChild přímým potomkem (dítětem) $parent?
	 * @param int $parent
	 * @param int $testedChild
	 * @return bool
	 */
	function isChildOf($parent,$testedChild) {
		return ($this->masters[$testedChild]==$parent);
	}

	/**
	 * Je $testedAncestor předkem (klidně o více generací) $child?
	 * @param int $child
	 * @param int $testedAncestor
	 * @return boolean
	 * @throws \InvalidArgumentException Když ID v $child nebo $testedAncestor chybí
	 */
	function isAncestorOf($child,$testedAncestor) {
		$pos=$child;
		if ($child!=0 and !isset($this->masters[$child])) throw new \InvalidArgumentException("ID $child does not exist.");
		if ($testedAncestor!=0 and !isset($this->masters[$testedAncestor])) throw new \InvalidArgumentException("ID $testedAncestor does not exist.");

		for ($check=0;$check<self::MAX_DEPTH and $pos!=0 and $pos!=self::BOGUS_LEVEL;$check++) {
			if ($pos==$testedAncestor) return true;
			$pos=$this->masters[$pos];
			if ($pos==0 and $testedAncestor==0) return true;
		}
		return false;
	}

	/**
	 * Je $testedDescendant potomkem (klidně o více generací) $parent
	 * @param int $parent
	 * @param int $testedDescendant
	 * @return bool
	 */
	function isDescendantOf($parent,$testedDescendant) {
		return $this->isAncestorOf($testedDescendant, $parent);
	}

	/**
	 * Všichni sourozenci
	 * @param int $id
	 * @param bool $includeSelf Zahrnout i $id?
	 * @return array
	 */
	function siblings($id,$includeSelf=false) {
		$master=$this->parent($id);
		$children=$this->children($master);
		if ($includeSelf) return $children;
		foreach($children as $i=>$r) {
			if ($r==$id) {
				unset($children[$i]);
				break;
			}
		}
		return $children;
	}

	/**
	 * Jsou si $id1 a $id2 sourozenci?
	 * @param int $id1
	 * @param int $id2
	 * @return bool
	 * @throws \InvalidArgumentException Když ID $id1 nebo $id2 chybí.
	 */
	function isSiblingOf($id1,$id2) {
		$s=$this->siblings($id2,true);
		if (!isset($this->masters[$id1])) throw new \InvalidArgumentException("ID $id1 does not exist");
		return in_array($id1, $s);
	}

	/**
	 * Najde předka $id na úrovni $level. Pokud je $id na úrovni $level nebo níže, vrací jen $id.
	 * @param int $id
	 * @param int $level
	 * @return null|int NULL v případě, že bylo nalezeno nějaké zacyklování.
	 * @throws \InvalidArgumentException Když ID $id chybí.
	 */
	function ancestor($id,$level) {
		if (!isset($this->masters[$id])) throw new \InvalidArgumentException("ID $id does not exist");
		if ($this->levels[$id]<=$level) return $id;

		$check=0;
		$pos=$id;
		while ($pos!=0 and $pos!=self::BOGUS_LEVEL and $check<self::MAX_DEPTH) {
			if ($this->level($pos)==$level) return $pos;
			$pos=$this->parent($pos);
			$check++;
		}
		if ($pos==0) return $pos;

		return null;
	}

	/**
	 * Úroveň $id. Přímí potomci kořene (s ID 0) mají úroveň 1, jejich potomci ID 2 atd.
	 * Čím vyšší číslo, tím hlubší zanoření.
	 * @param int $id
	 * @return int
	 * @throws \InvalidArgumentException Když ID $id chybí.
	 */
	function level($id) {
		if (isset($this->levels[$id])) return $this->levels[$id];
		else throw new \InvalidArgumentException("ID $id not found");
	}

	/**
	 * Cesta z kořene k $id
	 * @param int $id
	 * @param bool $fromRoot True (default) = od kořene k $id. False = od $id ke kořeni.
	 * @return array
	 */
	function path($id,$fromRoot=true) {
		$path=array();
		$pos=$id;
		for ($check=0; $check<self::MAX_DEPTH and $pos!=0 and $pos!=self::BOGUS_LEVEL ;$check++) {
			$path[]=$pos;
			$pos=$this->masters[$pos];
		}
		if ($fromRoot) {
			return array_reverse($path);
		}
		return $path;
	}

	/**
	 * Všechna ID v nedefinovaném pořadí.
	 * @return array
	 */
	function allIds() {
		return array_keys($this->masters);
	}

	/**
	 * Všichni potomci (v linearizovaném pořadí "zleva doprava").
	 * @param int $id
	 * @return array
	 */
	function descendants($id) {
		if (!isset($this->children[$id])) return array();
		return $this->descendantsRecursion($id,0);
	}

	/**
	 * @ignore
	 */
	private function descendantsRecursion($id,$check) {
		if ($check>self::MAX_DEPTH) return array();
		if (!isset($this->children[$id]) or !$this->children[$id]) {
			if ($check) return array($id);
			return array();
		}

		if ($check) $vrat=array($id);
		else $vrat=array();

		foreach($this->children[$id] as $ch) {
			$vrat=array_merge($vrat, $this->descendantsRecursion($ch, $check+1) );
		}

		return $vrat;
	}

	/**
	 * Linearizace stromu v pořadí "zleva doprava", pre-order.
	 * @param int $rootId Odkud začít
	 * @return array
	 */
	function linearize($rootId=0) {
		return $this->descendants($rootId);
	}

	/**
	 * Najde všechny ID, které nejsou validně zařazena do stromu.
	 * @return array
	 */
	function bogusIds() {
		$boguses=array();
		foreach($this->levels as $id=>$level) {
			if ($level==self::BOGUS_LEVEL) $boguses[]=$id;
		}
		return $boguses;
	}

}
