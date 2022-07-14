//	Взято из https://learn.javascript.ru/cookie.
function getCookie(name) {
	let matches = document.cookie.match(new RegExp(
		"(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
	));
	
	return matches ? decodeURIComponent(matches[1]) : undefined;
}

function setCookie(name, value, options = {}) {
	options = {
		path: '/',
		...options
	};
	
	if (options.expires instanceof Date) {
		options.expires = options.expires.toUTCString();
	}
	
	let updatedCookie = encodeURIComponent(name) + "=" + encodeURIComponent(value);
	
	for (let optionKey in options) {
		updatedCookie += "; " + optionKey;

		let optionValue = options[optionKey];
		if (optionValue !== true)
			updatedCookie += "=" + optionValue;
	}
	
	document.cookie = updatedCookie;
}

function deleteCookie(name) {
	setCookie(name, "", { 'max-age': -1 });
}

//	При загрузке документа устанавливаем нужные обработчики событий.
document.addEventListener("DOMContentLoaded", function() {
	//	Поисковая подсказка.
	const searchTipCookieName = "tip_search_shown";
	const searchTipCookie = getCookie(searchTipCookieName);

	if (searchTipCookie === undefined)
	{
		LoadSearchTip();
		//	Установить cookie, означающее, что подсказка поиска уже была показана.
		setCookie(searchTipCookieName, true);
	}
});

function LoadSearchTip() {
	//	Найти форму поиска и добавить к ней подсказку.
	const searchForm = document.getElementById('searchform');
	if (searchForm === undefined)
	{
		console.error("Форма поиска не найдена!");
		return;
	}

	//	Загрузить данные для подсказки.
	const request = new XMLHttpRequest();
	request.open("GET", "/html/search.tip.html");
	request.onreadystatechange = function() {
		if (request.readyState != 4 || request.status != 200)
			return;

		//	Отобразить подсказку.
		searchForm.innerHTML += request.responseText;

		const searchTipBox = document.getElementsByClassName('arrow_box')[0];
		if (searchTipBox === undefined)
		{
			console.error("Форма подсказки для поиска не найдена! Проверьте вёрстку шаблона.");
			return;
		}

		const searchTipBoxContents = searchTipBox.getElementsByClassName('contents')[0];
		const searchTipToggle = searchTipBox.getElementsByTagName('a')[0];

		//	Анимировать цвет появления подсказки.
		$(searchTipBox).animate({ backgroundColor: "#88b7d5"}, 1500);

		//	Добавить возможность раскрывать текст подсказки по нажатию на ссылку.
		searchTipToggle.addEventListener("click", function(e) {
			e.preventDefault();

			$(searchTipBoxContents).slideToggle('fast');
				searchTipToggle.text = searchTipToggle.text == "развернуть" ? "свернуть" : "развернуть";

			return false;
		});
	};
	request.send();
}