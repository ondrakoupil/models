<?php

namespace OndraKoupil\Models;

use \OndraKoupil\Tools\Arrays;
use \OndraKoupil\Tools\Strings;

/**
 * Controller rozšiřuje základní RelationController ještě o nějaká přídavná data ke každé vazbě.
 * <br /><br />
 * Přibývají dvě nové metody - list* a update*, a trochu se mění chování get*() a set*()
 * <br /><br />
 * Metody <b>get*()</b> navíc přijímají možnost vyfiltrovat si, jaká konkrétní data chceš, a podobně
 * jako ostatní controllery modelu lze zadat string, array stringů, null nebo false (tzn. všechno), a navíc i true
 * - v takovém případě se vrátí jen obyčejné pole IDček, podobně jako u základního RelaticonControlleru.
 * <br /><br />
 * Metoda <b>list*()</b> je zkratkou pro volání get*() s argumentem true, tj. list*() se chová jako
 * původní get*() z RelationControlleru a vrací jen pole ID z druhé strany.
 * <br /><br />
 * Metody <b>set*()</b> vždy přepisují všechna dodatečná data. Pokud chceš změnit jen některé datové sloupečky, je lepší použít <b>update*()</b>,
 * která přidá nový řádek/změní vybrané datové sloupce u konkrétního páru. Vzhledem k syntaxi SQL
 * dotazu insert... on duplicate key update, je možné update*() metodu volat vždy jen pro jediný pár.
 * <br /><br />
 * Metody <b>data*()</b> vrací komplet data pro konkrétní dvojici ID z obou stran, nebo null, pokud taková vazba neexistuje.
 * <br /><br />
 * Dále lze pomocí setupOrder() nadefinovat standarndí pořadí, ve kterém controller data vrací. To však lze pouze před tím, než dojde k prvnímu
 * načtení dat z databáze (get, list, cache...). Případnou změnu řazení, aby se projevila, musí doprovázet i clear cache.
 */
class RelationControllerWithData extends RelationController {

	protected $dataCols;

	protected $sortOrder = array("A"=>null, "B"=>null);

	function __construct(\Nette\Database\Context $db, $dbTable, $sideACol, $sideBCol, $dataCols=array(), $aliasA=false, $aliasB=false) {
		$this->dataCols=Arrays::arrayize($dataCols);
		if (!$dataCols) {
			throw new \InvalidArgumentException("RelationControllerWithData MUST have some \$dataCols specified. Use only RelationController instead.");
		}
		parent::__construct($db, $dbTable, $sideACol, $sideBCol, $aliasA, $aliasB);
	}

	/**
	 * Nastaví standarndí řazení při tahání dat z databáze.
	 * Lze zadat cokoliv, co sežere metoda order() z Nette Database.
	 * Null nebo false = nepoužívat řazení v SQL.
	 * @param string $orderA
	 * @param string $orderB
	 * @return \OndraKoupil\Models\RelationControllerWithData
	 */
	function setupOrder($orderA, $orderB) {
		$this->sortOrder = array(
			"A"=>$orderA,
			"B"=>$orderB
		);
		return $this;
	}

	/**
	 * @ignore
	 */
	function __call($name, $arguments) {

		//get
		if (preg_match('~^get(.*)$~i',$name,$parts)) {
			$parts[1]=Strings::lower($parts[1]);
			if (!isset($arguments[1])) $arguments[1]=null;
			if (in_array($parts[1],$this->aliasA)) {
				return $this->getA($arguments[0],$arguments[1]);
			}
			if (in_array($parts[1],$this->aliasB)) {
				return $this->getB($arguments[0],$arguments[1]);
			}
		}

		//list
		if (preg_match('~^list(.*)$~i',$name,$parts)) {
			$parts[1]=Strings::lower($parts[1]);
			if (in_array($parts[1],$this->aliasA)) {
				return $this->getA($arguments[0],true);
			}
			if (in_array($parts[1],$this->aliasB)) {
				return $this->getB($arguments[0],true);
			}
		}

		//update
		if (preg_match('~^update(.*)$~i',$name,$parts)) {
			$parts[1]=Strings::lower($parts[1]);
			if (!isset($arguments[2])) throw new \InvalidArgumentException("When calling update*(), you must provide all three arguments.");
			if (in_array($parts[1],$this->aliasA)) {
				return $this->updateA($arguments[0],$arguments[1],$arguments[2]);
			}
			if (in_array($parts[1],$this->aliasB)) {
				return $this->updateB($arguments[0],$arguments[1],$arguments[2]);
			}
		}

		//data
		if (preg_match('~^data(.*)$~i',$name,$parts)) {
			$parts[1]=Strings::lower($parts[1]);
			if (!isset($arguments[1])) throw new \InvalidArgumentException("When calling data*(), you must provide at least two arguments.");
			if (in_array($parts[1],$this->aliasA)) {
				return call_user_func_array(array($this,"dataA"), $arguments);
			}
			if (in_array($parts[1],$this->aliasB)) {
				return call_user_func_array(array($this,"dataB"), $arguments);
			}
		}

		return parent::__call($name, $arguments);
	}

