<?php

//	Модуль каталога товаров.
class Catalog extends Base {
	//	Информация о текущем товаре.
	public $itemInfo;
	//	Массив всех товаров каталога.
	public $items;
	//	Отзывы к текущему товару.
	public $reviews;
	//	Список доступных категорий.
	public $categories;
	
	//	Объект модуля с комментариями.
	private $commentsModule;
	
	//	Название модуля.
	const MODULE_NAME = "Каталог";
	
	function __construct() {
		parent::__construct($_SERVER['REQUEST_URI']);
	}

	private function GetItemInfo($itemID, $field = -1) {
		if (isset($this->items) && isset($this->items[$itemID]))
			return $this->items[$itemID];

		try {
			$result = $this->DB_Query("SELECT * FROM `catalog` WHERE `id` = '" . $itemID . "'", "utf8");
		} catch (Exception $e) {
			$this->error = $e->GetMessage();
		}
		
		if (!isset($result))
			return null;

		$result = $result[0];
		#	Занести поле 'options' в массив.
		if (isset($result['options'])) {
			$options = array_pop($result);
			foreach (json_decode($options, true) as $key => $value)
				$result[$key] = $value;
		}

		if ($field == -1)
			return $result;
		else
			return $result[$field];
	}
	
	//	Загрузка списка категорий.
	//	Выбирает из БД все существующие категории
	private function LoadCategories($match = null) {
		try {
			$categories = $this->DB_Query("SELECT DISTINCT `category` FROM `catalog`");
		}catch (Exception $e) {
			$this->error = $e->getMessage();
		}

		#	Получен список уникальных категорий.
		$allCategories = array();
		if (!isset($categories))
			$categories = array();

		foreach ($categories as $i => $categoryInfo) {
			$itemCategories = array_values(array_filter(explode("/", $categoryInfo["category"])));
			foreach ($itemCategories as $category)
				$allCategories[] = $category;
		}
		
		$this->categories = array_unique($allCategories);
	}

	//	Загрузка списка товаров.
	//	Если указан аргумент params, то необходимо отбирать товары подходящие под указанные критерии.
	private function LoadItems($params = null, $count = 0) {
		try {
			$query = "SELECT * FROM `catalog` ";
			$firstParam = true;

			#	Если были переданы критерии отбора, то осуществить выборку с учётом этих критериев.
			if (is_array($params)) {
				if (isset($params["onsale"])) {
					$query .= "WHERE JSON_EXTRACT(`options`, \"$.onsale\") = TRUE";
					$firstParam = false;
				}

				if (isset($params["rating"])) {
					if (!$firstParam)
						$query .= " AND ";
					else
						$query .= "WHERE ";

					$query .= "JSON_EXTRACT(`options`, \"$.rating\") >= {$params['rating']}";
					$firstParam = false;
				}

				if (isset($params["new"])) {
					if (!$firstParam)
						$query .= " AND ";
					else
						$query .= "WHERE ";

					$weekBefore = date("Y-m-d H:i:s", strtotime("-1 month"));
					$query .= "`date` BETWEEN '{$weekBefore}' AND CURRENT_TIMESTAMP";

					$firstParam = false;
				}

				if (isset($params["category"])) {
					if (!$firstParam)
						$query .= " AND ";
					else
						$query .= "WHERE ";

					$query .= "`category` LIKE '%{$params['category']}%'";
					$firstParam = false;
				}
			}
			
			if ($count > 10)
				$query .= " LIMIT 0, {$count}";

			$items = $this->DB_Query($query);
		}catch (Exception $e) {
			$this->error = $e->getMessage();
			return;
		}

		if (!isset($items))
			$items = array();

		foreach ($items as $index => $itemInfo) {
			$this->items[$itemInfo['id']] = $itemInfo;
			$this->items[$itemInfo['id']] = array_merge($this->items[$itemInfo['id']], json_decode($itemInfo['options'], true));

			if (!isset($this->items[$itemInfo['id']]['img']))
				$this->items[$itemInfo['id']]['img'] = '/images/no_image.png';
		}
	}

	//	Получить "похожие" товары.
	//	На данный момент, похожими считаются товары из любой из категорий указанного товара.
	private function LoadItemsAlike($itemID) {
		$itemInfo = $this->GetItemInfo($itemID);
		if (!isset($itemInfo))
			return;

		$itemCategory = substr($itemInfo["category"], 1);
		$this->LoadItems(array("category" => $itemCategory), 4);
		
		#	Убрать этот товар из полученного списка похожих.
		if (isset($this->items[$itemID]))
			unset($this->items[$itemID]);

		$this->itemsAlike = $this->items;
	}

	//	Отобразить товары соответствующие указанным критериям.
	//	В аргументе count передаётся максимальное кол-во товаров, которые нужно будет загрузить.
	public static function DisplayCatalogItems($params, $count) {
		$catalogModule = new Catalog;
		$catalogModule->LoadItems($params, $count);
		
		if (!isset($catalogModule->items))
			return;

		foreach ($catalogModule->items as $id => $info)
			$catalogModule->DisplayOne($id);
	}

	//	Отображение страницы каталога.
	public function Display($params = null) {
		#	Загрузить доступные товары.
		$this->LoadItems(null, isset($_GET['count']) ? $_GET['count'] : 0);

		#	Загрузить категории.
		$this->LoadCategories();

		if (!isset($this->page))
		{
			if (isset($this->items))
				$this->page = "items";
			else
				$this->page = "noitems";
		}

		parent::Display(__CLASS__);
	}

	//	Отображение одного элемента каталога.
	public function DisplayOne($id) {
		$item = $this->GetItemInfo($id);
		include("html/catalog.row.item.html");
	}

	//	Отображение позиции каталога.
	public function Item() {
		#	Список (если есть) похожих товаров.
		$this->LoadItemsAlike($this->params["item"]);

		#	Получение информации о текущем товаре.
		$this->itemInfo = $this->GetItemInfo($this->params["item"]);

		if (empty($this->itemInfo))
			$this->error = "Такого товара не существует!";
		else
			$this->page = "item";

		if (isset($this->itemInfo))
			$this->title = $this->itemInfo['title'];

		if ($this->IsModuleAvailable('comments')) {
			#require_once("modules/comments.php");
			#$this->commentsModule = new Comments;
			#$this->comments = $this->commentsModule->LoadComments($params);
		}

		parent::Display(__CLASS__);
	}

	//	Отображение позиций, входящих в данную категорию.
	public function Category() {
		$this->LoadItems(array("category" => $this->params["category"]), 0);

		#	Загрузка категорий.
		$this->LoadCategories();

		if (empty($this->items) || !empty($this->error))
			$this->error = "Не найдено соответствующих данной категории товаров.";
		else
			$this->page = "items";

		parent::Display(__CLASS__);
	}

	//	Отображение "популярных" товаров.
	public function Popular() {
		$this->LoadItems(array("rating" => 4), 0);

		#	Загрузим категории.
		$this->LoadCategories();

		$this->page = "items";

		parent::Display(__CLASS__);
	}

	//	Отображение "новых" товаров.
	public function Newgoods() {
		$this->LoadItems(array("new" => true), 0);

		#	Загрузим категории.
		$this->LoadCategories();
		
		$this->page = "items";

		parent::Display(__CLASS__);
	}
}

?>