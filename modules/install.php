<?php

//	Модуль установщика. Будет вызван в том случае, если файла с базовой конфигурацией найдено не будет.
class Install extends Base {
	//	Массив содержит информацию о шагах установщика.
	private $stepInfo = array(
		array(
			"title" => "Подготовка",
			"description" => "На данном этапе будет произведена проверка возможности установки движка на данный сайт."
		),
		array(
			"title" => "Сбор сведений",
			"description" => "Производится сбор необходимых для установки сведений."
		),
		array(
			"title" => "Создание файлов",
			"description" => "Нужные сведения были собраны и произойдёт создание файлов конфигурации."
		)
	);

	//	Название модуля.
	const MODULE_NAME = "Установка";
	
	//	Названия переменных, которые будут записаны в файл системной конфигурации.
	const CONFIG_VARNAME_DBHOST = "db_host";
	const CONFIG_VARNAME_DBLOGN = "db_login";
	const CONFIG_VARNAME_DBPASS = "db_passw";
	const CONFIG_VARNAME_DBDFDB = "db_defdb";
	
	function __construct() {
		$this->title = self::MODULE_NAME;
		$this->finalStepIndex = count($this->stepInfo);
		
		parent::__construct($_SERVER['REQUEST_URI']);
	}
	
	//	Отображение.
	//	В случае, если action не был указан, считается, что строка запроса сформирована неверно и происходит
	//	переадресация на начальный этап установки.
	public function Display($params = null) {
		if ($this->action == static::ACTION_DEFAULT) {
			Header("Location: /install/step1");
			return;
		}
		
		if (!isset($this->stepIndex)) {
			Header("Location: /install/");
			return;
		}

		$this->stepTitle = $this->stepInfo[$this->stepIndex]["title"];
		$this->stepDescription = $this->stepInfo[$this->stepIndex]["description"];

		include_once("html/install.html");
	}
	
	//	Первый шаг установки.
	//	Необходимо рассказать о последующих шагах и информации, которая будет собрана.
	public function Step1($params = null) {
		$this->page = "step1";
		$this->stepIndex = 0;

		$this->Display($params);
	}
	
	//	Второй шаг установки.
	//	Необходимо собрать сведения о конфигурации.
	public function Step2($params = null) {
		$this->page = "step2";
		$this->stepIndex = 1;

		$this->Display($params);
	}
	
	//	Третий шаг установки.
	//	Необходимо создать файл конфигурации, на основании собранных данных.
	public function Step3($params = null) {
		$this->page = "step3";
		$this->stepIndex = 2;

		$this->Display($params);
	}
	
	//	Установка завершена.
	//	Отключение скрипта установки.
	public function Finish($params = null) {
	}
}

?>