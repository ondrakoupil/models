<?php

namespace OndraKoupil\Models;

use \Nette\Database\Context;
use \OndraKoupil\Tools\Arrays;

/**
 * Třída zjednodušující práci s databází s podporou cachování.
 *
 * Vychozá z DataObjekt třídy ze systému Animato
 *
 * @author Ondřej Koupil koupil@optimato.cz
 */

class DataManager extends \Nette\Object {

	/**
	 * @var Context
	 */
	protected $db;
	private $dbTableName;
	protected $dbColId = "id";

	private $isCachedAll = false;
	private $cache = array();
	protected $cacheableCols = false;

	/**
	 * Může vyhodit StopActionException, díky čemuž se zablokuje vytvořenípoložky.
	 * function(array $values, DataManager $dm)
	 * @var callable
	 */
	public $onBeforeNew = array();

	/**
	 * Zavolá se po vytvoření nového.
	 * function($id, array $values, DataManager $dm)
	 * @var callable
	 */
	public $onNew = array();

	/**
	 * Před smazáním určité položky.
	 * Může vyhodit StopActionException, díky čemuž se zablokuje smazání položky.
	 * function($id, DataManager $dm)
	 * @var callable
	 */
	public $onBeforeDelete = array();

	/**
	 * Volá se po úspěšném smazání položky.
	 * function($id, DataManager $dm)
	 * @var callable
	 */
	public $onDelete = array();

	/**
	 * Může vyhodit StopActionException, díky čemuž se zablokuje editace položky.
	 * function($id, array $changes, DataManager $dm, array $originalValues, array $newValues)
	 * @var callable
	 */
	public $onBeforeEdit = array();

	/**
	 * Zavolá se po úspěšném editování.
	 * function($id, array $changes, DataManager $dm, array $originalValues, array $newValues)
	 * @var callable
	 */
	public $onEdit = array();

	/**
	 * Zavolá se po úspěšném duplikování.
	 * function ($idOriginal, $idDuplicate, DataManager $dm)
	 * @var callable
	 */
	public $onDuplicate = array();

	const ALL = false;

	/**
	 * @param \Nette\Database\Context $db
	 * @param string $tableName
	 * @throws \InvalidArgumentException
	 */
	function __construct(Context $db, $tableName) {
		if (!$tableName or !is_string($tableName)) {
			throw new \InvalidArgumentException("DataManager needs a string as \$tableName");
		}
		$this->db=$db;
		$this->dbTableName=$tableName;
	}

	/**
	 * @return string
	 */
	function getTableName() {
		return $this->dbTableName;
	}

	/**
	 * Vrací obecně připojení na databázi
	 * @return \Nette\Database\Context
	 * @see getTable()
	 */
	function getDb() {
		return $this->db;
	}

	/**
	 * Jméno databázové tabulky
	 * @return string
	 */
	function getDbTableName() {
		return $this->dbTableName;
	}

	/**
	 * Vrací dotaz na tabulku připravený k dalšímu upřesňování.
	 * @return \Nette\Database\Table\Selection
	 */
	function getTable() {
		return $this->db->table($this->dbTableName);
	}

	/**
	 * Umožňuje specifikovat, jaké všechny sloupce se mají cachovat. Defaultně všechny.
	 * <br />Doporučení použití v konstruktoru odděděného objektu nebo v továrničce.
	 * @param array|bool $cols ALL = všechny. Jinak array.
	 * @return self
	 */
	function setCacheableCols($cols) {
		if ($cols===self::ALL) {
			$this->cacheableCols=self::ALL;
		} else {
			$this->cacheableCols=Arrays::arrayize($cols);
		}
		return $this;
	}

	/**
	 * @return array|bool
	 */
	function getCacheableCols() {
		return $this->cacheableCols;
	}

	/**
	 * @param array|bool $cols
	 * @return self
	 * @see setCachableCols
	 */
	function addCacheableCols($cols) {
		if ($cols===self::ALL) $this->cacheableCols=self::ALL;
		if (!$this->cacheableCols) return $this->setCacheableCols($cols);
		$cols=Arrays::arrayize($cols);
		if (!in_array($this->cacheableCols, $cols)) {
			$this->cacheableCols[]=$cols;
		}
		return $this;
	}

