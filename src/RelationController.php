<?php

namespace OndraKoupil\Models;

use \OndraKoupil\Tools\Strings;
use \OndraKoupil\Tools\Arrays;

/**
 * Controller sloužící ke správě vazeb mezi dvěma entitami v databázi typu M:N.
 * <br />Předpokládá se existence tabulky obsahující jako cizí klíče IDčka obou entit a případně nějaká další data, se kterými však zatím controller nepracuje.
 * Tabulka musí mít nastaven primární nebo unikátní index tak, aby nebylo možné mít duplicitní záznam.
 * <br />Controller intenzivně cachuje a všechny jeho funkce lze volat výkonově (nezapomeňte využívat preloadingu). Cache je vždy platná jen pro konkrétní instanci controlleru
 * a je tedy ji třeba nějak sdílet.
 * <br /><br />Controller je oboustranný a lze jediný controller použít jak pro čtení/psaní vazeb z jedné entity, tak z druhé.
 * Jedna entita je označována jako A, druhá jako B. Pro lepší pochopení však controller umožňuje definovat aliasy a přistupovat pak pomocí magických metod.
 * U magických metod platí, že entita v názvu metody je ta, z jejíž strany chceme vazby měnit, což je trochu proti přirozenému chápání jazyka,
 * ale programátorsky to sedí víc.
 * <br /><br />
 * Příklad - máme kategorie a produkty. Kategorie jsou A, produkty jsou B.
 * <code>
 * $c = new RelationController("mod_eshop_produkt2kategorie","idkategorie","idprodukt");
 *
 * // ----- Jednoduché operace -----
 *
 * // Vrátí všechny kategorie, v nichž je zařazen produkt 10 (jako array)
 * $c->getProdukt(10);
 *
 * // Přidá k produktu 10 zařazení do kategorie 5 nebo do kategorií 1, 2 a 3
 * $c->addProdukt(10,5);
 * $c->addProdukt(10,array(1,2,3));
 *
 * // Spočítá, do kolika kategorií je zařazen produkt 10
 * // anebo produkty 10, 20 a 30 (jako array)
 * $c->countProdukt(10);
 * $c->countProdukt( array(10,20,30) );
 *
 * // Nastaví produktu 10 zařazení do kategorie 1, 2, 3 a ostatní smaže
 * $c->setProdukt(10,array(1,2,3));
 *
 * // Vymaže všechna zařazení produktu 10 do kategorií
 * $c->deleteProdukt(10);
 *
 * // Všechna zařazení produktu 11 nastaví podle zařazení produktu 10. První argument je zdroj, druhý je cíl.
 * $c->duplicateProdukt(10,11);
 *
 * // Je produkt 10 v kategorii 3? True/false
 * $c->isProdukt(10,3);
 * $c->hasProdukt(10,3);
 *
 * // Vrátí array [3] => array(...), [5] => array(...), [10] => array(...)
 * $c->getProdukt(array(3,5,10));
 *
 * // Vrátí obdobné array všech produktů
 * $c->getProdukt(array(3,5,10));
 *
 * // Vymaže zařazení produktu 10 do kategorie 5, ostatní nechá
 * $c->deleteProdukt(10,5);
 *
 * // ----- Preloadování do cache -----
 *
 * // Nacachuje si všechny vazby všech produktů
 * $c->loadProdukt(false);
 * $c->cacheProdukt(false);
 *
 * // Někde seženeme pole ID produktů $produkty
 * foreach($produkty as $p) {
 *		echo "\nProdukt $p je v těchto kategoriích: ".implode(", ", $c->getProdukt($p));
 * }
 *
 * // Pokud se mezitím zasáhne do DB, může být potřeba vymazat cache
 * $c->clearProdukt();
 *
 *
 *
 * // ----- A z druhé strany -----
 *
 * // Vrátí array všech produktů v kategorii 5
 * $c->getKategorie(5)
 *
 * </code>
 */
class RelationController extends \Nette\Object {

	/**
	 * @var \Nette\Database\Context
	 */
	protected $db;

	/**
	 * @var string
	 */
	protected $dbTable;

	protected $sideACol;
	protected $sideBCol;
	protected $aliasA=array();
	protected $aliasB=array();

