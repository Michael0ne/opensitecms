<?php

//	"Стандартные" настройки PHP для корректного отображения всего и вся.
error_reporting(E_ALL);
ini_set('display_errors', 1);
Header("Content-Type: text/html;charset=utf-8");

//	Требуется подключить лишь базовый "модуль" и передать ему в качестве аргумента строку запроса.
//	С тем, что необходимо вывести на самом деле - модуль разберётся сам.
require_once("modules/base.php");

$base = new Base($_SERVER['REQUEST_URI']);
//	"Подключим" модули. Это нельзя сделать внутри класса, поэтому используем генератор.
foreach ($base->LoadModules() as $moduleName)
	require_once($moduleName);

//	Создаём экземпляр класса "модуля", передав в качестве аргумента строку с запросом.
$activeModule = new ($base->GetActiveModuleName())($_SERVER['REQUEST_URI']);
$moduleAction = $base->GetActionName();

//	Если выбранный action есть у данного метода, то вызовем его.
//	В противном случае - вызов стандартного метода Display.
if (method_exists($activeModule, $moduleAction))
	$activeModule->$moduleAction();
else
	$activeModule->Display();

?>