	/**
	 * Připraví dotaz na databázi s nějakými podmínkami.
	 * <br />Argumenty jsou podobné jako u Statement->where() nebo NotORM->where() a může být jeden nebo mohou být dva.
	 * @param mixed $arg1
	 * @param mixed $arg2
	 * @return \Nette\Database\Statement
	 * @see getIdsWhere
	 * @see fetchWhere
	 */
	function findBy($arg1,$arg2=null) {
		if (func_num_args()==1) {
			return $this->getTable()->where($arg1);
		} else {
			return $this->getTable()->where($arg1,$arg2);
		}
	}

	/**
	 * Rozhodne, zda zadaná hodnota může být IDčkem.
	 * @param mixed $value
	 * @return bool
	 */
	function isValueAnId($value) {
		return (is_numeric($value) and $value>=0);
	}

	/**
	 * Pokusí se pojmenovat položku.
	 * <br />Defaultně hledá pole "name", když ne, vrací string "ID #" kde # je ID.
	 * @param mixed $idOrData
	 * @return string
	 */
	function name($idOrData) {
		if ($this->isValueAnId($idOrData)) {
			$data=$this->get($idOrData,false);
		} else {
			$data=$idOrData;
		}
		if (isset($data["name"])) return $data["name"];
		if (isset($data[$this->dbColId])) return "ID ".$data[$this->dbColId];
		return "Unknown item";
	}

	/**
	 * Rozhodne, zda data jsou platná a mohou přijít do save().
	 * @param array $data Kompletní data, sloučené změny s původními hodnotami
	 * @param int|bool $originalId ID nebo false (pro vytvoření nového)
	 * @param array $changes Jen updatovaná data
	 * @return boolean
	 */
	function isValid($data,$originalId=false,$changes=null) {
		return true;
	}

	/**
	 * Získá info o položce nebo položkách. Cachuje a výsledky si pamatuje.
	 * @param int|bool|array $ids False (resp. ALL) = všechny.
	 * @param string|bool|array $fields
	 * @return mixed Skalární hodnota, jedno nebo dvourozměrné pole dle formátu parametrů.
	 * @see fetch()
	 */
	function get($ids=false,$fields=false) {
		$this->cache($ids);

		if (is_array($ids)) {
			$out=array();
			foreach($ids as $id) {
				$out[$id]=$this->getOneRow($id, $fields);
			}
			return $out;
		}

		if ($ids===false) {
			if ($fields===false) {
				return $this->cache;
			}
			$vrat=array();
			foreach($this->cache as $id=>$row) {
				$vrat[$id]=$this->getOneRow($id, $fields);
			}
			return $vrat;
		}

		return $this->getOneRow($ids, $fields);
	}

	private function getOneRow($id,$fields) {
		if ($id===null or $id==="") return null;
		if (!isset($this->cache[$id])) return null;
		if ($fields===false) {
			return $this->cache[$id];
		} elseif (!is_array($fields)) {
			if (!isset($this->cache[$id][$fields])) return null;
			return $this->cache[$id][$fields];
		} else {
			$vrat=array();
			foreach($fields as $d) {
				if (!isset($this->cache[$id][$d]) and @($this->cache[$id][$d]!==null)) $vrat[$d]=null;
				$vrat[$d]=$this->cache[$id][$d];
			}
			return $vrat;
		}
		return null;
	}

	/**
	 * Získá data o položce nebo položkách dle ID přímým dotazem na DB. Necachuje.
	 * @param int|bool|array $ids
	 * @param string|bool|array $fields
	 * @return mixed Viz get()
	 * @see get()
	 */
	function fetch($ids=false,$fields=false) {
		if ($ids!==false) {
			$output=$this->fetchWhere(
				array($this->dbColId=>$ids),
				$fields
			);
		} else {
			$output=$this->fetchWhere(false, $fields);
		}
		if (!is_array($ids) and $ids!==false) {
			if (isset($output[$ids])) $output=$output[$ids];
		}
		return $output;
	}