	protected $data=array("A"=>array(),"B"=>array());
	protected $loadedAll=array("A"=>false,"B"=>false);

	/**
	 * @param \Nette\Database\Context $db
	 * @param string $dbTable Jméno databázové tabulky
	 * @param string $sideACol Jméno sloupce představujícího ID entity A
	 * @param string $sideBCol Jméno sloupce představujícího ID entity B
	 * @param string|array|bool $aliasA Array řetězců, které představují aliasy pro entitu A. False = odvodit z $sideACol (pokdu začíná na "id", tak se toto odtrhne)
	 * @param string|array|bool $aliasB Array řetězců, které představují aliasy pro entitu B. False = odvodit z $sideBCol (pokdu začíná na "id", tak se toto odtrhne)
	 */
	function __construct(\Nette\Database\Context $db, $dbTable, $sideACol, $sideBCol, $aliasA=false, $aliasB=false) {
		$this->db=$db;
		$this->dbTable=$dbTable;
		$this->sideACol=$sideACol;
		$this->sideBCol=$sideBCol;

		if (!$aliasA) {
			$aliasA=array(Strings::lower($sideACol));
			if (preg_match('~^id_?(.*)$~i',$sideACol,$parts)) {
				$aliasA[]=Strings::lower($parts[1]);
			}
			$this->aliasA=Arrays::arrayize($aliasA);
		} else {
			$this->aliasA=Arrays::arrayize($aliasA);
		}

		if (!$aliasB) {
			$aliasB=array(Strings::lower($sideBCol));
			if (preg_match('~^id_?(.*)$~i',$sideBCol,$parts)) {
				$aliasB[]=Strings::lower($parts[1]);
			}
			$this->aliasB=Arrays::arrayize($aliasB);
		} else {
			$this->aliasB=Arrays::arrayize($aliasB);
		}
	}

	/**
	 * @ignore
	 */
	function __call($name, $arguments) {

		//get
		if (preg_match('~^get(.*)$~i',$name,$parts)) {
			$parts[1]=Strings::lower($parts[1]);
			if (in_array($parts[1],$this->aliasA)) {
				return $this->getA($arguments[0]);
			}
			if (in_array($parts[1],$this->aliasB)) {
				return $this->getB($arguments[0]);
			}
		}

		//set
		if (preg_match('~^set(.*)$~i',$name,$parts)) {
			$parts[1]=Strings::lower($parts[1]);
			if (in_array($parts[1],$this->aliasA)) {
				return $this->setA($arguments[0],$arguments[1]);
			}
			if (in_array($parts[1],$this->aliasB)) {
				return $this->setB($arguments[0],$arguments[1]);
			}
		}

		//add
		if (preg_match('~^add(.*)$~i',$name,$parts)) {
			$parts[1]=Strings::lower($parts[1]);
			if (in_array($parts[1],$this->aliasA)) {
				return $this->addA($arguments[0],$arguments[1]);
			}
			if (in_array($parts[1],$this->aliasB)) {
				return $this->addB($arguments[0],$arguments[1]);
			}
		}

		//is / has
		if (preg_match('~^(has|is)(.*)$~i',$name,$parts)) {
			$parts[2]=Strings::lower($parts[2]);
			if (in_array($parts[2],$this->aliasA)) {
				return $this->is($arguments[0],$arguments[1],"A");
			}
			if (in_array($parts[2],$this->aliasB)) {
				return $this->is($arguments[1],$arguments[0],"B");
			}
		}

		//load
		if (preg_match('~^(load|cache)(.*)$~i',$name,$parts)) {
			$parts[2]=Strings::lower($parts[2]);
			if (in_array($parts[2],$this->aliasA)) {
				if (!isset($arguments[0])) $arguments[0]=false;
				return $this->loadToCacheA($arguments[0]);
			}
			if (in_array($parts[2],$this->aliasB)) {
				if (!isset($arguments[0])) $arguments[0]=false;
				return $this->loadToCacheB($arguments[0]);
			}
		}

		//delete
		if (preg_match('~^delete(.*)$~i',$name,$parts)) {
			$parts[1]=Strings::lower($parts[1]);
			if (!isset($arguments[1])) $arguments[1]=false;
			if (in_array($parts[1],$this->aliasA)) {
				return $this->deleteA($arguments[0],$arguments[1]);
			}
			if (in_array($parts[1],$this->aliasB)) {
				return $this->deleteB($arguments[0],$arguments[1]);
			}
		}

		//clear
		if (preg_match('~^clear(.*)$~i',$name,$parts)) {
			$parts[1]=Strings::lower($parts[1]);
			if (!isset($arguments[0])) $arguments[0]=false;
			if (in_array($parts[1],$this->aliasA)) {
				return $this->clearCacheA($arguments[0]);
			}
			if (in_array($parts[1],$this->aliasB)) {
				return $this->clearCacheB($arguments[0]);
			}
		}

		//count
		if (preg_match('~^count(.*)$~i',$name,$parts)) {
			$parts[1]=Strings::lower($parts[1]);
			if (!isset($arguments[0])) $arguments[0]=false;
			if (in_array($parts[1],$this->aliasA)) {
				return $this->countA($arguments[0]);
			}
			if (in_array($parts[1],$this->aliasB)) {
				return $this->countB($arguments[0]);
			}
		}

		//duplicate
		if (preg_match('~^duplicate(.*)$~i',$name,$parts)) {
			$parts[1]=Strings::lower($parts[1]);
			if (!isset($arguments[0])) $arguments[0]=false;
			if (!isset($arguments[1])) $arguments[1]=false;
			if (in_array($parts[1],$this->aliasA)) {
				return $this->duplicateA($arguments[0],$arguments[1]);
			}
			if (in_array($parts[1],$this->aliasB)) {
				return $this->duplicateB($arguments[0],$arguments[1]);
			}
		}

		throw new \RuntimeException("Calling method $name of RelationController is not possible.");
	}

