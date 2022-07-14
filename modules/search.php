<?php

class Search extends Base {
	public $searchq;
	public $searchr;
	
	private const MODULENAME = "Поиск";

	function Display($params = null) {
		// Выстраиваем навигацию.
		$this->LoadNavigation();
		// Информация о сайте.
		$this->LoadStoreInfo();
		// Название страницы.
		$this->title = $this->GetInfo('shopname') . " &ndash; " . self::MODULENAME;

		$this->searchq = !empty($_GET['s']) ? urldecode($_GET['s']) : null;

		if (substr_count($this->searchq, "\'") > 0 || substr_count($this->searchq, "%") > 0)
			$this->searchq = null;

		$this->Search();

		if (empty($this->searchr))
			$this->page = "noresult";
		else
			$this->page = "result";

		parent::Display(__CLASS__);
	}

	function Search() {
		if (!isset($this->searchq) || $this->searchq == null)
			return;
		
		# Парсер входного поискового запроса для поиска ключевых слов.
		$keywords = array(
			array(
				"tokenname" => "цена",
				"sql" => "`options` LIKE '%\"price\": $1%'"
			),
			array(
				"tokenname" => "цвет",
				"sql" => "`options` LIKE '%\"color\": $1%'"
			),
			array(
				"tokenname" => "распродажа",
				"sql" => "`options` LIKE '%\"onsale\": true%'"
			),
			array(
				"tokenname" => "скидка",
				"sql" => "`options` LIKE '%\"discount\": $1%'"
			),
			array(
				"tokenname" => "категория",
				"sql" => "`category` LIKE '%$1%'"
			)
		);
		
		$this->searchq = explode(" ", $this->searchq);
		
		$sql = "SELECT * FROM `catalog` WHERE ";
		
		foreach ($this->searchq as $searchTerm)
		{
			$searchTermOperatorPos = array(
				strpos($searchTerm, '<'),
				strpos($searchTerm, '>'),
				strpos($searchTerm, ':')
			);
			$searchTermOperator = null;
			if ($searchTermOperatorPos !== false)
				$searchTermOperator = $searchTerm[$searchTermOperatorPos];

			# Узнать, является ли текущее слово - "ключевым".
			foreach ($keywords as $index => $tokenInfo)
			{
				if (strstr($searchTerm, $tokenInfo["tokenname"]))
				{
					$searchTermVal = substr($searchTerm, strlen($tokenInfo["tokenname"]) + 1);
					$sql .= str_replace("$1", $searchTermVal, $tokenInfo["sql"]) . " AND ";
				}
			}
		}
		
		if ($sql[strlen($sql) - 1] == " ")
			$sql = substr($sql, 0, -5);
		
		print $sql;

		exit();

		try {
			$result = $this->DB_Query("SELECT * FROM `catalog` WHERE `category` LIKE '%" . $this->searchq . "%' OR `title` LIKE '" . $this->searchq . "' OR `description` LIKE '%" . $this->searchq . "%'", "utf8");

			$this->searchr = $result;
		}catch (Exception $e) {
			$this->error = $e->getMessage();
		}
	}

	function Highlight($s) {
		return str_replace($this->searchq, "<i>" . $this->searchq . "</i>", $s);
	}
}

?>