	/**
	 * Získá data o položce nebo položkách dle kritérií přímým dotazem na DB. Necachuje.
	 * Narozdíl od findBy() zpracuje výsledek a vrátí jedno nebo dvourozměrné pole, a ne objekt databázového výsledku.
	 * @param array|string $wheres
	 * @param bool|array|string $fields
	 * @param string|null $order
	 * @return mixed Narozdíl od fetch() vrací vždy alespoň jednorozměrné pole.
	 * @see findBy
	 * @see getIdsWhere
	 */
	function fetchWhere($wheres, $fields=false, $order = null) {
		$req=$this->getTable();
		if ($wheres!==false) $req->where($wheres);
		if ($fields!==false) {
			$req->select(Arrays::dearrayize($fields))->select($this->dbColId);
		}
		if ($order) {
			$req->order($order);
		}
		$outs=array();
		foreach($req as $row) {
			$rowId=$row[$this->dbColId];
			$outs[$rowId]=iterator_to_array($row);
			if ($fields!==false and !is_array($fields)) {
				if (isset($row[$fields])) {
					$outs[$rowId]=$row[$fields];
				} else {
					$outs[$rowId]=null;
				}
			}
		}
		return $outs;
	}

	/**
	 * Stejné jako fetchWhere, ale vrací vždy jen ten jeden nalezený záznam...
	 * @param array|string $wheres
	 * @param bool|array|string $fields
	 * @return array
	 * @throws Exceptions\DuplicateValueException Pokud výsledku vyhovuje víc než jediný záznam...
	 */
	function fetchOneWhere() {
		// TODO: tests
		$a=func_get_args();
		$output=call_user_func_array(array($this,"fetchWhere"), $a);
		if (!$output) return null;
		if (count($output)>1) {
			throw new Exceptions\DuplicateValueException("There is more than one result.");
		}
		return array_pop($output);
	}

	/**
	 * Donutí si nacachovat data o určitých položkách. Může urychlit aplikaci.
	 * @param bool|int|array $ids False (ALL), array nebo konkrétní ID
	 * @return self
	 */
	function cache($ids=false) {
		if ($this->isCachedAll) return $this;

		if ($ids===false) {
			return $this->cacheAll();
		}

		$ids=Arrays::arrayize($ids);

		$needsLoading=array();
		foreach($ids as $id) {
			if ($id===null or $id==="") continue;
			if (!array_key_exists($id,$this->cache)) {
				$needsLoading[]=$id;
			}
		}

		if ($needsLoading) {
			$result=$this->getTableWithCacheableCols(array($this->dbColId=>$needsLoading));
			foreach($result as $row) {
				$this->cache[$row[$this->dbColId]] = $row->toArray();
			}
			foreach($needsLoading as $id) {
				if (!isset($this->cache[$id])) $this->cache[$id]=null;
			}
		}

		return $this;
	}

	/**
	 * Updatuje cache, pokud je potřeba změnit jen nějakou malou hodnotu a není důvod proto reloadovat celou cache.
	 * @param int $id
	 * @param array $values Asociativní pole
	 * @return self
	 * @throws \InvalidArgumentException
	 */
	function updateCache($id,$values) {
		if (isset($this->cache[$id])) {
			if (!is_array($values)) throw new \InvalidArgumentException('$values must be an array!');
			$this->cache[$id]=$values+$this->cache[$id];
		}
		return $this;
	}

	/**
	 * Vrátí všechna nacachovaná data
	 * @return array [id] => array()
	 */
	public function getCachedData() {
		return $this->cache;
	}

	private function cacheAll() {
		$this->isCachedAll=true;
		$this->cache=array();
		foreach ($this->getTableWithCacheableCols() as $row) {
			$id=$row[$this->dbColId];
			$this->cache[$id]=iterator_to_array($row,true);
		}
		return $this;
	}