	/**
	 * @return \Nette\Database\Context
	 */
	function getDb() {
		return $this->db;
	}

	/**
	 * @return \Nette\Database\Table\Selection
	 */
	function getTable() {
		return $this->db->table($this->dbTable);
	}

	/**
	 * @param int|bool|array $idA
	 * @return array
	 */
	function getA($idA) {
		return $this->getGen($idA, "A");
	}

	/**
	 * @param int|bool|array $idA
	 * @return array
	 */
	function getB($idB) {
		return $this->getGen($idB, "B");
	}

	/**
	 * @ignore
	 */
	protected function getGen($id,$side) {
		if ($id===false) {
			$this->loadToCacheGen(false,$side);
			return $this->data[$side];
		}
		if (is_array($id)) {
			$this->loadToCacheGen($id,$side);
			$vrat=array();
			foreach($id as $idr) {
				if (isset($this->data[$side][$idr])) {
					$vrat[$idr]=$this->data[$side][$idr];
				} else {
					$vrat[$idr]=array();
				}
			}
			return $vrat;
		} else {
			$this->loadToCacheGen($id,$side);
			if (isset($this->data[$side][$id])) {
				return $this->data[$side][$id];
			}
			return array();
		}
	}

	/**
	 * @param int $idA
	 * @param array|int $valuesB
	 * @return RelationController
	 */
	function setA($idA,$valuesB) {
		return $this->setGen($idA, $valuesB, "A", true);
	}

	/**
	 * @param int $idB
	 * @param array|int $valuesA
	 * @return RelationController
	 */
	function setB($idB,$valuesA) {
		return $this->setGen($idB, $valuesA, "B", true);
	}

