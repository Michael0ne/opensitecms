<?php

//	Классы уточняющие кааков был характер брошенного исключения.
class ConnectionException extends Exception {}
class QueryException extends Exception {}

//	Базовый класс объединяющий в себя  необходимый функционал для работы с БД, файлами и шаблонами.
class Base {
	//	Любой модуль, наследуемый от базового имеет доступ к следующим членам класса.

	//	Заголовок текущей страницы.
	public $title;
	//	Строка содержащая текущую ошибку.
	public $error;
	//	Строка содержащая заголовок ошибки. Если не указано - то стандартное "ошибка".
	public $errortitle;
	//	ID текущей страницы.
	public $page;
	//	Название файла с шаблоном для страницы 404 ошибки.
	public $page404;
	//	Информация о магазине.
	//	TODO: вынести в другой модуль, связанный с магазином, т.к. напрямую не связано с базовым модулем.
	public $shopInfo;

	//	Доступные параметры конфигурации (не изменяемые).
	private $config;
	//	Список доступных модулей.
	private $modules;
	//	Текущий модуль.
	protected $module;
	//	Текущее действие для модуля.
	protected $action;
	//	Параметры, которые были переданы модулю.
	protected $params;
	//	Массив с именами страниц.
	protected $pageNames;
	//	Пункты меню (навигация).
	protected $navigation;

	//	Активное подключение к БД.
	private $currentDB;
	//	Объект с результатом последнего запроса к БД.
	private $lastResult;
	//	Массив, содержащий определение набора с типами расположения меню.
	private $MenuPositions = array("TOP", "RIGHT", "BOTTOM", "LEFT");
	
	//	Константы.
	
	//	Константа определяющая номер ошибки SQL, когда таблица в БД не была найдена.
	private const SQL_ERROR_TABLENOTFOUND = 1146;
	//	Модуль, который будет использоваться по умолчанию.
	public const MODULE_DEFAULT = "index";
	//	Действие, которое будет использоваться по умолчанию.
	public const ACTION_DEFAULT = "main";
	//	Папка с модулями по умолчанию.
	public const MODULES_FOLDER = "modules";

	//	Название текущего модуля.
	public const MODULE_NAME = "Базовый";

	//	Методы.

	//	Конструктор будет вызван первым.
	//	Необходимый аргумент для создания - параметры, переданные в запросе.
	function __construct($uri) {
		#	Указанный путь необходимо проверить на вменяемость.
		$urlParsed = parse_url($uri);
		if ($urlParsed === false)
			return;
		
		#	Редирект на главный модуль в том случае, если не было указано ничего.
		if (isset($urlParsed["path"]) && $urlParsed["path"] == "/")
			Header("Location: /index");
		
		#	Разобрать входную строку и узнать, какая часть является названием модуля, а какая желаемым действием модуля.
		$path = $urlParsed["path"];
		if ($path[0] == "/")
			$path = substr($path, 1);

		$pathParts = array_values(array_filter(explode("/", $path), 'strlen'));
		$this->module = $pathParts[0];
		$this->action = isset($pathParts[1]) ? $pathParts[1] : null;
		
		#	Если передано более 2 аргументов, то необходимо взять самый последний и ассоциировать его с предыдущим.
		if (count($pathParts) > 2)
		{
			array_shift($pathParts);
			$lastKey = array_shift($pathParts);
			
			$this->params[$lastKey] = $pathParts[0];
		}

		#	Параметры необходимо преобразовать в ассоциативный массив в том случае, если они были указаны.
		if (isset($urlParsed["query"]) && !empty($urlParsed["query"]))
		{
			$uriParts = explode("&", $urlParsed["query"]);
			foreach ($uriParts as $uriPart)
			{
				$keyValue = explode("=", $uriPart);
				$this->params[$keyValue[0]] = isset($keyValue[1]) ? $keyValue[1] : null;
			}
		}

		#	Сформируем список доступных модулей и проверим, что выбранный модуль в него входит.
		$this->GetAvailableModules();
		if (!$this->IsModuleAvailable($this->module))
		{
			$this->error = "Такой страницы {$this->module} не существует!";
			$this->module = self::MODULE_DEFAULT;
		}
		
		if (!isset($this->action) || empty($this->action))
			$this->action = self::ACTION_DEFAULT;
		
		#	Установка названия файла со страницей для ошибки 404.
		$this->page404 = "index";
		
		#	Загрузка имён страниц.
		if (file_exists("pagenames.json"))
			$this->pageNames = json_decode(file_get_contents("pagenames.json"), true);
		
		#	В случае, если файла конфигурации не существует - подключить модуль установщика и запустить его.
		if (!file_exists("conf.ig")) {
			if ($this->module != "install")
				Header("Location: /install");
			return;
		}
		
		#	Установить заголовок для страницы.
		$this->MakeTitle();
		
		#	Загрузка меню.
		$this->LoadMenus();
	}
	