	/**
	 *
	 * @param type $where
	 * @return \Nette\Database\Statement
	 */
	private function getTableWithCacheableCols($where=self::ALL) {
		try {
			$db=$this->getTable();
			if ($where!==self::ALL) $db->where($where);
			if ($this->cacheableCols!==self::ALL) $db->select(implode(",",$this->cacheableCols))->select($this->dbColId);
			else $db->select("*");
			return $db;
		} catch (\PDOException $e) {
			throw new Exceptions\DatabaseException($this->errorMessage("cache", false, $e->getMessage()));
		}
	}

	/**
	 * Vymaže z cache všechny nebo vybrané položky
	 * @param bool|array|int $ids False = všechny položky. Jinak ID.
	 * @return self
	 */
	function clearCache($ids=false) {
		if ($ids===false) return $this->clearCacheAll();
		$ids=Arrays::arrayize($ids);
		$removedSomething=false;
		foreach($ids as $id) {
			if (isset($this->cache[$id])) {
				$removedSomething=true;
				unset($this->cache[$id]);
			}
		}
		if ($removedSomething) {
			$this->isCachedAll=false;
		}
		return $this;
	}

	private function clearCacheAll() {
		$this->isCachedAll=false;
		$this->cache=array();
		return $this;
	}

	/**
	 * Smaže položku nebo položky.
	 * <br />Používá $onDelete nebo $onBeforeDelete
	 * @param array|int $ids
	 * @return int Počet skutečně smazaných řádků
	 */
	function delete($ids) {
		$ids=Arrays::arrayize($ids);

		foreach($ids as $i=>$id) {
			try {
				$this->onBeforeDelete($id,$this);
			} catch (Exceptions\StopActionException $e) {
				unset($ids[$i]);
			}
		}

		try {
			$out=$this->getTable()->where($this->dbColId,$ids)->delete();
		} catch (\PDOException $e) {
			throw new Exceptions\DatabaseException($this->errorMessage("delete", $ids, $e->getMessage()),$e);
		}

		foreach($ids as $id) {
			$this->onDelete($id,$this);
		}
		$this->clearCache($ids);

		return $out;
	}

	protected function errorMessage($method,$ids,$originalMessage,$query="") {
		$message="";
		$message.=($this->dbTableName);
		$message.=" -> $method ";
		if ($ids) $message.="(".implode(", ",Arrays::arrayize($ids)).")";
		$message.=" fail: ";
		$message.=$originalMessage;
		$message.=($query?". Query was: $query":"");
		return $message;
	}

	/**
	 * Vloží/změní záznam.
	 * @param boolean|int $id False = vytvořit nový. ID = updatovat/vytvořit dané ID.
	 * @param array $data Data k uložení
	 * @param bool $doNotValidate True = ignorovat, když metoda isValid() vrátí false. isValid() se zavolá tak jako tak.
	 * @return null|array|\Nette\Database\Table\ActiveRow Pokud je uložení zrušeno pomocí onBeforeEdit nebo onBeforeNew, vrací null.<br />
	 * Při UPDATE vrací změny sloučené s původními daty, víceméně tedy nové hodnoty položky. Při INSERT vrací nově vytvořenou položku jako ActiveRow (a z jejího ["id"] se pak dá vyčíst přidělené ID).
	 */
	function save($id,$data,$doNotValidate=false) {

		// Checking ID
		if ($id) {
			$current=$this->get($id,false);
			if (!$current) {
				$data[$this->dbColId]=$id;
				$id=false;
			}
		}

		// Validation
		if ($id) {
			$newData=$data+$current;
			$validationResult = true;
			try {
				$validationResult=$this->isValid($newData, $id, $data);
			} catch (\Exception $e) {
				if (!$doNotValidate) {
					throw new \RuntimeException($e->getMessage(),1,$e);
				}
			}
			if (!$doNotValidate and $validationResult===false) {
				throw new \RuntimeException("DataManager ".get_class($this)."->isValid($id) returned false. Save() will not complete.");
			}
			if (is_array($validationResult)) {
				$data=$validationResult;
				$newData=$data+$current;
			}
		} else {
			$newData=$data;
			try {
				$validationResult=$this->isValid($data, $id, $data);
			} catch (\Exception $e) {
				if (!$doNotValidate) {
					throw new \RuntimeException($e->getMessage(),1,$e);
				}
			}
			if (!$doNotValidate and $validationResult===false) {
				throw new \RuntimeException("DataManager ".get_class($this)."->isValid($id) returned false. Save() will not complete.");
			}
			if (is_array($validationResult)) {
				$data=$validationResult;
				$newData=$data;
			}
		}

		// onBeforeEdit / onBeforeNew
		if ($id) {
			try {
				$this->onBeforeEdit($id,$data,$this,$current,$newData);
			} catch (Exceptions\StopActionException $e) {
				return null;
			}
		} else {
			try {
				$this->onBeforeNew($data,$this);
			} catch (Exceptions\StopActionException $e) {
				return null;
			}
		}

		// Commiting to DB
		$output=null;
		if ($id) {
			try {
				$this->getTable()->where("id",$id)->update($data);
				$output=$newData;
			} catch (\PDOException $e) {
				$message=$this->errorMessage("SAVE", $id, $e->getMessage());
				throw new Exceptions\DatabaseException($message,$e);
			}
		} else {
			try {
				$output=$this->getTable()->insert($data);
			} catch (\PDOException $e) {
				$message=$this->errorMessage("SAVE", false, $e->getMessage());
				throw new Exceptions\DatabaseException($message,$e);
			}
		}

		// onEdit / onNew
		if ($id) {
			$this->onEdit($id,$data,$this,$current,$newData);
		} else {
			$this->onNew($output["id"],$output,$this);
		}

		if ($id) {
			$this->updateCache($id, $data);
		} else {
			$this->isCachedAll=false;
		}

		return $output;
	}

