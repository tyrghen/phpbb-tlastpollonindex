<?php
/**
 *
 * TNewspage. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2019, Tyrghen, http://tyrghen.armasites.com
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace tyrghen\lastpollonindex\migrations;

class install_acp_module extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['tyrghen_lastpollonindex_active']);
	}

	public static function depends_on()
	{
		return array('\phpbb\db\migration\data\v320\v320');
	}

	public function update_data()
	{
		return array(
			array('config.add', array('tyrghen_lastpollonindex_active', 1)),
			array('config.add', array('tyrghen_lastpollonindex_debug', 0)),
			array('config.add', array('tyrghen_lastpollonindex_poll_forum', 0)),
			array('config.add', array('tyrghen_lastpollonindex_show_poll', 1)),
			array('config.add', array('tyrghen_lastpollonindex_poll_expiry', 7)),

			array('module.add', array(
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_TLASTPOLLONINDEX_TITLE'
			)),
			array('module.add', array(
				'acp',
				'ACP_TLASTPOLLONINDEX_TITLE',
				array(
					'module_basename'	=> '\tyrghen\lastpollonindex\acp\main_module',
					'modes'				=> array('settings'),
				),
			)),
		);
	}
}
