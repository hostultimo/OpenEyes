<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

/**
 * A base controller class that helps display the firm dropdown and the patient name.
 * It is extended by all non-admin controllers.
 */

class BaseController extends Controller
{
	public $jsVars = array();
	protected $css = array();

	protected $selected_firm_id;
	protected $selected_site_id;
	protected $patient_id;
	protected $patient_name;
	protected $_firms;
	/**
	 * return the id of the currently selected firm
	 *
	 * @return int|null
	 */
	public function getSelectedFirmId()
	{
		if (!$this->selected_firm_id) {
			$this->selected_firm_id = Yii::app()->session->get('selected_firm_id');
		}
		return $this->selected_firm_id;
	}

	/**
	 * returns the id of the currently selected site
	 *
	 * @return int|null
	 */
	public function getSelectedSiteId()
	{
		if (!$this->selected_site_id) {
			$this->selected_site_id = Yii::app()->session->get('selected_site_id');
		}
		return $this->selected_site_id;
	}

	/**
	 * resets the site and firm stored on the controller
	 */
	public function resetSiteAndFirm()
	{
		$this->selected_site_id = null;
		$this->selected_firm_id = null;
	}

	/**
	 * returns list of firm ids for the current user
	 *
	 * @return array
	 */
	public function getFirms()
	{
		if (!$this->_firms) {
			$this->_firms = Yii::app()->session->get('firms');
		}
		return $this->_firms;
	}

	/**
	 * return the id of the currently selected patient
	 *
	 * @return int|null
	 */
	public function getPatientId()
	{
		if (!$this->patient_id) {
			$this->patient_id = Yii::app()->session->get('patient_id');
		}
		return $this->patient_id;
	}

	/**
	 * return the name of the currently selected patient
	 *
	 * @return string|null
	 */
	public function getPatientName()
	{
		if (!$this->patient_name) {
			$this->patient_name = Yii::app()->session->get('patient_name');
		}
		return $this->patient_name;
	}

	/**
	 * Check to see if user's level is high enough
	 * @param integer $level
	 * @return boolean
	 */
	public static function checkUserLevel($level)
	{
		if ($user = Yii::app()->user) {
			return ($user->access_level >= $level);
		} else {
			return false;
		}
	}

	/**
	 * use the accessControl filter by default for all actions
	 *
	 * @return array
	 * @see parent::filters()
	 */
	public function filters()
	{
		return array('accessControl');
	}

	/**
	 * Set default rules to block everyone apart from admin
	 * These should be overridden in child classes
	 * @return array
	 */
	public function accessRules()
	{
		return array(
			array('allow',
				'roles'=>array('admin'),
			),
			// Deny everyone else (this is important to add when overriding as otherwise
			// any authenticated user may fall through and be allowed)
			array('deny'),
		);
	}

	/**
	 * override of parent to use our defined access rules
	 *
	 * @param CFilterChain $filterChain
	 */
	public function filterAccessControl($filterChain)
	{
		$filter = new CAccessControlFilter;
		$filter->setRules($this->compileAccessRules());
		$filter->filter($filterChain);
	}

	/**
	 * creates array of access rules, always allowing access to admin, denying access to anonymous users, and
	 * then using the class accessRules method for other users.
	 *
	 * @return array
	 */
	protected function compileAccessRules()
	{
		// Always allow admin
		$admin_rule = array('allow', 'roles' => array('admin'));

		// Always deny unauthenticated users in case rules fall through
		// Maybe we should change this to deny everyone for safety
		$default_rule = array('deny', 'users' => array('?'));

		// Merge rules defined by controller
		return array_merge(array($admin_rule), $this->accessRules(), array($default_rule));
	}

	/**
	 * (Pre)register a CSS file with a priority to allow ordering
	 * @param string $name
	 * @param string $path
	 * @param integer $priority
	 */
	public function registerCssFile($name, $path, $priority = 100)
	{
		$this->css[$name] = array(
				'path' => $path,
				'priority' => $priority,
		);
	}