	/**
	 * Hromadná změna položek přímo v databázi dle ID.
	 * <br />Nevolá isValid ani onBeforeEdit nebo onEdit
	 * @param int|array $ids
	 * @param array $updates Pole změn [dbSloupec]=>"Nová hodnota"
	 * @return  int Počet změněných řádků
	 * @throws Exceptions\DatabaseException
	 */
	function massUpdate($ids,$updates) {
		try {
			$out = $this->getTable()->where($this->dbColId,$ids)->update($updates);
		} catch (\PDOException $e) {
			throw new Exceptions\DatabaseException(
				$this->errorMessage("massUpdate", $ids, $e->getMessage()),
				$e
			);
		}
		$this->clearCache($ids);
		return $out;
	}

	/**
	 * Metoda by měla vrátit "výchozí" položku připravenou k editaci před vložením.
	 * <br />Defaultní implementace bere default hodnoty z databáze.
	 * @return array
	 */
	function defaultData() {
		$dd=$this->getDbDefaults();
		if (array_key_exists($this->dbColId, $dd)) {
			unset($dd[$this->dbColId]);
		}
		return $dd;
	}

	/**
	 * Duplikace položky.
	 * Volá onNew a lze tedy zrušit pomocí onBeforeNew.
	 * Kromě toho volá pro každou položku i onDuplicate.
	 * @param array|int $ids
	 * @return array|\Nette\Database\Table\ActiveRow Vrací zduplikované řádky. Pokud byl $ids jako array, vrací také array s indexy dle původních (!) ID, jinak rovnou vrací nový řádek.
	 * Pokud by bylo duplikování zrušeno pomocí onBeforeNew, vrací null.
	 * @throws Exceptions\DatabaseException
	 * @see duplicatePrepareData()
	 */
	function duplicate($ids) {
		$originalIds=$ids;
		$ids=Arrays::arrayize($ids);
		$dataAll=$this->get($ids,false);

		$vrat=array();
		foreach($ids as $id) {
			if (!isset($dataAll[$id])) continue;
			$data=$dataAll[$id];
			$dataPrepared=$this->duplicatePrepareData($data, $id);
			if (!$dataPrepared) {
				$dataModified=$data;
			}
			try {
				$newRow=$this->save(false, $dataPrepared);
			} catch (Exceptions\DatabaseException $e) {
				$errorMessage=$this->errorMessage("duplicate", $id, $e->getMessage());
				throw new Exceptions\DatabaseException($errorMessage,$e);
			}

			$vrat[$id]=$newRow;

			$this->onDuplicate($id, $newRow[$this->dbColId], $this);
		}

		$this->clearCache($ids);

		if (is_array($originalIds)) {
			return $vrat;
		}
		if (isset($vrat[$originalIds])) {
			return $vrat[$originalIds];
		}
		return null;
	}

