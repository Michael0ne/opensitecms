<?php

class About extends Base {
	//	Название модуля.
	public const MODULE_NAME = "О сайте";

	public function Display($params = null) {
		$this->page = "index";
		parent::Display(__CLASS__);
	}
}

?>