	//	Создаёт заголовок для страницы.
	//	Основываясь на названии модуля, создаёт строку-заголовок, которая также будет содержать название сайта, если оно было указано.
	private function MakeTitle() {
		$shopName = $this->GetInfo("shopname");

		if ($shopName != null)
			$this->title = $shopName . "&nbsp;&ndash;&nbsp;" . static::MODULE_NAME;
		else
			$this->title = static::MODULE_NAME;
	}

	//	Получить значение конфигурации.
	//	Попытается получить значение параметра конфигурации, вернёт null в случае отсутствия такого параметра.
	public function GetConfigurationValue($key) {
		if (!isset($this->config))
			$this->config = @include("conf.ig");

		return isset($this->config[$key]) ? $this->config[$key] : null;
	}
	
	//	Записать ошибку.
	private function LogError($error) {
		#	TODO: реализовать.
	}
	
	//	Создаст указанную БД, если такой не найдено.
	//	В случае неудачи, будет брошено исключение.
	private function DB_MakeDB($dbName) {
		#	Если мы пришли сюда, то подключения к СУБД ещё не установлено.
		#	Однако, если оно всё же установлено, то выходим.
		if ($this->currentDB)
			return;

		try
		{
			$db = new mysqli($this->GetConfigurationValue('db_host'),
				$this->GetConfigurationValue('db_login'),
				$this->GetConfigurationValue('db_passw'),
				null);

			$result = $db->query("SHOW DATABASES LIKE '%{$dbName}%';");
			if ($result->num_rows == 0)
				#	Такой БД ещё нет, попробуем создать...
				$result = $db->query("CREATE DATABASE `{$dbName}`;");

			$result->free();
			$db->close();
		}catch(mysqli_sql_exception $e)
		{
			$this->LogError($e->getMessage());
			throw new QueryException("Не удалось создать БД!");
		}
	}
	
	//	Создаёт таблицу с указанным именем.
	//	Пытается создать таблицу по указанной схеме из файла <таблица>.scheme.
	//	Если на каком-то этапе происходит ошибка - бросается исключение.
	private function DB_MakeTable($tableName) {
		#	Необходимо, чтобы подключение к БД было установлено заранее.
		if (!$this->currentDB)
			throw new ConnectionException("Подключение к БД неактивно!");
		
		#	Попытаемся найти схему (модель) для указанной таблицы в папке 'tablescheme'.
		$tableScheme = null;
		if (file_exists("tablescheme/{$tableName}.scheme"))
			$tableScheme = file_get_contents("tablescheme/{$tableName}.scheme");

		if (empty($tableScheme))
			throw new Exception("Указанная схема таблицы пуста!");
		
		#	Преобразовать схему таблицы (JSON) в ассоциативный массив.
		$tableSchemeJson = json_decode($tableScheme, true);
		
		if (json_last_error() != JSON_ERROR_NONE)
			throw new Exception("Схема таблицы содержит ошибку синтаксиса!");
		
		#	Согласно указанной схемы (модели) таблицы, сформировать запрос и выполнить его.
		$query = "CREATE TABLE `{$tableSchemeJson['name']}` (";
		$firstField = true;
		foreach ($tableSchemeJson['fields'] as $fieldName => $fieldProperties)
		{
			if (!$firstField)
				$query .= ", ";
			
			$query .= "`{$fieldName}` ";

			#	В случае, если тип поля - SET и указано внутреннее название этого типа в движке, то попытаться найти его и подставить.
			if (substr_count($fieldProperties['type'], "SET") == 0)
				$query .= $fieldProperties['type'];
			else
				$query .= $this->ParseSetName($fieldProperties['type']);

			if (isset($tableSchemeJson['keys']) && isset($tableSchemeJson['keys'][$fieldName]))
				$query .= " {$tableSchemeJson['keys'][$fieldName]}";
			
			if (isset($fieldProperties['defval']))
				if ($fieldProperties['defval'] == "NOT NULL")
					$query .= " {$fieldProperties['defval']}";
				else
					$query .= " DEFAULT ({$fieldProperties['defval']})";

			if ($firstField)
				$firstField = false;
		}

		$query .= ");";

		#	В итоге - попытаться выполнить запрос на создание таблицы. Если завершится неуспешно, то запомнить ошибку.
		try
		{
			$queryResult = $this->currentDB->query($query);
		}catch(mysqli_sql_exception $e)
		{
			$this->LogError($e->getMessage());			
			throw new QueryException($e->getMessage());
		}
	}
	
