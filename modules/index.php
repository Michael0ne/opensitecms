<?php

class Index extends Base {
	//	Название модуля.
	public const MODULE_NAME = "Главная";

	public function Display($params = null) {
		if (!file_exists("html/{$this->module}.{$this->action}.html"))
			Header("Location: /{$this->module}");

		parent::Display(__CLASS__);
	}
}

?>