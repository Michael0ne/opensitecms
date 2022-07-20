<?php

//	Модуль установщика. Будет вызван в том случае, если файла с базовой конфигурацией найдено не будет.
class Install extends Base {
	//	Название модуля.
	const MODULE_NAME = "Установка";
	
	//	Названия переменных, которые будут записаны в файл системной конфигурации.
	const CONFIG_VARNAME_DBHOST = "db_host";
	const CONFIG_VARNAME_DBLOGN = "db_login";
	const CONFIG_VARNAME_DBPASS = "db_passw";
	const CONFIG_VARNAME_DBDFDB = "db_defdb";

	//	Необходимая для установки минимальная версия PHP.
	const DEPENDENCY_PHPVERREQ = 8;
	//	Сколько шагов предусматривает установщик.
	const STEPS_TOTAL = 3;
	
	function __construct() {
		parent::__construct($_SERVER['REQUEST_URI']);

		$this->LoadLocalisation("installation");
		$this->title = $this->Localise("page-title");
		$this->finalStepIndex = self::STEPS_TOTAL;
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

		include_once("html/install.html");
	}
	
	//	Первый шаг установки.
	//	Необходимо рассказать о последующих шагах и информации, которая будет собрана.
	public function Step1($params = null) {
		$this->page = "step1";
		$this->stepIndex = 1;

		$extensions = get_loaded_extensions();
		$params['extensions'] = $extensions;

		#	Для этого шага нужно проверить, что версия PHP соответствует минимально необходимой, а также установлено расширение работы с SQL.
		$params['sqlextensions'] = implode(", ", array_filter($extensions, function($v, $k) {
			if (substr_count($v, "sql") != null)
				return true;
			else
				return false;
		}, ARRAY_FILTER_USE_BOTH));

		$phpversion = null;
		preg_match_all("/(?:[\w]+)/", phpversion(), $phpversion);

		if ($phpversion != null) {
			$params['phpok'] = (int)$phpversion[0][0] >= self::DEPENDENCY_PHPVERREQ;
			$params['phprowstyle'] = "success";
		}else{
			$params['phprowstyle'] = "fail";
		}

		$this->Display($params);
	}
	
	//	Второй шаг установки.
	//	Необходимо собрать сведения о конфигурации.
	public function Step2($params = null) {
		$this->page = "step2";
		$this->stepIndex = 2;

		$this->Display($params);
	}
	
	//	Третий шаг установки.
	//	Необходимо создать файл конфигурации, на основании собранных данных.
	public function Step3($params = null) {
		$this->page = "step3";
		$this->stepIndex = 3;

		$this->Display($params);
	}
	
	//	Установка завершена.
	//	Отключение скрипта установки.
	public function Finish($params = null) {
	}
}

?>