	/**
	 * @ignore
	 */
	protected function setGen($id,$values,$side,$clear=true) {
		if ($clear) {
			$this->deleteGen($id, false, $side);
		}
		$values=Arrays::arrayize($values);

		$data=array();
		$otherCol="";
		$otherSide="";
		if ($side=="A") {
			$pattern="($this->sideACol,$this->sideBCol)";
			$otherCol=$this->sideBCol;
			$otherSide="B";
		} else {
			$pattern="($this->sideBCol,$this->sideACol)";;
			$otherCol=$this->sideACol;
			$otherSide="A";
		}
		foreach($values as $v) {
			$data[]="('".addslashes($id)."','".addslashes($v)."')";
		}

		if (!$data) return $this;

		$q="insert into `$this->dbTable` $pattern values ".implode(", ",$data);
		$q.=" on duplicate key update $otherCol = $otherCol";

		$ok=$this->db->query($q);
		if ($ok===false) throw new Exceptions\DatabaseException("Failed query: $q");

		if ($clear) { //set
			$this->data[$side][$id]=$values;
		} else { //add - pokud jsme předtím v cache neměli, nevíme, co vlastně máme.
			if (isset($this->data[$side][$id]) and $this->data[$side][$id]!==null) {
				$this->data[$side][$id]=array_merge($this->data[$side][$id],$values);
				$this->data[$side][$id]=array_unique($this->data[$side][$id]);
			}
		}
		$this->clearCacheGen($otherSide);
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
	 * @param int $idB
	 * @param array|int $valuesA
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
	 * @param int $idB
	 * @param array|int $valuesA
	 * @return RelationController
	 */
	function deleteB($idB,$valuesA=false) {
		return $this->deleteGen($idB, $valuesA, "B");
	}

	/**
	 * @ignore
	 */
	protected function deleteGen($id,$values,$side) {
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
				$valuesArr = Arrays::arrayize($values);
				$inv=array_fill_keys($valuesArr, false);
				foreach($this->data[$side][$id] as $ii=>$ir) {
					if (isset($inv[$ir])) unset($this->data[$side][$id][$ii]);
				}
			}
		}

		$this->clearCacheGen($otherSide);

		return $this;
	}

	/**
	 * @param int|bool $idA
	 * @return RelationController
	 */
	function loadToCacheA($idA=false) {
		return $this->loadToCacheGen($idA, "A");
	}

	/**
	 * @param int|bool $idB
	 * @return RelationController
	 */
	function loadToCacheB($idB=false) {
		return $this->loadToCacheGen($idB, "B");
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

		foreach ($req as $r) {
			if ($side=="A") {
				$ind=$r[$this->sideACol];
				$val=$r[$this->sideBCol];
			} else {
				$ind=$r[$this->sideBCol];
				$val=$r[$this->sideACol];
			}
			$this->data[$side][$ind][]=$val;
		}

		return $this;
	}

	/**
	 * @return RelationController
	 */
	function clearCacheA() {
		return $this->clearCacheGen("A");
	}

	/**
	 * @return RelationController
	 */
	function clearCacheB() {
		return $this->clearCacheGen("B");
	}

	/**
	 * @param int $sourceIdA
	 * @param int $targetIdA
	 * @return RelationController
	 */
	function duplicateA($sourceIdA,$targetIdA) {
		return $this->duplicateGen($sourceIdA,$targetIdA,"A");
	}

	/**
	 * @param int $sourceIdB
	 * @param int $targetIdB
	 * @return RelationController
	 */
	function duplicateB($sourceIdB,$targetIdB) {
		return $this->duplicateGen($sourceIdB,$targetIdB,"B");
	}

	protected function duplicateGen($sourceId,$targetId,$side) {
		$data=$this->getGen($sourceId, $side);
		return $this->setGen($targetId, $data, $side, true);
	}

	function countA($idA) {
		return $this->countGen($idA,"A");
	}

	function countB($idB) {
		return $this->countGen($idB,"B");
	}

	protected function countGen($id,$side) {
		$this->loadToCacheGen($id,$side);
		if (is_array($id)) {
			$vrat=array();
			foreach($id as $i) {
				$vrat[$i]=$this->countGen($i,$side);
			}
			return $vrat;
		} else {
			if (isset($this->data[$side][$id])) {
				return count($this->data[$side][$id]);
			}
			return 0;
		}
	}

	/**
	 * @ignore
	 */
	protected function clearCacheGen($side) {
		$this->data[$side]=array();
		$this->loadedAll[$side]=false;
		return $this;
	}

	/**
	 * @param int $a
	 * @param int $b
	 * @return boolean
	 */
	function is($a,$b,$preferredSide="A") {
		if (isset($this->data["A"][$a])) {
			return in_array($b,$this->data["A"][$a]);
		}
		if (isset($this->data["B"][$b])) {
			return in_array($a,$this->data["B"][$b]);
		}
		if ($this->loadedAll["A"] or $this->loadedAll["B"]) return false;

		if ($preferredSide=="A") {
			$allA=$this->getA($a);
			return in_array($b,$allA);
		} else {
			$allB=$this->getB($b);
			return in_array($a,$allB);
		}
	}
}
