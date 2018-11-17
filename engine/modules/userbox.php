<?php
/**
 * DLE UserBox
 *
 * @copyright 2018 ПафНутиЙ, LazyDev
 * @version   1.6.0
 * @link      https://pafnuty.name https://lazydev.pro
 * @git       https://github.com/dle-modules/DLE-UserBox
 */

if (!defined('DATALIFEENGINE')) {
	die('see ya.');
}

$userName = ($userName != 'this' && !empty($userName) ? $userName : ($userName == 'this' && $member_id['user_group'] != 5 ? $member_id['name'] : false));
if (!$userName) {
	return;
}

$userName = $db->safesql(strip_tags(stripslashes($userName)));

$cfg = [
	'userName'    => $userName,
	'template'    => $template ?: 'default',
	'cachePrefix' => $cachePrefix ?: 'userbox',
	'cache'		  => $cache ?: '',
	'fields'      => $fields ?: ''
];

$resetCache = false;
if ($cfg['cache'] && !$config['allow_cache']) {
	$config['allow_cache'] = 1;
	$resetCache = true;
}

$cacheName = md5(implode('_', $cfg));
$showUserInfo = dle_cache($cfg['cachePrefix'], $cacheName . $config['skin'], false);

if (!$showUserInfo) {
	if (file_exists(TEMPLATE_DIR . '/userbox/' . $cfg['template'] . '.tpl')) {
		$userFieldArray = [
			'email', 'name', 'news_num', 'user_id', 'comm_num', 'user_group', 'lastdate', 'reg_date', 'info', 'foto', 'fullname', 'land', 'banned', 'xfields', 'signature'
		];
		$templateFields = ['fullname', 'land', 'info', 'signature', 'banned'];
		
		if ($cfg['fields']) {
			$fieldsArray = explode(',', $cfg['fields']);
			$tempArray = [];
			foreach ($fieldsArray as $value) {
				if (!in_array($value, $userFieldArray)) {
					$tempArray[] = $db->safesql(strip_tags(stripslashes($value)));
				}
			}
			unset($fieldsArray);
			
			$userFieldArray = array_merge($userFieldArray, $tempArray);
			$templateFields = array_merge($templateFields, $tempArray);
			unset($tempArray);
		}	
		
		$selectFields = implode(', ', $userFieldArray);
		$tpl->load_template('userbox/' . $cfg['template'] . '.tpl');
		
		if ($userName == $member_id['name']) {
			$row = $member_id;
		} else {
			$row = $db->super_query("SELECT " . $selectFields . " FROM " . USERPREFIX . "_users WHERE name='" . $cfg['userName'] . "'");
		}
		
		if ($row['name']) {
			$xfields = xfieldsload(true);
			
			if (count(explode('@', $row['foto'])) == 2) {
				$tpl->set('{gravatar}', $row['foto']);	
				$tpl->set('{foto}', 'https://www.gravatar.com/avatar/' . md5(trim($row['foto'])) . '?s=' . intval($user_group[$row['user_group']]['max_foto']));
			} else {
				if ($row['foto']) {
					if (strpos($row['foto'], '//') === 0) {
						$avatar = 'http:' . $row['foto']; 
					} else {
						$avatar = $row['foto'];
					}

					$avatar = @parse_url($avatar);
					if ($avatar['host']) {
						$tpl->set('{foto}', $row['foto']);
					} else {
						$tpl->set('{foto}', $config['http_home_url'] . 'uploads/fotos/' . $row['foto']);
					}
				} else {
					$tpl->set('{foto}', '{THEME}/dleimages/noavatar.png');
				}
				$tpl->set('{gravatar}', '');
			}
			
			$tpl->set('{email}', stripslashes($row['email']));
			$tpl->set('{user-group}',  $user_group[$row['user_group']]['group_prefix'] . $user_group[$row['user_group']]['group_name'] . $user_group[$row['user_group']]['group_suffix']);
			$tpl->set('{registration}', langdate('j F Y H:i', $row['reg_date']));
			$tpl->set('{lastdate}', langdate('j F Y H:i', $row['lastdate']));
			$tpl->set('{user-name}', stripslashes($row['name']));
			$tpl->set('{user-id}', stripslashes($row['user_id']));
			
			foreach ($templateFields as $key) {
				if ($row[$key]) {
					$tpl->set_block('', ["'\\[{$key}\\](.*?)\\[/{$key}\\]'si" => '\\1', "'\\[not-{$key}\\](.*?)\\[/not-{$key}\\]'si" => '']);
					$tpl->set('{' . $key . '}', stripslashes($row[$key]));
				} else {
					$tpl->set_block('', ["'\\[{$key}\\](.*?)\\[/{$key}\\]'si" => '', "'\\[not-{$key}\\](.*?)\\[/not-{$key}\\]'si" => '\\1']);
					$tpl->set('{' . $fieldKey . '}', '');
				}
			}
			
			if ($row['news_num']) {
				if ($config['allow_alt_url']) {
					$newsUrl = $config['http_home_url'] . 'user/' . urlencode($row['name']) . '/news/';
					$rssUrl = $config['http_home_url'] . 'user/' . urlencode($row['name']) . '/rss.xml';
				} else {
					$newsUrl = $PHP_SELF . '?subaction=allnews&amp;user=' . urlencode($row['name']);
					$rssUrl = $PHP_SELF . '?mod=rss&amp;subaction=allnews&amp;user=' . urlencode($row['name']);
				}
				
				$tpl->set('', ['{news}' => $newsUrl, '{rss}' => $rssUrl, '{news-num}' => number_format($row['news_num'], 0, ',', ' ')]);
				$tpl->set_block('', ["'\\[news-num\\](.*?)\\[/news-num\\]'si" => '\\1', "'\\[not-news-num\\](.*?)\\[/not-news-num\\]'si" => '']);
			} else {
				$tpl->set('', ['{news}' => '', '{rss}' => '', '{news-num}' => 0]);
				$tpl->set_block('', ["'\\[news-num\\](.*?)\\[/news-num\\]'si" => '', "'\\[not-news-num\\](.*?)\\[/not-news-num\\]'si" => '\\1']);
			}
			
			if ($row['comm_num']) {
				$tpl->set_block('', ["'\\[comm-num\\](.*?)\\[/comm-num\\]'si" => '\\1', "'\\[not-comm-num\\](.*?)\\[/not-comm-num\\]'si" => '']);
				$tpl->set('', ['{comments}' => $PHP_SELF . '?do=lastcomments&amp;userid=' . $row['user_id'], '{comm-num}' => number_format($row['comm_num'], 0, ',', ' ')]);
			} else {
				$tpl->set_block('', ["'\\[comm-num\\](.*?)\\[/comm-num\\]'si" => '', "'\\[not-comm-num\\](.*?)\\[/not-comm-num\\]'si" => '\\1']);
				$tpl->set('', ['{comments}' => '', '{comm-num}' => 0]);
			}
			
			$xfieldsdata = xfieldsdataload($row['xfields']);
			
			foreach ($xfields as $value) {
				$preg_safe_name = preg_quote($value[0], "'");
				
				if ($xfieldsdata[$value[0]] == '') {
					$xfgiven = false;
				} else {
					$xfgiven = true;
				}
				
				if ($value[5] != 1 || ($is_logged && $member_id['user_group'] == 1) || ($is_logged && $member_id['user_id'] == $row['user_id'])) {
					if (!$xfgiven) {
						$tpl->copy_template = preg_replace("'\\[xfgiven_{$preg_safe_name}\\](.*?)\\[/xfgiven_{$preg_safe_name}\\]'is", '', $tpl->copy_template);
						$tpl->copy_template = str_replace("[xfnotgiven_{$value[0]}]", '', $tpl->copy_template);
						$tpl->copy_template = str_replace("[/xfnotgiven_{$value[0]}]", '', $tpl->copy_template);
					} else {
						$tpl->copy_template = preg_replace("'\\[xfnotgiven_{$preg_safe_name}\\](.*?)\\[/xfnotgiven_{$preg_safe_name}\\]'is", '', $tpl->copy_template);
						$tpl->copy_template = str_replace("[xfgiven_{$value[0]}]", '', $tpl->copy_template);
						$tpl->copy_template = str_replace("[/xfgiven_{$value[0]}]", '', $tpl->copy_template);
					}
					
					$tpl->set("[xfvalue_{$value[0]}]", stripslashes($xfieldsdata[$value[0]]));
				} else {
					$tpl->copy_template = preg_replace("'\\[xfgiven_{$preg_safe_name}\\](.*?)\\[/xfgiven_{$preg_safe_name}\\]'is", '', $tpl->copy_template);
					$tpl->copy_template = preg_replace("'\\[xfvalue_{$preg_safe_name}\\]'i", '', $tpl->copy_template);
					$tpl->copy_template = preg_replace("'\\[xfnotgiven_{$preg_safe_name}\\](.*?)\\[/xfnotgiven_{$preg_safe_name}\\]'is", '', $tpl->copy_template);
				}
			}
			
			$tpl->compile('showUserInfo');
			$showUserInfo = $tpl->result['showUserInfo'];
			create_cache($cfg['cachePrefix'], $showUserInfo, $cacheName . $config['skin'], false);
			$tpl->clear();
		} elseif ($member_id['user_group'] == 1) {
			$showUserInfo = '<b style="color:red">Пользователь с логином ' . $cfg['userName'] . ' не найден.</b>';
		}
	} elseif ($member_id['user_group'] == 1) {
		$showUserInfo = '<b style="color:red">Отсутствует файл шаблона: ' . $config['skin'] . '/userbox/' . $cfg['template'] . '.tpl</b>';
	}
}

if ($resetCache) {
	$config['allow_cache'] = 0;
}

echo $showUserInfo;
