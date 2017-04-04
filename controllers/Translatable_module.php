<?php
require_once(FUEL_PATH.'/libraries/Fuel_base_controller.php');

class Translatable_module extends Fuel_base_controller {
	
	public $nav_selected = 'translatable';

	function __construct()
	{
		parent::__construct();
	}

	function index()
	{
		$vars['page_title'] = $this->fuel->admin->page_title(array(lang('module_translatable')), FALSE);
		$crumbs = array('tools' => lang('section_tools'), lang('module_translatable'));

		$this->fuel->admin->set_titlebar($crumbs, 'ico_translatable');
		$this->fuel->admin->render('_admin/translatable', $vars, '', TRANSLATABLE_FOLDER);
	}
}