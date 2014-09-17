<?php

/**
 * UserBox - Модуль вывода информации о пользователе для DLE 9.8-10.2
 * =======================================================
 * Автор: 	ПафНутиЙ
 * URL:   	http://pafnuty.name/
 * ICQ:   	817233
 * email: 	pafnuty10@gmail.com
 * =======================================================
 * Файл:  	userbox.php
 * -------------------------------------------------------
 * Версия:	1.5 (17.09.2014)
 * =======================================================
 */

// Как всегда главная строка)))

if (!defined('DATALIFEENGINE')) {
	die("Go fuck yourself!");
}

// userName должен быть строкой, хоть это и не обязательно, но проверим.
$userName = !empty($userName) ? $db->safesql(strip_tags(stripcslashes($userName))) : false;

// Если userName=this, то берём текущего пользователя.
$userName = ($userName == 'this' && $member_id['user_group'] != 5) ? $member_id['name'] : $userName;

// Если userName=thisNewsId, то берём пользователя из полной новости.
$userName = ($userName == 'thisNewsId' && $_REQUEST['newsid'] > 0) ? 'thisNewsId' : $userName;

$cfg = array(
	// Определяем переменную userName
	'userName'    => $userName,

	// Определяем дефолтный аватар, на случай если юзер не загрузил его.
	'defAvatar'   => !empty($defaultAvatar) ? $defaultAvatar : 'dleimages/noavatar.png',

	// Определяем шаблон вывода.
	'template'    => !empty($template) ? $template : 'default',

	// ID новости, из которой будет взят логин юзера.
	'newsId'      => ($userName == 'thisNewsId' && $_REQUEST['newsid'] > 0) ? (int)$_REQUEST['newsid'] : false,

	// Префикс кеша (менять не имеет смысла).
	'cachePrefix' => !empty($cachePrefix) ? $cachePrefix : 'userbox_',

	// Суффикс кеша также не имеет смысла менять, если не требуется разный вывод для разных групп пользователей.
	'cacheSuffix' => !empty($cacheSuffix) ? true : false
);
$cacheName = md5(implode('_', $cfg));

$showUserInfo = false;

// Если в строке передан обязательный параметр &userName - работаем.

