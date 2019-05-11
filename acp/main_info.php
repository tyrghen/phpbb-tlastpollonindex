<?php
/**
 *
 * TLastPollOnIndex. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2019, Tyrghen, http://tyrghen.armasites.com
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace tyrghen\lastpollonindex\acp;

/**
 * TLastPollOnIndex ACP module info.
 */
class main_info
{
	public function module()
	{
		return array(
			'filename'	=> '\tyrghen\lastpollonindex\acp\main_module',
			'title'		=> 'ACP_TLASTPOLLONINDEX_TITLE',
			'modes'		=> array(
				'settings'	=> array(
					'title'	=> 'ACP_TLASTPOLLONINDEX',
					'auth'	=> 'ext_tyrghen/lastpollonindex',
					'cat'	=> array('ACP_TLASTPOLLONINDEX_TITLE')
				),
			),
		);
	}
}
