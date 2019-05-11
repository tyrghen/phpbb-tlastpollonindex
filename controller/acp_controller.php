<?php
/**
 *
 * TLastPollOnIndex. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2019, Tyrghen, http://tyrghen.armasites.com
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace tyrghen\lastpollonindex\controller;

/**
 * TLastPollOnIndex ACP controller.
 */
class acp_controller
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\log\log */
	protected $log;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \tyrghen\lastpollonindex\core\common */
	protected $common;

	/** @var string Custom form action */
	protected $u_action;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\config\config			$config		Config object
	 * @param \phpbb\language\language		$language	Language object
	 * @param \phpbb\log\log				$log		Log object
	 * @param \phpbb\request\request		$request	Request object
	 * @param \phpbb\template\template		$template	Template object
	 * @param \phpbb\user					$user		User object
	 * @param \tyrghen\lastpollonindex\core\common	$common		Common functions
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\language\language $language, \phpbb\log\log $log, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, \tyrghen\lastpollonindex\core\common $common)
	{
		$this->config	= $config;
		$this->language	= $language;
		$this->log		= $log;
		$this->request	= $request;
		$this->template	= $template;
		$this->user		= $user;
		$this->common	= $common;
	}

	/**
	 * Display the options a user can configure for this extension.
	 *
	 * @return void
	 */
	public function display_options()
	{
		// Add our common language file
		$this->language->add_lang('common', 'tyrghen/lastpollonindex');

		// Create a form key for preventing CSRF attacks
		add_form_key('tyrghen_lastpollonindex_acp');

		// Create an array to collect errors that will be output to the user
		$errors = array();

		// Is the form being submitted to us?
		if ($this->request->is_set_post('submit'))
		{
			// Test if the submitted form is valid
			if (!check_form_key('tyrghen_lastpollonindex_acp'))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}

			// If no errors, process the form data
			if (empty($errors))
			{
				// Set the options the user configured
				$this->config->set('tyrghen_lastpollonindex_active', (int)$this->request->variable('tyrghen_lastpollonindex_active', 1));
				$this->config->set('tyrghen_lastpollonindex_debug', (int)$this->request->variable('tyrghen_lastpollonindex_debug', 0));
				$this->config->set('tyrghen_lastpollonindex_poll_forum', (int)$this->request->variable('tyrghen_lastpollonindex_poll_forum', 0));
				$this->config->set('tyrghen_lastpollonindex_show_voters', (int)$this->request->variable('tyrghen_lastpollonindex_show_voters', 1));
				$this->config->set('tyrghen_lastpollonindex_poll_expiry', (int)$this->request->variable('tyrghen_lastpollonindex_poll_expiry', 7));

				// Add option settings change action to the admin log
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_TLASTPOLLONINDEX_SETTINGS');

				// Option settings have been updated and logged
				// Confirm this to the user and provide link back to previous page
				trigger_error($this->language->lang('ACP_TLASTPOLLONINDEX_SETTING_SAVED') . adm_back_link($this->u_action));
			}
		}

		$s_errors = !empty($errors);

		$class_methods = get_class_methods($this->common);

		// Set output variables for display in the template
		$this->template->assign_vars(array(
			'S_ERROR'		=> $s_errors,
			'ERROR_MSG'		=> $s_errors ? implode('<br />', $errors) : '',

			'U_ACTION'		=> $this->u_action,

			'TYRGHEN_TLASTPOLLONINDEX_ISACTIVE'	=> (bool) $this->config['tyrghen_lastpollonindex_active'],
			'TYRGHEN_TLASTPOLLONINDEX_DEBUG'	=> (bool) $this->config['tyrghen_lastpollonindex_debug'],
			'TYRGHEN_TLASTPOLLONINDEX_POLLFORUM'	=> (int) $this->config['tyrghen_lastpollonindex_poll_forum'],
			'TYRGHEN_TLASTPOLLONINDEX_SHOWVOTERS'	=> (bool) $this->config['tyrghen_lastpollonindex_show_voters'],
			'TYRGHEN_TLASTPOLLONINDEX_POLLEXPIRY'	=> (int) $this->config['tyrghen_lastpollonindex_poll_expiry'],
			'TYRGHEN_TLASTPOLLONINDEX_POLLFORUMS'		=> $this->common->make_forum_select($this->config['tyrghen_lastpollonindex_poll_forum'], false, true, false, false),			
		));
	}

	/**
	 * Set custom form action.
	 *
	 * @param string	$u_action	Custom form action
	 * @return void
	 */
	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}
}