	//	Получить реальную строку определяющую указанный MySQL набор name.
	private function ParseSetName($name) {
		if (!$name)
			return null;
		
		if (strpos($name, "$") === false)
			return null;

		#	Найдём расположение спец. символа в исходной строке.
		$setName = substr($name, strpos($name, "$") + 1, -1);
		
		#	Теперь попробуем найти определение такого набора в движке.
		if (!isset($this->$setName) || !is_array($this->$setName))
			return null;
		
		return "SET ('" . implode("','", $this->$setName) . "')";
	}

	// Установить соединение с БД согласно настроек конфигурационного файла.
	//	В случае установленного ранее соединения метод будет завершён успешно.
	//	В случае, если указанной БД не существует, будет произведена попытка её создания.
	//	В остальных случаях будет брошено исключение с пояснением, которое необходимо обработать.
	private function DB_Open() {
		if ($this->currentDB)
			return true;

		try
		{
			$this->currentDB = new mysqli($this->GetConfigurationValue('db_host'),
			$this->GetConfigurationValue('db_login'),
			$this->GetConfigurationValue('db_passw'),
			$this->GetConfigurationValue('db_defdb'));
		}catch (mysqli_sql_exception $e)
		{
			//	Если такой БД не существует...
			if (strstr($e->getMessage(), "Unknown database"))
			{
				try
				{
					$this->DB_MakeDB($this->GetConfigurationValue('db_defdb'));
				}catch(Exception $e)
				{
					$this->error = $e->getMessage();
					return false;
				}
				return $this->DB_Open();
			}
			else
			{
				$this->LogError($e->getMessage());
				$this->error = $e->getMessage();
				return false;
			}
		}
		if (!$this->currentDB)
			throw new ConnectionException("Ошибка соединения с сервером MySQL.");
		else
			return true;
	}

	//	Пытается закрыть соединение с активной БД.
	//	Возвращает результат успешности операции.
	private function DB_Close() {
		if (!$this->currentDB)
			return true;

		$this->currentDB->close();
		$this->currentDB = null;

		return $this->currentDB ? false : true;
	}

	//	Отправка запроса к БД.
	//	Опционально можно указать кодировку, которая будет использоваться.
	public function DB_Query($query, $charset = "utf8") {
		if (!$this->currentDB)
			if (!$this->DB_Open())
				return null;

		if ($charset != null)
			if (!$this->currentDB->set_charset($charset))
				throw new QueryException("Ошибка установки кодировки для соединения с бд.");

		$result_ = null;
		try
		{
			$result_ = $this->currentDB->query($query);
		}catch(mysqli_sql_exception $e)
		{
			$this->LogError($e->getMessage());

			#	Эта ошибка - не удалось найти указанную таблицу.
			if ($e->getCode() == self::SQL_ERROR_TABLENOTFOUND)
			{
				#	Надо узнать, что это за таблица.		
				$errorMessage = $e->getMessage();
				$quoteOpenPos = strpos($errorMessage, "'");
				$quoteClosePos = strpos($errorMessage, "'", $quoteOpenPos + 1);
				if ($quoteOpenPos === false || $quoteClosePos === false)
					throw new Exception("Ошибка чтения названия таблицы!");

				$queryTable = substr($errorMessage, $quoteOpenPos + 1, $quoteClosePos - $quoteOpenPos - 1);
				
				#	Остаётся лишь создать таблицу.
				try
				{
					$this->DB_MakeTable($queryTable);
				}catch (Exception $e) {
					#	В случае, если создать таблицу не удалось, то завершить выполнение записав ошибку.
					$this->error = $e->getMessage();
					return null;
				}
				return $this->DB_Query($query, $charset);
			}
		}

		if (!$result_)
			throw new QueryException("Ошибка выполнения запроса к бд.");

		$result = array();
		$result1 = array();
		$this->lastResult = $result_;

		if (!is_object($result_))
			throw new QueryException("Ошибка выполнения запроса к бд.");

		while ($result1 = $result_->fetch_assoc())
			$result[] = $result1;

		$this->DB_Close();

		if (!$result)
			return null;
		else
			return $result;
	}

	public function DB_FreeResult() {
		if (!$this->lastResult)
			return;
		
		$this->lastResult->free();
	}

	//	Узнать количество строк в выполненном запросе.
	//	Если в метод не передать ничего - то выведет количество строк в последнем запросе.
	public function DB_NumRows($result = null) {
		if (!$result)
			if (!$this->lastResult) {
				return 0;
			}else{
				$result = $this->lastResult;
			}

		if (empty($result) || !is_object($result))
			return 0;

		return $result->num_rows;
	}