	function duplicatePrepareData($data,$originalId) {
		if (isset($data[$this->dbColId])) {
			unset($data[$this->dbColId]);
		}
		return $data;
	}


	private $cacheDbSchema=null;
	private function getDbSchema() {
		if ($this->cacheDbSchema===null) {
			$cols=$this->db->query("show full columns from `".$this->getTableName()."`");
			$this->cacheDbSchema=array();
			foreach($cols as $col) {
				$this->cacheDbSchema[$col["Field"]]=array("comment"=>$col["Comment"],"default"=>$col["Default"]);
			}
		}
		if ($this->cacheDbSchema!==null) return $this->cacheDbSchema;
	}

	/**
	 * @return array [columnName] => Comment
	 */
	function getDbComments() {
		return Arrays::transform($this->getDbSchema(), true, "comment");
	}

	/**
	 * @return array [columnName] => DefaultValue
	 */
	function getDbDefaults() {
		return Arrays::transform($this->getDbSchema(), true, "default");
	}

	/**
	 * @return array
	 */
	function getDbColumns() {
		return array_keys($this->getDbSchema());
	}


	/**
	 * Vrátí náhodné ID nebo náhodná ID
	 * @param int $num
	 * @return array|int Vrací array, pokud $num > 1, jinak jen int.
	 */
	function getRandomId($num=1) {
		if ($this->isCachedAll) {
			if ($num>count($this->cache)) $num=count($this->cache);
			$x=@array_rand($this->cache,$num);
			if ($x) return $x;
			return null;
		} else {
			$u=$this->getTable()->order("rand()")->limit($num)->select($this->dbColId)->fetchPairs($this->dbColId, $this->dbColId);
			if ($num==1) {
				$a=current($u);
				if ($a) {
					return $a;
				}
				return null;
			} else {
				return array_values($u);
			}
		}
	}

	/**
	 * Vrátí všechna ID v databázi.
	 * @return array
	 */
	function getAllIds() {
		if ($this->isCachedAll) {
			return array_keys($this->cache);
		}
		return array_values($this->getTable()->select($this->dbColId)->fetchPairs($this->dbColId,$this->dbColId));
	}

	/**
	 * Najde ID určitých položek podle kritérií.
	 * @param mixed $arg1 Kritéria - podobně jako u NotORM where() metody
	 * @param mixed $arg2 Nepovinně druhý argument
	 * @return array
	 * @see findBy
	 * @see fetchWhere
	 */
	function getIdsWhere($arg1,$arg2=null) {
		$u=null;
		if (func_num_args()==1) {
			$u=$this->getTable()->where($arg1);
		} else {
			$u=$this->getTable()->where($arg1,$arg2);
		}
		$u->select($this->dbColId);
		$all=$u->fetchPairs($this->dbColId,$this->dbColId);
		return array_values($all);
	}

	/**
	 * Ověří, zda položka se zadaným ID v databázi existuje.
	 * @param array|int $ids
	 * @return boolean|array V případě $ids jako array vrací array [id] => bool.
	 */
	function exists($ids) {
		if ($this->isCachedAll) {
			if (is_array($ids)) {
				$vrat=array();
				foreach($ids as $id) {
					$vrat[$id]=isset($this->cache[$id]);
				}
				return $vrat;
			} else {
				return isset($this->cache[$ids]);
			}
		}

		if (!is_array($ids) and isset($this->cache[$ids]) and $this->cache[$ids]!==false) {
			return true;
		}

		$u=$this->getTable()->select($this->dbColId)->where($this->dbColId,$ids);
		if (is_array($ids)) {
			$vrat=array_fill_keys($ids, false);
			foreach($u as $r) {
				$vrat[$r[$this->dbColId]]=true;
			}
			return $vrat;
		} else {
			if ($u->fetch()) return true;
			return false;
		}
	}

