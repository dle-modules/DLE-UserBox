<?php
/**
 * DLE UserBox
 *
 * @copyright 2018 ПафНутиЙ, LazyDev
 * @version   1.7.1
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

$userBoxLang = [
	'dateRecently' => 'недавно ',
	'dateToday' => 'сегодня ',
	'dateYesterday' => 'вчера ',
	'ratingLikeDislike' => 'Понравилось: {likes}, Не понравилось: {dislikes}, Суммарный рейтинг: {rating}',
];

$userBoxCfg = [
	'userName'    => $userName,
	'template'    => $template ?: 'default',
	'cachePrefix' => $cachePrefix ?: 'userbox',
	'cache'		  => $cache ?: '',
	'fields'      => $fields ?: ''
];

$resetCache = false;
if ($userBoxCfg['cache'] && !$config['allow_cache']) {
	$config['allow_cache'] = 1;
	$resetCache = true;
}

$cacheName = md5(implode('_', $userBoxCfg));
$showUserInfo = dle_cache($userBoxCfg['cachePrefix'], $cacheName . $config['skin'], false);

if (!function_exists('strPosArray')) {
	function strPosArray($String, $Array)
	{
		foreach ($Array as $Query) {
			if (strpos($String, $Query) !== false) {
				return true;
			}
		}
		return false;
	}
}
if (!$showUserInfo) {
	if (file_exists(TEMPLATE_DIR . '/userbox/' . $userBoxCfg['template'] . '.tpl')) {	
		$userFieldArray = [
			'email', 'name', 'news_num', 'user_id', 'comm_num', 'user_group', 'lastdate', 'reg_date', 'info', 'foto', 'fullname', 'land', 'banned', 'xfields', 'signature'
		];
		$templateFields = ['fullname', 'land', 'info', 'signature', 'banned'];
		
		if ($userBoxCfg['fields']) {
			$fieldsArray = explode(',', $userBoxCfg['fields']);
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
		$tpl->load_template('userbox/' . $userBoxCfg['template'] . '.tpl');
		
		if ($userName == $member_id['name']) {
			$row = $member_id;
		} else {
			$row = $db->super_query("SELECT " . $selectFields . " FROM " . USERPREFIX . "_users WHERE name='" . $userBoxCfg['userName'] . "'");
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
			$tpl->set('{user-group}', $user_group[$row['user_group']]['group_prefix'] . $user_group[$row['user_group']]['group_name'] . $user_group[$row['user_group']]['group_suffix']);

			if (($_TIME - $row['lastdate']) < 3600) {
				$tpl->set('{lastdate}', $userBoxLang['dateRecently']);
			} elseif (date('Ymd', $row['lastdate']) == date('Ymd', $_TIME)) {
				$tpl->set('{lastdate}', $userBoxLang['dateToday']);
			} elseif (date('Ymd', $row['lastdate']) == date('Ymd', ($_TIME - 86400))) {
				$tpl->set('{lastdate}', $userBoxLang['dateYesterday']);
			} else {
				$tpl->set('{lastdate}', langdate('j F Y H:i', $row['lastdate']));
			}
			if (strpos($tpl->copy_template, '{lastdate=') !== false) {
				$news_date = $row['lastdate'];
				$tpl->copy_template = preg_replace_callback ("#\{lastdate=(.+?)\}#i", 'formdate', $tpl->copy_template);
			}
			
			if (($_TIME - $row['reg_date']) < 3600) {
				$tpl->set('{registration}', $userBoxLang['dateRecently']);
			} elseif (date('Ymd', $row['reg_date']) == date('Ymd', $_TIME)) {
				$tpl->set('{registration}', $userBoxLang['dateToday']);
			} elseif (date('Ymd', $row['reg_date']) == date('Ymd', ($_TIME - 86400))) {
				$tpl->set('{registration}', $userBoxLang['dateYesterday']);
			} else {
				$tpl->set('{registration}', langdate('j F Y H:i', $row['reg_date']));
			}
			if (strpos($tpl->copy_template, '{registration=') !== false) {
				$news_date = $row['reg_date'];
				$tpl->copy_template = preg_replace_callback ("#\{registration=(.+?)\}#i", 'formdate', $tpl->copy_template);
			}
			
			$tpl->set('{user-name}', stripslashes($row['name']));
			$tpl->set('{user-id}', stripslashes($row['user_id']));
			
			foreach ($templateFields as $key) {
				if ($row[$key]) {
					$tpl->set_block('', ["'\\[{$key}\\](.*?)\\[/{$key}\\]'si" => '\\1', "'\\[not-{$key}\\](.*?)\\[/not-{$key}\\]'si" => '']);
					$tpl->set('{' . $key . '}', stripslashes($row[$key]));
				} else {
					$tpl->set_block('', ["'\\[{$key}\\](.*?)\\[/{$key}\\]'si" => '', "'\\[not-{$key}\\](.*?)\\[/not-{$key}\\]'si" => '\\1']);
					$tpl->set('{' . $key . '}', '');
				}
			}
			
			if (($row['lastdate'] + 1200) > $_TIME) {
				$tpl->set_block('', ["'\\[online\\](.*?)\\[/online\\]'si" => '\\1', "'\\[offline\\](.*?)\\[/offline\\]'si" => '']);
			} else {
				$tpl->set_block('', ["'\\[online\\](.*?)\\[/online\\]'si" => '', "'\\[offline\\](.*?)\\[/offline\\]'si" => '\\1']);
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
			
			$tpl->set('{new-pm}', $row['pm_unread']);
			$tpl->set('{all-pm}', $row['pm_all']);
			
			if ($row['favorites']) {
				$tpl->set('{favorite-count}', count(explode(',', $member_id['favorites'])));
			} else {
				$tpl->set('{favorite-count}', 0);
			}
			
			if ($config['allow_alt_url']) {
				$tpl->set('{user-link}', $config['http_home_url'] . 'user/' . urlencode($row['name']) . '/');
			} else {
				$tpl->set('{user-link}', $PHP_SELF . '?subaction=userinfo&amp;user=' . urlencode($row['name']));
			}
			
			if ($row['comm_num']) {
				$tpl->set_block('', ["'\\[comm-num\\](.*?)\\[/comm-num\\]'si" => '\\1', "'\\[not-comm-num\\](.*?)\\[/not-comm-num\\]'si" => '']);
				$tpl->set('', ['{comments}' => $PHP_SELF . '?do=lastcomments&amp;userid=' . $row['user_id'], '{comm-num}' => number_format($row['comm_num'], 0, ',', ' ')]);
			} else {
				$tpl->set_block('', ["'\\[comm-num\\](.*?)\\[/comm-num\\]'si" => '', "'\\[not-comm-num\\](.*?)\\[/not-comm-num\\]'si" => '\\1']);
				$tpl->set('', ['{comments}' => '', '{comm-num}' => 0]);
			}
			
			$ratingNewsBlock = [
				"'\\[rating-type-1\\](.*?)\\[/rating-type-1\\]'si" => '',
				"'\\[rating-type-2\\](.*?)\\[/rating-type-2\\]'si" => '',
				"'\\[rating-type-3\\](.*?)\\[/rating-type-3\\]'si" => '',
				"'\\[rating-type-4\\](.*?)\\[/rating-type-4\\]'si" => ''
			];
			$newsRatingType = $config['rating_type'] + 1;
			$ratingNewsBlock["'\\[rating-type-{$newsRatingType}\\](.*?)\\[/rating-type-{$newsRatingType}\\]'si"] = '\\1';
			$tpl->set_block('', $ratingNewsBlock);
			
			$ratingCommentsBlock = [
				"'\\[comments-rating-type-1\\](.*?)\\[/comments-rating-type-1\\]'si" => '',
				"'\\[comments-rating-type-2\\](.*?)\\[/comments-rating-type-2\\]'si" => '',
				"'\\[comments-rating-type-3\\](.*?)\\[/comments-rating-type-3\\]'si" => '',
				"'\\[comments-rating-type-4\\](.*?)\\[/comments-rating-type-4\\]'si" => ''
			];
			$commRatingType = $config['comments_rating_type'] + 1;
			$ratingCommentsBlock["'\\[comments-rating-type-{$commRatingType}\\](.*?)\\[/comments-rating-type-{$commRatingType}\\]'si"] = '\\1';
			$tpl->set_block('', $ratingCommentsBlock);
			
			if (strPosArray($tpl->copy_template, ['{news-rating}', '{news-rating-num}', '{news-likes}', '{news-dislikes}', '{news-rating-vote}'])) {
				$rowRating = $db->super_query("SELECT SUM(rating) as rating, SUM(vote_num) as num FROM " . PREFIX . "_post_extras WHERE user_id='{$row['user_id']}'");
				$rating = 0;
				if (!$config['rating_type']) {
					if ($rowRating['num']) {
						$rating = round(($rowRating['rating'] / $rowRating['num']), 0);
					}
					if ($rating < 0) {
						$rating = 0;
					}
					$ratingNewsNum = $rating;
					$rating = $rating * 20;

					$ratingNewsDisplay = "<div class=\"rating\" style=\"display:inline;\"><ul class=\"unit-rating\"><li class=\"current-rating\" style=\"width:{$rating}%;\">{$rating}</li></ul></div>";
					
				} elseif ($config['rating_type'] == 1) {
					if ($rowRating['num']) {
						$rating = number_format($rowRating['rating'], 0, ',', ' '); 
					}
					if ($rowRating['num'] < 0) {
						$rating = 0;
					}
					$ratingNewsNum = $rating;
					$ratingNewsDisplay = "<span class=\"ratingtypeplus\">{$rating}</span>";
					
				} elseif ($config['rating_type'] == 2 || $config['rating_type'] == 3) {
					if ($rowRating['num']) {
						$rating = number_format($rowRating['rating'], 0, ',', ' ');
					}
					$ratingNewsNum = $rating;
					
					$extraclass = 'ratingzero';
					if ($rowRating['rating'] < 0) {
						$extraclass = 'ratingminus';
					}
					if ($rowRating['rating'] > 0) {
						$extraclass = 'ratingplus';
						$rating = '+' . $rating;
					}

					if ($config['rating_type'] == 2) {
						$ratingNewsDisplay = "<span class=\"ratingtypeplusminus {$extraclass}\">{$rating}</span>";
					} else {
						$dislikesNews = ($rowRating['num'] - $rowRating['rating']) / 2;
						$likesNews = $rowRating['num'] - $dislikesNews;

						$ratingNewsDisplay = str_replace(['{likes}', '{dislikes}', '{rating}'], ["<span class=\"ratingtypeplusminus ratingplus\">{$likesNews}</span>", "<span class=\"ratingtypeplusminus ratingminus\">{$dislikesNews}</span>", "<span class=\"ratingtypeplusminus {$extraclass}\">{$rating}</span>"], $userBoxLang['ratingLikeDislike']);
					}
				}
				
				$tpl->set('', ['{news-rating}' => $ratingNewsDisplay, '{news-rating-num}' => $ratingNewsNum, '{news-likes}' => $likesNews, '{news-dislikes}' => $dislikesNews, '{news-rating-vote}' => $rowRating['num']]);
			} else {
				$tpl->set('', ['{news-rating}' => '', '{news-rating-num}' => '', '{news-likes}' => '', '{news-dislikes}' => '', '{news-rating-vote}' => '']);
			}
			
			if (strPosArray($tpl->copy_template, ['{comments-rating}', '{comments-rating-num}', '{comments-likes}', '{comments-dislikes}', '{comments-rating-vote}'])) {
				$rowCommentsRating = $db->super_query("SELECT SUM(rating) as rating, SUM(vote_num) as num FROM " . PREFIX . "_comments WHERE user_id='{$row['user_id']}'");
				$rating = 0;
				if (!$config['comments_rating_type']) {	
					if ($rowCommentsRating['num']) {
						$rating = round(($rowCommentsRating['rating'] / $rowCommentsRating['num']), 0);
					}
					if ($rating < 0) {
						$rating = 0;
					}
					$ratingCommNum = $rating;
					$rating = $rating * 20;

					$ratingCommDisplay = "<div class=\"rating\" style=\"display:inline;\"><ul class=\"unit-rating\"><li class=\"current-rating\" style=\"width:{$rating}%;\">{$rating}</li></ul></div>";
					
				} elseif ($config['comments_rating_type'] == 1) {
					if ($rowCommentsRating['num']) {
						$rating = number_format($rowCommentsRating['rating'], 0, ',', ' ');
					}
					if ($rating < 0) {
						$rating = 0;
					}
					$ratingCommNum = $rating;
					$ratingCommDisplay = "<span class=\"ratingtypeplus\">{$rating}</span>";
					
				} elseif ($config['comments_rating_type'] == 2 || $config['comments_rating_type'] == 3) {
					if ($rowCommentsRating['num']) {
						$rating = number_format($rowCommentsRating['rating'], 0, ',', ' ');
					}
					$ratingCommNum = $rating;
					
					$extraclass = 'ratingzero';
					if ($rowCommentsRating['rating'] < 0) {
						$extraclass = 'ratingminus';
					}
					if ($rowCommentsRating['rating'] > 0) {
						$extraclass = 'ratingplus';
						$rating = '+' . $rating;
					}
					
					if ($config['comments_rating_type'] == 2) {
						$ratingCommDisplay = "<span class=\"ratingtypeplusminus {$extraclass}\">{$rating}</span>";
					} else {
						$dislikesComm = ($rowCommentsRating['num'] - $rowCommentsRating['rating'])/2;
						$likesComm = $rowCommentsRating['num'] - $dislikesComm;
						
						$ratingCommDisplay = str_replace(['{likes}', '{dislikes}', '{rating}'], ["<span class=\"ratingtypeplusminus ratingplus\">{$likesComm}</span>", "<span class=\"ratingtypeplusminus ratingminus\">{$dislikesComm}</span>", "<span class=\"ratingtypeplusminus {$extraclass}\">{$rating}</span>"], $userBoxLang['ratingLikeDislike']);
					}
				}
				
				$tpl->set('', ['{comments-rating}' => $ratingCommDisplay, '{comments-rating-num}' => $ratingCommNum, '{comments-likes}' => $likesComm, '{comments-dislikes}' => $dislikesComm, '{comments-rating-vote}' => $rowCommentsRating['num']]);
			} else {
				$tpl->set('', ['{comments-rating}' => '', '{comments-rating-num}' => '', '{comments-likes}' => '', '{comments-dislikes}' => '', '{comments-rating-vote}' => '']);
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
			create_cache($userBoxCfg['cachePrefix'], $showUserInfo, $cacheName . $config['skin'], false);
			$tpl->clear();
		} elseif ($member_id['user_group'] == 1) {
			$showUserInfo = '<b style="color:red">Пользователь с логином ' . $userBoxCfg['userName'] . ' не найден.</b>';
		}
	} elseif ($member_id['user_group'] == 1) {
		$showUserInfo = '<b style="color:red">Отсутствует файл шаблона: ' . $config['skin'] . '/userbox/' . $userBoxCfg['template'] . '.tpl</b>';
	}
}

if ($resetCache) {
	$config['allow_cache'] = 0;
}

if (strpos($showUserInfo, '[this-user]') !== false || strpos($showUserInfo, '[not-this-user]') !== false) {
	if ($userName == $member_id['name']) {
		$showUserInfo = preg_replace(["'\\[this-user\\](.*?)\\[/this-user\\]'is", "'\\[not-this-user\\](.*?)\\[/not-this-user\\]'is"], ['\\1', ''], $showUserInfo);
	} else {
		$showUserInfo = preg_replace(["'\\[this-user\\](.*?)\\[/this-user\\]'is", "'\\[not-this-user\\](.*?)\\[/not-this-user\\]'is"], ['', '\\1'], $showUserInfo);
	}
}

echo $showUserInfo;