	//	Получить список доступных модулей.
	//	Метод просканирует папку с модулями и сформирует массив имён файлов.
	//	Повторный вызов этого метода в текущем скрипте не будет сканировать папку повторно, а вернёт уже готовый список.
	private function GetAvailableModules() {
		if (isset($this->modules))
			return $this->modules;

		#	Просканируем директорию 'modules' на наличие модулей.
		$modules = Array();
		$modulesDir = opendir(self::MODULES_FOLDER);
		while (false !== ($module = readdir($modulesDir)))
			if (!is_dir($module))
				$modules[] = substr($module, 0, strpos($module, '.'));
		closedir($modulesDir);
		
		$this->modules = $modules;

		return $this->modules;
	}
	
	//	Вернуть пригодное для подключения имя модуля.
	//	Функция-генератор возвращает пригодное для подключения в качестве модуля имя.
	public function LoadModules() {
		if (!isset($this->modules))
			$this->GetAvailableModules();

		foreach ($this->modules as $moduleName)
		{
			$moduleName = self::MODULES_FOLDER . "/" . $moduleName . ".php";
			yield $moduleName;
		}
	}

	//	Узнать доступен ли указанный модуль.
	public function IsModuleAvailable($moduleName) {
		return in_array($moduleName, $this->GetAvailableModules());
	}
	
	//	Получить текущий выбранный модуль.
	public function GetActiveModuleName() {
		return $this->module;
	}
	
	public function GetActionName() {
		return $this->action;
	}

	// Информация о магазине.
	private function LoadStoreInfo() {
		try
		{
			$result = $this->DB_Query("SELECT * FROM `storeinfo`");
		}catch(Exception $e)
		{
			$this->error = $e->getMessage();
			return;
		}

		if (empty($result))
			return;

		foreach ($result as $i => $parameter)
			$this->shopInfo[$parameter["paramid"]] = array(
				"title" => $parameter["paramidtext"],
				"value" => $parameter["paramval"]
			);
	}

	//	Получить информацию о сайте.
	//	Делает запрос к БД чтобы получить значение указанного параметра.
	//	Если указанного параметра не существует, то вернётся null.
	public function GetInfo($fieldName) {
		if (!$fieldName)
			return null;

		if (!isset($this->shopInfo))
		{
			$this->LoadStoreInfo();
			return $this->GetInfo($fieldName);
		}
		
		if (!isset($this->shopInfo[$fieldName]))
			return null;
		else
			return $this->shopInfo[$fieldName]["value"];
	}

	//	Вывод контента.
	//	Метод подключает необходимый файл шаблона из папки HTML.
	public function Display($params = null) {
		if (!empty($params))
			include_once("html/{$params}.html");
		else
			include_once("html/{$this->module}.html");
	}

	//	Вывод страницы с ошибкой (404).
	//	Метод выведет указанную страницу, где будет сообщено о том, что запрошенный контент не был найден.
	public function Display404($page) {
		$this->error = "Такой страницы '{$page}' не существует.";

		include_once("html/{$this->page404}.html");
	}
	
	//	Загрузка доступных меню.
	//	Загрузка всех доступных пунктов меню из БД.
	private function LoadMenus() {
		$result = array();
		try
		{
			$result = $this->DB_Query("SELECT * FROM `menu` WHERE FIND_IN_SET('TOP', `position`) > 0 AND `hidden` = FALSE ORDER BY `order` ASC");
		}catch (Exception $e) {
			$this->error = $e->getMessage();
			return;
		}

		$this->navigation = array();
		#	Если по какой-то причине не удалось загрузить информацию о навигации, то
		#	оставим только ссылку на текущую страницу.
		if (!$result)
		{
			$this->navigation[strtolower(__CLASS__)] = static::MODULE_NAME;
			return;
		}

		foreach ($result as $i => $menuItem)
			$this->navigation[$menuItem["page"]] = $menuItem["title"];
	}

	/**
	* Функция возвращает окончание для множественного числа слова на основании числа и массива окончаний
	* @param  $number Integer Число на основе которого нужно сформировать окончание
	* @param  $endingsArray  Array Массив слов или окончаний для чисел (1, 4, 5),
	*         например array('яблоко', 'яблока', 'яблок')
	* @return String
	*/
	public function getNumEnding($number, $endingArray) {
		$number = $number % 100;
		if ($number>=11 && $number<=19) {
			$ending=$endingArray[2];
		}
		else {
			$i = $number % 10;
			switch ($i)
			{
				case (1): $ending = $endingArray[0]; break;
				case (2):
				case (3):
				case (4): $ending = $endingArray[1]; break;
				default: $ending=$endingArray[2];
			}
		}
		return $ending;
	}
}

?>