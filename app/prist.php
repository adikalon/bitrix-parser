<?php

require __DIR__.'/../core.php';

Logger::send("|СТАРТ| - Скрипт запущен. Парсинг из ".PARSER_NAME);

$pause = 0;
$options = [
	CURLOPT_HTTPHEADER => [
		"Host: prist.ru",
		"Connection: keep-alive",
		"Cache-Control: max-age=0",
		"User-Agent: Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.84 Safari/537.36",
		"Upgrade-Insecure-Requests: 1",
		"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8",
		"Accept-Encoding: deflate",
		"Accept-Language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7",
	]
];

// Список основного оглавления раздела
function getHeaders() {
	global $pause, $options;
	$html = Request::curl('http://prist.ru/produce/prices/meas.htm', $pause, $options);
	$html = iconv('windows-1251', 'utf-8', $html);
	$dom = phpQuery::newDocument($html);
	$elements = $dom->find("div[style='background-color: #F9F9F9; padding: 5px; margin: 5px;']");
	$headers = [];
	foreach ($elements as $element) {
		$headers[] = [
			'title' => pq($element)->find('h2>a')->text(),
			'link' => 'http://prist.ru'.pq($element)->find('h2>a')->attr('href')
		];
		unset($element);
	}
	$dom->unloadDocument();
	unset($pause, $html, $dom, $elements);
	return $headers;
}

// Разбор страницы товара
function parseGood($link, $title) {
	global $pause, $options;
	$html = Request::curl($link, $pause, $options);
	$html = iconv('windows-1251', 'utf-8', $html);
	$dom = phpQuery::newDocument($html);
	
	// Тут отправляем врайтеру
	
	Logger::send('|ТОВАР| - Товар: "'.$title.'" добавлен.'); // Поменять $title на название товара
	$dom->unloadDocument();
	unset($link, $title, $html, $dom);
}

// Проверка на последнюю страницу
function lastPage($match) {
	$cur = (int)$match['cur'];
	$last = (int)$match['last'];
	if ($cur == $last) {
		unset($match, $cur, $last);
		return true;
	}
	unset($match, $cur, $last);
	return false;
}

// Обход ссылок объявлений
function sectionParse($section) {
	global $pause, $options;
	for ($p = 1; true; $p++) {
		Logger::send('|КАТЕГОРИЯ| - Обход категории: "'.$section['title'].'". Страница: '.$p);
		$html = Request::curl($section['link'].'&p='.$p, $pause, $options);
		$html = iconv('windows-1251', 'utf-8', $html);
		preg_match('/.*<strong>(?<cur>\d+)<\/strong>\s*из\s*<strong>(?<last>\d+)<\/strong>.*/', $html, $match);
		$dom = phpQuery::newDocument($html);
		$a = $dom->find('a[href^=/produce/card/]');
		$cur = '';
		foreach ($a as $href) {
			$link = 'http://prist.ru'.pq($href)->attr('href');
			if ($link != $a) {
				$a = $link;
				parseGood($link, $section['title']);
			}
			unset($href, $link);
		}
		$dom->unloadDocument();
		unset($html, $dom, $a, $cur);
		if (lastPage($match)) {
			unset($match);
			break;
		}
		unset($match);
	}
	Logger::send('|КАТЕГОРИЯ| - Окончен обход категории: "'.$section['title'].'".');
	unset($section);
}

foreach (getHeaders() as $section) {
	sectionParse($section);
	unset($section);
}