	/**
	 * @return array
	 */
	public function getDataCols() {
		return $this->dataCols;
	}

	/**
	 * @param int|bool|array $idA
	 * @return array
	 */
	function getA($idA,$dataCols=null) {
		return $this->getGen($idA, "A", $dataCols);
	}

	/**
	 * @param int|bool|array $idA
	 * @return array
	 */
	function getB($idB,$dataCols=null) {
		return $this->getGen($idB, "B", $dataCols);
	}

	/**
	 *
	 * @param false|int|array $id
	 * @param char $side
	 * @param array|string|true $dataCols True = jen IDčka, null = všechno, jinak string nebo array stringů názvů z $dataCols
	 * @return array
	 */
	protected function getGen($id,$side,$dataCols=null) {
		if ($id===false) {
			$this->loadToCacheGen(false,$side);
			if ($dataCols===null or $dataCols===false) return $this->data[$side];
			if ($dataCols===true) {
				$vrat=array();
				foreach($this->data[$side] as $idSide=>$valsSide) {
					$vrat[$idSide]=array_keys($valsSide);
				}
				return $vrat;
			}
			$vrat=array();
			foreach ($this->data[$side] as $i=>$r) {
				$vrat[$i]=$this->getGenPrepareData($r, $dataCols);
			}
			return $vrat[$i];
		}

		if (is_array($id)) {
			$this->loadToCacheGen($id,$side);
			$vrat=array();
			foreach($id as $idr) {
				if (isset($this->data[$side][$idr])) {
					if ($dataCols===null or $dataCols===false) {
						$vrat[$idr]=$this->data[$side][$idr];
					} elseif ($dataCols===true) {
						$vrat[$idr]=array_keys($this->data[$side][$idr]);
					} else {
						$vrat[$idr]=array();
						foreach ($this->data[$side][$idr] as $idri=>$idrr) {
							$vrat[$idr][$idri]=$this->getGenPrepareData($idrr, $dataCols);
						}
					}
				} else {
					$vrat[$idr]=array();
				}
			}
			return $vrat;
		} else {
			$this->loadToCacheGen($id,$side);
			if (isset($this->data[$side][$id])) {
				if ($dataCols===null or $dataCols===false) {
					return $this->data[$side][$id];
				} elseif ($dataCols===true) {
					return array_keys($this->data[$side][$id]);
				}
				$vrat=array();
				foreach($this->data[$side][$id] as $vali=>$valr) {
					$vrat[$vali]=$this->getGenPrepareData($valr, $dataCols);
				}
				return $vrat;
			}
			return array();
		}
	}

	/**
	 * @ignore
	 * @param array $allDataArray
	 * @param array|string $what
	 * @return array|string
	 */
	protected function getGenPrepareData($allDataArray,$what) {

		if ($what===false or $what===null) {
			return $allDataArray;
		}

		if (!is_array($what)) {
			return $allDataArray[$what];
		}

		$ret=array();
		foreach($what as $w) {
			$ret[$w]=$allDataArray[$w];
		}

		return $ret;
	}

	function listA($idA) {
		return $this->listGen($idA,"A");
	}

	function listB($idB) {
		return $this->listGen($idB,"B");
	}

	protected function listGen($id,$side) {
		return $this->getGen($id,$side,true);
	}

	function dataA($idA, $idB, $columns=null) {
		return $this->dataGen($idA, $idB, "A", $columns);
	}

	function dataB($idB, $idA, $columns=null) {
		return $this->dataGen($idB, $idA, "B", $columns);
	}

	protected function dataGen($idSide, $idOtherSide, $side, $columns) {
		$this->loadToCacheGen($idSide, $side);

		if (isset($this->data[$side][$idSide][$idOtherSide])) {
			return $this->getGenPrepareData($this->data[$side][$idSide][$idOtherSide], $columns);
		}

		return null;
	}