if ($cfg['userName']) {

	// Попытаемся подцепить данные из кеша.
	$showUserInfo = dle_cache($cfg['cachePrefix'], $cacheName . $config['skin'], $cfg['cacheSuffix']);

	// Еcли в кеше ничего нет - работаем.
	if (!$showUserInfo) {
		if (file_exists(TEMPLATE_DIR . '/userbox/' . $cfg['template'] . '.tpl')) {

			// Массив с полями для выборки из БД и вывода в шаблон
			$arrUF = array(
				'email',
				'name',
				'news_num',
				'user_id',
				'comm_num',
				'user_group',
				'lastdate',
				'reg_date',
				'info',
				'foto',
				'fullname',
				'land'
			);
			// Если dle < 10.3 добавим поле icq
			if ($config['version_id'] < 10.3) {
				$arrUF[] = 'icq';
			}

			// Объединяем его для вставки в запрос.
			$selectFields = implode(', ', $arrUF);

			// Подрубаем наш шаблон, если он есть
			if (!isset($tpl)) {
				$tpl = new dle_template();
				$tpl->dir = TEMPLATE_DIR;
			}
			else {
				$tpl->result['showUserInfo'] = '';
			}

			$tpl->load_template('userbox/' . $cfg['template'] . '.tpl');

			// Если задан &userName=thisNewsId - добавляем запрос на получение пользователя из текущей новости.
			if ($cfg['userName'] == 'thisNewsId' && $cfg['newsId']) {
				$_username = $db->super_query("SELECT autor FROM " . PREFIX . "_post WHERE id='" . $cfg['newsId'] . "'");
			}

			$_uname = ($_username['autor']) ? $_username['autor'] : $cfg['userName'];

			// super_query побыстрее, чем обычный запрос (наверное).
			$userField = $db->super_query("SELECT " . $selectFields . ", xfields FROM " . USERPREFIX . "_users WHERE name='" . $_uname . "'");

			if ($userField['name'] === $_uname) {
				// Если имя пользователя совпадает с тем, что задано в строке подключения - работаем.

				if (count(explode("@", $userField['foto'])) == 2) {
					// Если граватар
					$userField['foto'] = 'http://www.gravatar.com/avatar/' . md5(trim($userField['foto'])) . '?s=' . intval($user_group[$userField['user_group']]['max_foto']);

				}
				else {
					// Если у нас
					if ($userField['foto'] and (file_exists(ROOT_DIR . "/uploads/fotos/" . $userField['foto'])))
						$userField['foto'] = $config['http_home_url'] . 'uploads/fotos/' . $userField['foto'];
					else
						$userField['foto'] = $config['http_home_url'] . 'templates/' . $config['skin'] . '/' . $cfg['defAvatar'];

				}

				// Получаем группу юзера
				$userField['user_group'] = $user_group[$userField['user_group']]['group_prefix'] . $user_group[$userField['user_group']]['group_name'] . $user_group[$userField['user_group']]['group_suffix'];

				// Получаем даты последнего визита и регистрации
				$userField['lastdate'] = langdate("j F Y H:i", $userField['lastdate']);
				$userField['reg_date'] = langdate("j F Y H:i", $userField['reg_date']);

				// Считаем рейтинг
				$tpl->set('{user_rating}', userrating($userField['user_id']));

				// Определяем как будет выглядеть ссылка на профиль юзера
				if ($config['allow_alt_url'] && $config['allow_alt_url'] != "no") {
					$user_page = $config['http_home_url'] . "user/" . urlencode($userField['name']) . "/";
				}
				else {
					$user_page = "$PHP_SELF?subaction=userinfo&amp;user=" . urlencode($userField['name']);
				}
				// Выводим это тегом
				$tpl->set('{user_url}', $user_page);

				// Обрабатываем теги шаблона (без копипаста и всякой херни).
				foreach ($arrUF as $field) {
					if ($userField[$field]) {
						$tpl->set('{user_' . $field . '}', $userField[$field]);
						$tpl->copy_template = preg_replace("'\\[not_user_" . $field . "\\](.*?)\\[/not_user_" . $field . "\\]'is", "", $tpl->copy_template);
						$tpl->copy_template = str_replace("[user_" . $field . "]", "", $tpl->copy_template);
						$tpl->copy_template = str_replace("[/user_" . $field . "]", "", $tpl->copy_template);

					}
					else {
						$tpl->set('{user_' . $field . '}', "");
						$tpl->copy_template = preg_replace("'\\[user_" . $field . "\\](.*?)\\[/user_" . $field . "\\]'is", "", $tpl->copy_template);
						$tpl->copy_template = str_replace("[not_user_" . $field . "]", "", $tpl->copy_template);
						$tpl->copy_template = str_replace("[/not_user_" . $field . "]", "", $tpl->copy_template);
					}
				}

				// Работаем с допполями
				if (strpos($tpl->copy_template, "[ufvalue_") !== false) {

					$xfields = xfieldsload(true);
					$xfieldsdata = xfieldsdataload($userField['xfields']);

					foreach ($xfields as $value) {
						$preg_safe_name = preg_quote($value[0], "'");

						if ($value[5] != 1) {

							if (empty($xfieldsdata[$value[0]])) {

								$tpl->copy_template = preg_replace("'\\[ufgiven_{$preg_safe_name}\\](.*?)\\[/ufgiven_{$preg_safe_name}\\]'is", "", $tpl->copy_template);
								$tpl->copy_template = str_replace("[ufnotgiven_{$preg_safe_name}]", "", $tpl->copy_template);
								$tpl->copy_template = str_replace("[/ufnotgiven_{$preg_safe_name}]", "", $tpl->copy_template);

							}
							else {
								$tpl->copy_template = preg_replace("'\\[ufnotgiven_{$preg_safe_name}\\](.*?)\\[/ufnotgiven_{$preg_safe_name}\\]'is", "", $tpl->copy_template);
								$tpl->copy_template = str_replace("[ufgiven_{$preg_safe_name}]", "", $tpl->copy_template);
								$tpl->copy_template = str_replace("[/ufgiven_{$preg_safe_name}]", "", $tpl->copy_template);
							}

							$tpl->copy_template = preg_replace("'\\[ufvalue_{$preg_safe_name}\\]'i", stripslashes($xfieldsdata[$value[0]]), $tpl->copy_template);

						}
						else {

							$tpl->copy_template = preg_replace("'\\[ufgiven_{$preg_safe_name}\\](.*?)\\[/ufgiven_{$preg_safe_name}\\]'is", "", $tpl->copy_template);
							$tpl->copy_template = preg_replace("'\\[ufvalue_{$preg_safe_name}\\]'i", "", $tpl->copy_template);
							$tpl->copy_template = preg_replace("'\\[ufnotgiven_{$preg_safe_name}\\](.*?)\\[/ufnotgiven_{$preg_safe_name}\\]'is", "", $tpl->copy_template);

						}
					}
				}

				// Компилим шаблон
				$tpl->compile('showUserInfo');

				$showUserInfo = $tpl->result['showUserInfo'];
				// Записываем результат работы в кеш.
				create_cache($cfg['cachePrefix'], $showUserInfo, $cacheName . $config['skin'], $cfg['cacheSuffix']);

				$tpl->clear();
			}
			else {
				$showUserInfo = '<b style="color:red">Пользователь с логином ' . $_uname . ' не найден.</b>';
			}


		}
		else {
			// Если шаблона нет - скажем об этом.
			$showUserInfo = '<b style="color:red">Отсутствует файл шаблона: ' . $config['skin'] . '/userbox/' . $cfg['template'] . '.tpl</b>';
		}

	}

}
else {

	// Выводим сообщение об ошибке, если строка подключения не правильная.
	$showUserInfo = '<b style="color:red">Строка подключения не содержит обязательного параметра &userName</b>';

}

// Выводим результат работы модуля.
echo $showUserInfo;
