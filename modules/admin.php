<?php

class Admin extends Base {
	public $sidebarNavigationMain;
	public $sidebarNavigationHelp;
	
	private $pagesManagement;
	private $pagesHelp;

	private $refererPage;
	
	//	Название модуля.
	const MODULE_NAME = "Администратор";
	
	function __construct() {
		$this->pagesManagement = array(
			"main" => "Обзор",
			"catalog" => "Каталог",
			"items" => "Товары",
			"config" => "Настройки сайта",
			"ab" => "A/B тестирование"
		);

		$this->pagesHelp = array(
			"modules" => "Модули",
			"catalog" => "Каталоги",
			"items" => "Товары",
			"tags" => "Теги"
		);
		
		parent::__construct($_SERVER['REQUEST_URI']);
	}
	
	function BuildSidebarNavigation() {
		$sidebarMain = null;
		$sidebarHelp = null;

		foreach ($this->pagesManagement as $pageName => $pageTitle)
			$sidebarMain .= "<li class=\"nav-item\"><a class=\"nav-link" . ($this->page == $pageName ? " active" : "") . "\" href=\"/admin/{$pageName}\">{$pageTitle}</a></li>";

		foreach ($this->pagesHelp as $pageName => $pageTitle)
			$sidebarHelp .= "<li class=\"nav-item\"><a class=\"nav-link" . ($this->page == "help." . $pageName ? " active" : "") . "\" href=\"/admin/help/{$pageName}\">{$pageTitle}</a></li>";

		$this->sidebarNavigationMain = $sidebarMain;
		$this->sidebarNavigationHelp = $sidebarHelp;
	}
	
	function Display($params = null) {
		if ($this->page == null)
			$this->page = "main";

		//	Боковое меню навигации.
		$this->BuildSidebarNavigation();
		
		//	TODO: Реферер.

		parent::Display(__CLASS__);
	}

	function Catalog() {
		$this->page = "catalog";

		$this->Display();
	}

	function Items() {
		$this->page = "items";
		$this->Display();
	}

	function Config() {
		$this->page = "config";
		$this->Display();
	}

	function Help() {
		$this->page = "help";
		if (isset($this->params["help"]))
			$this->page .= "." . $this->params["help"];

		$this->Display();
	}
	
	function AB() {
		$this->page = "ab";
		$this->Display();
	}
}

?>