	/**
	 * @ignore
	 */
	protected function setGen($id,$valuesWithData,$side,$clear=true) {

		if (!is_array($valuesWithData)) {
			throw new \InvalidArgumentException("When using set*() in RelationControllerWithData, \$values must be an array!");
		}

		if ($clear) {
			$this->deleteGen($id, false, $side);
		} else {
			$this->deleteGen($id, array_keys($valuesWithData), $side);
		}

		$data=array();
		$otherCol="";
		$otherSide="";
		$canUseForCache=true;
		if ($side=="A") {
			$pattern="($this->sideACol,$this->sideBCol,".implode(",",$this->dataCols).")";
			$otherCol=$this->sideBCol;
			$otherSide="B";
		} else {
			$pattern="($this->sideBCol,$this->sideACol,".implode(",",$this->dataCols).")";;
			$otherCol=$this->sideACol;
			$otherSide="A";
		}
		foreach($valuesWithData as $vid=>$vvals) {
			$dataPart=array();
			foreach($this->dataCols as $dataCol) {
				if (isset($vvals[$dataCol])) {
					$dataPart[]="'".addslashes($vvals[$dataCol])."'";
				} else {
					if (array_key_exists($dataCol, $vvals) and $vvals[$dataCol]===null) {
						$dataPart[]="null";
					} else {
						$dataPart[]="DEFAULT($dataCol)";
						$canUseForCache=false;
					}
				}
			}
			$data[]="('".addslashes($id)."','".addslashes($vid)."',".implode(",",$dataPart).")";
		}

		if (!$data) return $this;

		$q="insert into $this->dbTable $pattern values ".implode(", ",$data);

		$ok=$this->db->query($q);
		if ($ok===false) throw new Exceptions\DatabaseException("Failed query: $q");

		if ($canUseForCache) {
			if ($clear) { //set
				$this->data[$side][$id]=$valuesWithData;
			} else { //add - pokud jsme předtím v cache neměli, nevíme, co vlastně máme.
				if (isset($this->data[$side][$id]) and $this->data[$side][$id]!==null) {
					$this->data[$side][$id]=$valuesWithData+$this->data[$side][$id];
				}
			}
		} else {
			if (isset($this->data[$side][$id])) {
				unset($this->data[$side][$id]);
			}
		}

		$this->clearCacheGen($otherSide);
		return $this;
	}

	function updateA($idA,$idB,$data) {
		return $this->updateGen($idA, $idB, $data, "A");
	}

	function updateB($idB,$idA,$data) {
		return $this->updateGen($idB, $idA, $data, "B");
	}

	/**
	 *
	 * @param int $idSide ID jednoho záznamu na straně $side
	 * @param int $idOtherSide ID jednoho z příslušných záznamů pro $idSide
	 * @param array $data Data pro update
	 * @param char $side A nebo B
	 * @return \OndraKoupil\RelationControllerWithData
	 * @throws Exceptions\DatabaseException
	 */
	protected function updateGen($idSide,$idOtherSide,$data,$side) {
		if ($side=="A") {
			$pattern="($this->sideACol,$this->sideBCol,".implode(",",array_keys($data)).")";
			$otherCol=$this->sideBCol;
			$otherSide="B";
		} else {
			$pattern="($this->sideBCol,$this->sideACol,".implode(",",array_keys($data)).")";;
			$otherCol=$this->sideACol;
			$otherSide="A";
		}
		$q="insert into $this->dbTable $pattern values ";
		$q.="('".addslashes($idSide)."','".addslashes($idOtherSide)."'";
		foreach($data as $dataI=>$dataR) {
			$q.=",'".addslashes($dataR)."'";
		}
		$q.=") on duplicate key update ";

		$count=0;
		foreach($data as $dataI=>$dataR) {
			if ($count) $q.=", ";
			$q.="$dataI = '".addslashes($dataR)."'";
			$count++;
		}

		$ok=$this->db->query($q);
		if ($ok===false) throw new Exceptions\DatabaseException("Failed query: $q");

		// dáme do cache, máme-li ji
		if (isset($this->data[$side][$idSide][$idOtherSide])) {
			$this->data[$side][$idSide][$idOtherSide]=$data+$this->data[$side][$idSide][$idOtherSide];
		}
		if (isset($this->data[$otherSide][$idOtherSide][$idSide])) {
			$this->data[$otherSide][$idOtherSide][$idSide]=$data+$this->data[$otherSide][$idOtherSide][$idSide];
		}

		return $this;
	}