	/**
	 * Vrátí seřazené pole ID záznamů. Určeno hlavně pro veřejnou část.
	 * <br />Pro správné fungování je třeba doimplementovat metodu sortGetPriority() a případně sortBefore() a sortUseStrings().
	 * <br />Defaultní implementace řadí vždy dle ID.
	 * @param bool|array $ids False pro všechny dostupné ID, jinak array IDček
	 * @param mixed $mode Nějaký doplňující parametr předávaný jednotlivým metodám
	 * @see sortGetPriority()
	 * @see sortBefore()
	 */
	public function sort($ids=false,$mode=null) {
		$this->sortBefore($ids, $mode);
		$priorities=array();
		if ($ids===false) $ids=$this->getAllIds();
		$ids=Arrays::arrayize($ids);
		foreach($ids as $id) {
			$priorities[$id]=$this->sortGetPriority($id, $mode);
		}
		$myIds=$ids;
		$useStrings=$this->sortUseStrings($mode);
		if ($useStrings) {
			$sortFn=function($id1,$id2) use ($priorities) {
				return strnatcasecmp($priorities[$id1], $priorities[$id2]);
			};
		} else {
			$sortFn=function($id1,$id2) use ($priorities) {
				if ($priorities[$id1]<$priorities[$id2]) return -1;
				if ($priorities[$id1]>$priorities[$id2]) return 1;
				return 0;
			};
		}
		uasort($myIds, $sortFn);

		return array_values($myIds);
	}

	/**
	 * Slouží pro přípravu k řazení, např. načtení do paměti potřebných dat. Volá se vždy jednou před provedením sort().
	 * @param array|bool $ids Viz sort(). Pozor, může být i false.
	 * @param mixed $mode
	 */
	protected function sortBefore($ids,$mode = null) {
		;
	}

	/**
	 * Měla by vrátit číselné nebo řetězcové "ohodnocení" položky s zadaným ID, podle kterého se pak položky seřadí při sort().
	 * @param int $id Konkrétní ID.
	 * @param mixed $mode
	 * @return int|string Viz sortUseStrings()
	 * @see sortUseString()
	 */
	protected function sortGetPriority($id,$mode = null) {
		return $id;
	}

	/**
	 * Měla by vrátit true, pokud pro seřazení dle daného $mode se mají výstupy z sortGetPriority() porovnávat jako řetězce
	 * a ne jako čísla. Jinak by měla vrátit false.
	 * <br />Defaultní implementace vrací false. Metodu implementuj jinak, pokud potřebuješ složitější chování.
	 * @param mixed $mode
	 * @return boolean
	 */
	protected function sortUseStrings($mode = null) {
		return false;
	}

	/**
	 * Vyfiltruje platné záznamy.
	 * @param array|bool $ids False = všechny.
	 * @param string $mode
	 * @return array Array ID
	 */
	function filter($ids=false,$mode=null) {
		$this->filterBefore($ids, $mode);
		if ($ids===false) $ids=$this->getAllIds();
		$out=array();
		foreach($ids as $id) {
			$isValid=$this->filterIsValid($id, $mode);
			if ($isValid!==false) {
				$out[]=$id;
			}
		}
		return $out;
	}

	/**
	 * Volá se vždy před iterativním voláním filterIsValid, slouží třeba k cachnutí všech relevantních záznamů.
	 * @param array|bool $ids
	 * @param mixed $mode
	 */
	protected function filterBefore($ids,$mode = null) {
		$this->cache($id);
	}

	/**
	 * Měla by vrátit true/false podle toho, zda daná položka je platná.
	 * Defaultní implementace vrací vždy true.
	 * @param int $id
	 * @param mixed $mode
	 * @return boolean
	 */
	protected function filterIsValid($id,$mode = null) {
		return true;
	}

	/**
	 * Nejdřív vyfiltruje platné záznamy a pak je seřadí.
	 * @param array|bool $ids False = všechna ID.
	 * @param mixed $mode
	 * @return array Array ID
	 */
	function filterAndSort($ids = false,$mode=null) {
		$filtered = $this->filter($ids,$mode);
		$sorted = $this->sort($filtered,$mode);
		return $sorted;
	}

}
