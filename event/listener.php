<?php

/**
 *
 * TLastPollOnIndex. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited, https://www.phpbb.com
 * @copyright (c) 2018, kasimi, https://kasimi.net
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace tyrghen\lastpollonindex\event;

use phpbb\auth\auth;
use phpbb\collapsiblecategories\operator\operator as cc_operator;
use phpbb\config\config;
use phpbb\db\driver\driver_interface as db_interface;
use phpbb\event\data;
use phpbb\event\dispatcher_interface;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	/** @var user */
	protected $user;

	/** @var language */
	protected $lang;

	/** @var auth */
	protected $auth;

	/** @var request_interface */
	protected $request;

	/** @var config */
	protected $config;

	/** @var db_interface */
	protected $db;

	/** @var template */
	protected $template;

	/** @var dispatcher_interface */
	protected $dispatcher;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/** @var cc_operator */
	protected $cc_operator;

	/**
	 * @param template $template
	 */
	public function __construct(
		user $user,
		language $lang,
		auth $auth,
		request_interface $request,
		config $config,
		db_interface $db,
		template $template,
		dispatcher_interface $dispatcher,
		$root_path,
		$php_ext,
		cc_operator $cc_operator = null
	)
	{
		$this->user			= $user;
		$this->lang			= $lang;
		$this->auth			= $auth;
		$this->request		= $request;
		$this->config		= $config;
		$this->db			= $db;
		$this->template		= $template;
		$this->dispatcher	= $dispatcher;
		$this->root_path	= $root_path;
		$this->php_ext		= $php_ext;
		$this->cc_operator	= $cc_operator;
	}

	/**
	 * @return array
	 */
	public static function getSubscribedEvents()
	{
		return [
			'core.index_modify_page_title'		=> 'index_modify_page_title',
			// 'core.submit_post_modify_sql_data'	=> 'submit_post_modify_sql_data',
		];
	}

	/**
	 *
	 */
	public function index_modify_page_title()
	{
		$l_debug = array();

		$forum_list = array();

		$top_forum = $this->sql_query([
			'SELECT'	=> 'f.forum_id, f.forum_name, f.parent_id, f.forum_type, f.forum_flags, f.forum_options, f.left_id, f.right_id',
			'FROM'		=> [FORUMS_TABLE => 'f'],
			'WHERE'		=> 'forum_id = ' . (int) $this->config['tyrghen_lastpollonindex_poll_forum'],
		]);
		
		$l_debug[] = "<h3>TOP FORUM</h3><p>" . print_r($top_forum,true) . "</p>";

        if (isset($top_forum) && count($top_forum))
        {
			if (($top_forum[0]['right_id'] - $top_forum[0]['left_id']) > 1)
			{
				$forum_tree = $this->sql_query([
					'SELECT'	=> 'f.forum_id, f.forum_name, f.parent_id, f.forum_type, f.forum_flags, f.forum_options, f.left_id, f.right_id',
					'FROM'		=> [FORUMS_TABLE => 'f'],
					'WHERE'		=> 'f.left_id >= ' . $top_forum[0]['left_id'] . ' AND f.right_id <= ' . $top_forum[0]['right_id'],
					'ORDER_BY'	=> 'f.left_id ASC',
				]);
	
				foreach ($forum_tree as $row) {
					$forum_list[(int) $row['forum_id']] = $row;
				}
			}
			else
			{
				$forum_list[(int) $top_forum[0]['forum_id']] = $top_forum[0];
			}
			$l_debug[] = "<h3>FORUM LIST</h3><p>" . print_r($forum_list ,true) . "</p>";

			$topics = $this->sql_query([
				'SELECT'	=> 't.topic_id, t.topic_title, t.topic_first_post_id, t.topic_status, t.poll_start, t.poll_length, t.poll_title, t.poll_max_options, t.poll_vote_change, t.forum_id, f.forum_status',
				'FROM'		=> [TOPICS_TABLE => 't'],
				'LEFT_JOIN'	=> [
					[
						'FROM'	=> [FORUMS_TABLE => 'f'],
						'ON'	=> 'f.forum_id = t.forum_id',
					],
				],
				// 'WHERE'		=> 't.forum_id in ('. implode(',', array_keys($forum_list)) .') AND t.poll_start <> 0',
				'WHERE'		=> 't.forum_id in ('. implode(',', array_keys($forum_list)) .') AND t.poll_start <> 0 AND ((t.poll_length = 0 AND  t.poll_start + ' . ((int) $this->config['tyrghen_lastpollonindex_poll_expiry'] * 86400) . ' >= ' . time() . ') OR (t.poll_length > 0 AND t.poll_start + t.poll_length >= ' . time() . '))',
				'ORDER_BY'	=> 't.poll_start DESC, t.poll_start + t.poll_length DESC',
				'LIMIT' 	=> '1'
			]);
			$l_debug[] = "<h3>TOPIC WHERE CLAUSE</h3><p>" . 
				't.forum_id in ('. implode(',', array_keys($forum_list)) .') AND t.poll_start <> 0 AND ((t.poll_length = 0 AND  t.poll_start + ' . ((int) $this->config['tyrghen_lastpollonindex_poll_expiry'] * 86400) . ' >= ' . time() . ') OR (t.poll_length > 0 AND t.poll_start + t.poll_length >= ' . time() . '))'
			 	. "</p>";
				
			if ($topics)
			{
				$this->lang->add_lang('viewtopic');
				$this->lang->add_lang('common', 'tyrghen/lastpollonindex');
			}
			$l_debug[] = "<h3>TOPICS</h3><p>" . print_r($topics,true) . "</p>";
	
			foreach ($topics as $topic_data)
			{
				if (!$this->auth->acl_get('f_read', $topic_data['forum_id']))
				{
					continue;
				}
	
				$poll_info = $this->sql_query([
					'SELECT'	=> 'o.*, p.bbcode_bitfield, p.bbcode_uid',
					'FROM'		=> [POLL_OPTIONS_TABLE => 'o', POSTS_TABLE => 'p'],
					'WHERE'		=> 'o.topic_id = ' . (int) $topic_data['topic_id'] . ' AND p.post_id = ' . (int) $topic_data['topic_first_post_id'] . ' AND p.topic_id = o.topic_id',
					'ORDER_BY'	=> 'o.poll_option_id',
				]);
	
				$vote_counts = [];
	
				foreach ($poll_info as $row)
				{
					$option_id = (int) $row['poll_option_id'];
					$vote_counts[$option_id] = (int) $row['poll_option_total'];
				}

				$poll_votes = $this->sql_query([
					'SELECT'	=> 'pv.poll_option_id, pv.vote_user_id
									,u.username',
					'FROM'		=> [POLL_VOTES_TABLE => 'pv'],
					'LEFT_JOIN'	=> [
						[
							'FROM'	=> [USERS_TABLE => 'u'],
							'ON'	=> 'pv.vote_user_id = u.user_id',
						],
					],
						'WHERE'		=> 'topic_id = ' . (int) $topic_data['topic_id'],
				]);
	
				$voters = [];
				foreach ($poll_votes as $row)
				{
					$option_id = (int) $row['poll_option_id'];
					$option_voters = $voters[$option_id];
					if (!isset($option_voters))
					{
						$option_voters = array();
					}
					$option_voters[] = $row['username'];
					$voters[$option_id] = $option_voters;
				}
				$l_debug[] = "<h3>VOTERS</h3><p>" . print_r($voters,true) . "</p>";


				if ($this->user->data['is_registered'])
				{
					$cur_voted_id = $this->sql_query([
						'SELECT'	=> 'poll_option_id',
						'FROM'		=> [POLL_VOTES_TABLE => 'pv'],
						'WHERE'		=> 'topic_id = ' . (int) $topic_data['topic_id'] . ' AND vote_user_id = ' . (int) $this->user->data['user_id'],
					], 'poll_option_id');
				}
				else
				{
					$cookie_name = $this->config['cookie_name'] . '_poll_' . $topic_data['topic_id'];
					$cur_voted_id = $this->request->variable($cookie_name, '', true, request_interface::COOKIE);
					$cur_voted_id = $cur_voted_id ? explode(',', $cur_voted_id) : [];
					$cur_voted_id = array_map('intval', $cur_voted_id);
				}
	
				$s_can_edit = $this->auth->acl_get('f_vote', $topic_data['forum_id']);
				// Can not vote at all if no vote permission
				$s_can_vote = $this->auth->acl_get('f_vote', $topic_data['forum_id']) &&
					(($topic_data['poll_length'] != 0 && $topic_data['poll_start'] + $topic_data['poll_length'] > time()) || $topic_data['poll_length'] == 0) &&
					$topic_data['topic_status'] != ITEM_LOCKED &&
					$topic_data['forum_status'] != ITEM_LOCKED &&
					(!sizeof($cur_voted_id) ||
					($this->auth->acl_get('f_votechg', $topic_data['forum_id']) && $topic_data['poll_vote_change']));
				$s_display_results = !$s_can_vote || $cur_voted_id;
	
				/**
				* Event to manipulate the poll data
				*
				* @event core.viewtopic_modify_poll_data
				* @var	array	cur_voted_id				Array with options' IDs current user has voted for
				* @var	int		forum_id					The topic's forum id
				* @var	array	poll_info					Array with the poll information
				* @var	bool	s_can_vote					Flag indicating if a user can vote
				* @var	bool	s_display_results			Flag indicating if results or poll options should be displayed
				* @var	int		topic_id					The id of the topic the user tries to access
				* @var	array	topic_data					All the information from the topic and forum tables for this topic
				* @var	string	viewtopic_url				URL to the topic page
				* @var	array	vote_counts					Array with the vote counts for every poll option
				* @var	array	voted_id					Array with updated options' IDs current user is voting for
				* @since 3.1.5-RC1
				*/
				$vars = [
					'cur_voted_id',
					'forum_id',
					'poll_info',
					's_can_vote',
					's_display_results',
					'topic_id',
					'topic_data',
					'viewtopic_url',
					'vote_counts',
					'voted_id',
				];
				extract($this->dispatcher->trigger_event('core.viewtopic_modify_poll_data', compact($vars)));
	
				if ($s_can_vote)
				{
					add_form_key('posting');
				}
	
				$poll_option_total = array_column($poll_info, 'poll_option_total');
				$poll_total = array_sum($poll_option_total);
				$poll_most = max($poll_option_total);
	
				$parse_flags = ($poll_info[0]['bbcode_bitfield'] ? OPTION_FLAG_BBCODE : 0) | OPTION_FLAG_SMILIES;
	
				for ($i = 0, $size = sizeof($poll_info); $i < $size; $i++)
				{
					$poll_info[$i]['poll_option_text'] = generate_text_for_display($poll_info[$i]['poll_option_text'], $poll_info[$i]['bbcode_uid'], $poll_info[$i]['bbcode_bitfield'], $parse_flags, true);
				}
	
				$topic_data['poll_title'] = generate_text_for_display($topic_data['poll_title'], $poll_info[0]['bbcode_uid'], $poll_info[0]['bbcode_bitfield'], $parse_flags, true);
	
				$poll_options_template_data = [];
	
				foreach ($poll_info as $poll_option)
				{
					$option_pct = $poll_total > 0 ? $poll_option['poll_option_total'] / $poll_total : 0;
					$option_pct_txt = sprintf("%.1d%%", round($option_pct * 100));
					$option_pct_rel = $poll_most > 0 ? $poll_option['poll_option_total'] / $poll_most : 0;
					$option_pct_rel_txt = sprintf("%.1d%%", round($option_pct_rel * 100));
					$option_most_votes = $poll_option['poll_option_total'] > 0 && $poll_option['poll_option_total'] == $poll_most;
					$option_voters = '';
					if (isset($voters[$poll_option['poll_option_id']]))
					{
						$option_voters = implode(", ", $voters[$poll_option['poll_option_id']]);
					}


					$poll_options_template_data[] = [
						'POLL_OPTION_ID' 			=> $poll_option['poll_option_id'],
						'POLL_OPTION_CAPTION' 		=> $poll_option['poll_option_text'],
						'POLL_OPTION_RESULT' 		=> $poll_option['poll_option_total'],
						'POLL_OPTION_PERCENT' 		=> $option_pct_txt,
						'POLL_OPTION_PERCENT_REL' 	=> $option_pct_rel_txt,
						'POLL_OPTION_PCT'			=> round($option_pct * 100),
						'POLL_OPTION_WIDTH'			=> round($option_pct * 250),
						'POLL_OPTION_VOTED'			=> in_array($poll_option['poll_option_id'], $cur_voted_id),
						'POLL_OPTION_MOST_VOTES'	=> $option_most_votes,
						'POLL_OPTION_VOTERS'		=> $option_voters,
					];
				}
				$l_debug[] = "<h3>POLL OPTIONS TEMPLATE DATA</h3><p>" . print_r($poll_options_template_data,true) . "</p>";
	
				$poll_end = $topic_data['poll_length'] + $topic_data['poll_start'];
	
				$viewtopic_url = append_sid($this->root_path . 'viewtopic.' . $this->php_ext, ['f' => $topic_data['forum_id'], 't' => $topic_data['topic_id']]);
	
				$poll_template_data = [
					'TOPIC_TITLE'			=> $topic_data['topic_title'],
					'POLL_QUESTION'			=> $topic_data['poll_title'],
					'TOTAL_VOTES' 			=> $poll_total,
					'POLL_LEFT_CAP_IMG'		=> $this->user->img('poll_left'),
					'POLL_RIGHT_CAP_IMG'	=> $this->user->img('poll_right'),
	
					'L_MAX_VOTES'			=> $this->lang->lang('MAX_OPTIONS_SELECT', (int) $topic_data['poll_max_options']),
					'L_POLL_LENGTH'			=> $topic_data['poll_length'] ? $this->lang->lang($poll_end > time() ? 'POLL_RUN_TILL' : 'POLL_ENDED_AT', $this->user->format_date($poll_end)) : '',
	
					'S_HAS_POLL'			=> true,
					'S_CAN_VOTE'			=> $s_can_vote,
					'S_CAN_EDIT'			=> $s_can_edit,
					'S_DISPLAY_RESULTS'		=> $s_display_results,
					'S_SHOW_VOTERS'			=> (bool) $this->config['tyrghen_lastpollonindex_show_voters'],
					'S_IS_MULTI_CHOICE'		=> $topic_data['poll_max_options'] > 1,
					'S_POLL_ACTION'			=> $viewtopic_url,
	
					'U_VIEW_RESULTS'		=> $viewtopic_url . '&amp;view=viewpoll',
				];
				$l_debug[] = "<h3>POLL TEMPLATE DATA</h3><p>" . print_r($poll_template_data,true) . "</p>";
	
				if ($this->cc_operator !== null)
				{
					$fid = 'poll_' . $topic_data['topic_id'];
					$poll_template_data = array_merge($poll_template_data, [
						'S_CAN_HIDE_CATEGORY'		=> true,
						'S_CATEGORY_HIDDEN'			=> $this->cc_operator->is_collapsed($fid),
						'U_CATEGORY_COLLAPSE_URL'	=> $this->cc_operator->get_collapsible_link($fid),
					]);
				}
	
				/**
				* Event to add/modify poll template data
				*
				* @event core.viewtopic_modify_poll_template_data
				* @var	array	cur_voted_id					Array with options' IDs current user has voted for
				* @var	int		poll_end						The poll end time
				* @var	array	poll_info						Array with the poll information
				* @var	array	poll_options_template_data		Array with the poll options template data
				* @var	array	poll_template_data				Array with the common poll template data
				* @var	int		poll_total						Total poll votes count
				* @var	int		poll_most						Mostly voted option votes count
				* @var	array	topic_data						All the information from the topic and forum tables for this topic
				* @var	string	viewtopic_url					URL to the topic page
				* @var	array	vote_counts						Array with the vote counts for every poll option
				* @var	array	voted_id						Array with updated options' IDs current user is voting for
				* @since 3.1.5-RC1
				*/
				$vars = [
					'cur_voted_id',
					'poll_end',
					'poll_info',
					'poll_options_template_data',
					'poll_template_data',
					'poll_total',
					'poll_most',
					'topic_data',
					'viewtopic_url',
					'vote_counts',
					'voted_id',
				];
				extract($this->dispatcher->trigger_event('core.viewtopic_modify_poll_template_data', compact($vars)));
	
				$this->template->assign_block_vars('polls', $poll_template_data);
	
				$this->template->assign_block_vars_array('polls.poll_options', $poll_options_template_data);

			}
		}
		if ($this->config['tyrghen_lastpollonindex_debug'])
		{
			$this->template->assign_vars(array(
				'poll_debug'	=> implode("<br/>",$l_debug),
			));
		}
	}

	// /**
	//  * @param data $event
	//  */
	// public function submit_post_modify_sql_data(data $event)
	// {
	// 	if (in_array($event['post_mode'], ['post', 'edit_topic', 'edit_first_post']) && $this->auth->acl_get('f_poll_on_index', $event['data']['forum_id']))
	// 	{
	// 		$sql_data = $event['sql_data'];
	// 		$sql_data[TOPICS_TABLE]['sql']['poll_on_index'] = $this->request->variable('poll_on_index', ext::POLL_ON_INDEX_NO);
	// 		$event['sql_data'] = $sql_data;
	// 	}
	// }

	/**
	 * @param array $sql_array
	 * @param string $column
	 * @return array
	 */
	protected function sql_query(array $sql_array, $column = '')
	{
		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		return $column ? array_column($rows, $column) : $rows;
	}
}