	/**
	 * @param int $idA
	 * @param array|int $valuesB
	 * @return RelationController
	 */
	function addA($idA,$valuesB) {
		return $this->setGen($idA, $valuesB, "A", false);
	}

	/**
	 * @param int $idA
	 * @param array|int $valuesB
	 * @return RelationController
	 */
	function addB($idB,$valuesA) {
		return $this->setGen($idB, $valuesA, "B", false);
	}

	/**
	 * @param int $idA
	 * @param array|int $valuesB
	 * @return RelationController
	 */
	function deleteA($idA,$valuesB=false) {
		return $this->deleteGen($idA, $valuesB, "A");
	}

	/**
	 * @param int $idA
	 * @param array|int $valuesB
	 * @return RelationController
	 */
	function deleteB($idB,$valuesA=false) {
		return $this->deleteGen($idB, $valuesA, "B");
	}

	/**
	 * @ignore
	 */
	protected function deleteGen($id,$idOther,$side) {

		if (!is_scalar($id)) {
			throw new \InvalidArgumentException("\$id must be a scalar!");
		}

		$values=$idOther;
		if (!$values and $values!==false) return $this;
		if ($side=="A") {
			$col=$this->sideACol;
			$otherCol=$this->sideBCol;
			$otherSide="B";
		} else {
			$col=$this->sideBCol;
			$otherCol=$this->sideACol;
			$otherSide="A";
		}

		$req=$this->getTable()->where($col,$id);
		if ($values!==false) {
			$req->where($otherCol,$values);
		}
		$ok=$req->delete();
		if ($ok===false) return false;

		if ($values===false) {
			$this->data[$side][$id]=array();
		} else {
			if (isset($this->data[$side][$id])) {
				foreach(Arrays::arrayize($values) as $val) {
					if (isset($this->data[$side][$id][$val])) {
						unset($this->data[$side][$id][$val]);
					}
				}
			}
		}

		$this->clearCacheGen($otherSide);

		return $this;
	}

	/**
	 * @ignore
	 */
	protected function loadToCacheGen($id,$side) {
		if ($this->loadedAll[$side]) return $this;
		if (!is_array($id) and $id!==false) {
			if (isset($this->data[$side][$id])) return $this;
		}

		$req=$this->getTable();
		if ($side=="A") $col=$this->sideACol;
			else $col=$this->sideBCol;
		if ($id!==false) {
			$id=Arrays::arrayize($id);
			foreach($id as $idi=>$idr) {
				if (!isset($this->data[$side][$idr])) {
					$this->data[$side][$idr]=array();
				} else {
					unset($id[$idi]);
				}
			}
			if (!$id) return $this;
			$req->where($col,$id);
		} else {
			$this->data[$side]=array();
			$this->loadedAll[$side]=true;
		}

		$req->select("$this->sideACol,$this->sideBCol".($this->dataCols?(",".implode(",",$this->dataCols)):""));

		if ($side=="A") {
			if ($this->sortOrder["A"]) {
				$req->order($this->sortOrder["A"]);
			}
		} else {
			if ($this->sortOrder["B"]) {
				$req->order($this->sortOrder["B"]);
			}
		}

		foreach ($req as $r) {
			if ($side=="A") {
				$ind=$r[$this->sideACol];
				$val=$r[$this->sideBCol];
			} else {
				$ind=$r[$this->sideBCol];
				$val=$r[$this->sideACol];
			}
			$data=array();
			foreach($this->dataCols as $dc) {
				$data[$dc]=$r[$dc];
			}
			$this->data[$side][$ind][$val]=$data;
		}

		return $this;
	}

	/**
	 * @param int $a
	 * @param int $b
	 * @return boolean
	 */
	function is($a,$b,$preferredSide="A") {
		if (isset($this->data["A"][$a])) {
			return isset($this->data["A"][$a][$b]);
		}
		if (isset($this->data["B"][$b])) {
			return isset($this->data["B"][$b][$a]);
		}
		if ($this->loadedAll["A"] or $this->loadedAll["B"]) return false;

		if ($preferredSide=="A") {
			$allA=$this->getA($a);
			return isset($allA[$b]);
		} else {
			$allB=$this->getB($b);
			return isset($allB[$a]);
		}
	}
}