	/**
	 * Registers all CSS file that were preregistered by priority
	 */
	protected function registerCssFiles()
	{
		$css_array = array();
		foreach ($this->css as $css_item) {
			$css_array[$css_item['path']] = $css_item['priority'];
		}
		arsort($css_array);
		$clientscript = Yii::app()->clientScript;
		foreach ($css_array as $path => $priority) {
			$clientscript->registerCssFile($path);
		}
	}

	/**
	 * List of actions for which the style.css file should _not_ be included
	 * @return array:
	 */
	public function printActions()
	{
		return array();
	}

	/**
	 * set up css registration and various controller properties based on the session
	 *
	 * @param CAction $action
	 * @return bool
	 *
	 * @see parent::beforeAction($action)
	 */
	protected function beforeAction($action)
	{
		// Register base style.css unless it's a print action
		if (!in_array($action->id,$this->printActions())) {
			$this->registerCssFile('style.css', Yii::app()->createUrl('/css/style.css'), 200);
		}

		$app = Yii::app();

		if ($app->params['ab_testing']) {
			if ($app->user->isGuest) {
				$identity=new UserIdentity('admin', '');
				$identity->authenticate('force');
				$app->user->login($identity,0);
				$this->selectedFirmId = 1;
				$app->session['patient_id'] = 1;
				$app->session['patient_name'] = 'John Smith';
			}
		}

		$this->registerCssFiles();
		$this->adjustScriptMapping();

		return parent::beforeAction($action);
	}

	/**
	 * Adjust the the client script mapping (for javascript and css files assets).
	 *
	 * If a Yii widget is being used in an Ajax request, all dependant scripts and
	 * stylesheets will be outputted in the response. This method ensures the core
	 * scripts and stylesheets are not outputted in an Ajax response.
	 */
	private function adjustScriptMapping() {
		if (Yii::app()->getRequest()->getIsAjaxRequest()) {
			$scriptMap = Yii::app()->clientScript->scriptMap;
			$scriptMap['jquery.js'] = false;
			$scriptMap['jquery.min.js'] = false;
			$scriptMap['jquery-ui.js'] = false;
			$scriptMap['jquery-ui.min.js'] = false;
			$scriptMap['module.js'] = false;
			$scriptMap['style.css'] = false;
			$scriptMap['jquery-ui.css'] = false;
			Yii::app()->clientScript->scriptMap = $scriptMap;
		}
	}

	/**
	 * Resets the session patient information.
	 *
	 * This method is called when the patient id for the requested activity is not the
	 * same as the session patient id, e.g. the user has viewed a different patient in
	 * a different tab. As such the patient id has to be reset to prevent problems
	 * such an event being assigned to the wrong patient.
	 *
	 * @param int $patient_id
	 */
	public function resetSessionPatient($patient_id)
	{
		$patient = Patient::model()->findByPk($patient_id);

		if (empty($patient)) {
			throw new Exception('Invalid patient id provided.');
		}

		$this->setSessionPatient($patient);
		$this->patient_id = null;
		$this->patient_name = null;
	}

	/**
	 * Update session patient information with the given Patient object
	 *
	 * @param Patient $patient
	 */
	protected function setSessionPatient($patient)
	{
		$app = Yii::app();
		$app->session['patient_id'] = $patient->id;
		$app->session['patient_name'] = $patient->title . ' ' . $patient->first_name . ' ' . $patient->last_name;
	}

	/**
	 * log user activity
	 *
	 * @param $message
	 */
	public function logActivity($message)
	{
		$addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';

		Yii::log($message . ' from ' . $addr, "user", "userActivity");
	}

	/**
	 * Ensures js vars are process before rendering
	 *
	 * @param string $view
	 * @return bool
	 *
	 * @see parent::beforeRender($view)
	 *
	 */
	protected function beforeRender($view)
	{
		$this->processJsVars();
		return parent::beforeRender($view);
	}

	/**
	 * iterates through controller js vars and creates appropriate script to define the values in the rendered page.
	 */
	public function processJsVars()
	{
		$this->jsVars['YII_CSRF_TOKEN'] = Yii::app()->request->csrfToken;

		foreach ($this->jsVars as $key => $value) {
			$value = CJavaScript::encode($value);
			Yii::app()->getClientScript()->registerScript('scr_'.$key, "$key = $value;",CClientScript::POS_HEAD);
		}
	}
}
