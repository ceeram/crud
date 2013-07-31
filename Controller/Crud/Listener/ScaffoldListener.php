<?php

App::uses('CakeEvent', 'Event');
App::uses('CrudListener', 'Crud.Controller/Crud');

/**
 * Implements beforeRender event listener that uses the build-in
 * scaffolding views in cakephp.
 *
 * Using this listener you don't have to bake your views when
 * doing rapid prototyping of your application
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
class ScaffoldListener extends CrudListener {

/**
 * List of events implemented by this class
 *
 * @return array
 */
	public function implementedEvents() {
		return array(
			'Crud.beforeRender' => 'beforeRender',
			'Crud.beforeFind' => 'beforeFind',
			'Crud.beforePaginate' => 'beforePaginate'
		);
	}

/**
 * Make sure to contain associated models
 *
 * This have no effect on clean applications where containable isn't
 * loaded, but for those who does have it loaded, we should
 * use it.
 *
 * This help applications with `$recursive -1` in their AppModel
 * and containable behavior loaded
 *
 * @param CakeEvent $event
 * @return void
 */
	public function beforeFind(CakeEvent $event) {
		if (!isset($event->subject->query['contain'])) {
			$event->subject->query['contain'] = array();
		}

		$existing = $event->subject->query['contain'];
		$associated = array_keys($this->_model()->getAssociated());

		$event->subject->query['contain'] = array_merge($existing, $associated);
	}

/**
 * Make sure to contain associated models
 *
 * This have no effect on clean applications where containable isn't
 * loaded, but for those who does have it loaded, we should
 * use it.
 *
 * This help applications with `$recursive -1` in their AppModel
 * and containable behavior loaded
 *
 * @param CakeEvent $event
 * @return void
 */
	public function beforePaginate(CakeEvent $event) {
		$Paginator = $this->_controller()->Paginator;

		if (!isset($Paginator->settings['contain'])) {
			$Paginator->settings['contain'] = array();
		}

		$existing = $Paginator->settings['contain'];
		$associated = array_keys($this->_model()->getAssociated());

		$Paginator->settings['contain'] = array_merge($existing, $associated);
	}

/**
 * Do all the magic needed for using the
 * cakephp scaffold views
 *
 * @param CakeEvent
 * @return void
 */
	public function beforeRender(CakeEvent $event) {
		$subject = $event->subject;
		$model = $this->_model();
		$request = $this->_request();
		$controller = $this->_controller();
		$action = $request->action;

		$scaffoldTitle = Inflector::humanize(Inflector::underscore($controller->viewPath));
		$title = __d('cake', 'Scaffold :: ') . Inflector::humanize($action) . ' :: ' . $scaffoldTitle;

		$modelClass = $controller->modelClass;
		$primaryKey = $model->primaryKey;
		$displayField = $model->displayField;
		$singularVar = Inflector::variable($modelClass);
		$pluralVar = Inflector::variable($controller->name);
		$singularHumanName = Inflector::humanize(Inflector::underscore($modelClass));
		$pluralHumanName = Inflector::humanize(Inflector::underscore($controller->name));
		$modelSchema = $model->schema();
		$associations = $this->_associations($model);
		$scaffoldFields = $this->_scaffoldFields($model);
		$scaffoldFieldExclude = $this->_scaffoldFieldExclude($scaffoldFields);
		$scaffoldFilters = $this->_scaffoldFilters($request);
		$sidebarActions = $this->_sidebarActions();

		$controller->set(compact(
			'modelClass', 'primaryKey', 'displayField', 'singularVar', 'pluralVar',
			'singularHumanName', 'pluralHumanName', 'scaffoldFields', 'associations',
			'scaffoldFilters', 'action', 'scaffoldFieldExclude', 'modelSchema', 'sidebarActions'
		));

		$controller->set('title_for_layout', $title);

		if ($controller->viewClass) {
			$controller->viewClass = 'Scaffold';
		}

		$controller->helpers = (array)$controller->helpers;
		$controller->helpers[] = 'Time';

		App::build(array(
			'View' => array(
				APP . 'View' . DS,
				APP . 'Plugin' . DS . 'Crud' . DS . 'View' . DS
			)
		));
	}

/**
 * Returns fields to be displayed on scaffolded view
 *
 * @param Model $model
 * @return array List of fields
 */
	protected function _scaffoldFields(Model $model) {
		$modelSchema = $model->schema();
		$scaffoldFields = array_keys($modelSchema);
		$_scaffoldFields = $this->_action()->config('scaffoldFields');
		if (!empty($_scaffoldFields)) {
			$_scaffoldFields = (array)$_scaffoldFields;
			$scaffoldFields = array_intersect($scaffoldFields, array_combine(
				$_scaffoldFields,
				$_scaffoldFields
			));
		}

		return $scaffoldFields;
	}

/**
 * Returns fields to be excluded on scaffolded view
 *
 * @param array $scaffoldFields
 * @return array List of fields
 */
	protected function _scaffoldFieldExclude($scaffoldFields) {
		$className = $this->_action()->config('className');
		$scaffoldFieldExclude = $this->_action()->config('scaffoldFieldExclude');

		if (empty($scaffoldFieldExclude)) {
			if ($className == 'Crud.Add' || $className == 'Crud.Edit') {
				$scaffoldFieldExclude = array('created', 'modified', 'updated');
				foreach ($scaffoldFields as $_field) {
					if (substr($_field, -6) === '_count') {
						$scaffoldFieldExclude[] = $_field;
					}
				}
			} else {
				$scaffoldFieldExclude = array();
			}
		}
		return $scaffoldFieldExclude;
	}

/**
 * Returns fields to be filtered upon in scaffolded view
 *
 * @param CakeRequest $request
 * @return array Array of fields to show filters for
 */
	protected function _scaffoldFilters(CakeRequest $request) {
		$scaffoldFilters = array();
		$_scaffoldFilters = $this->_action()->config('scope');
		if (!empty($_scaffoldFilters)) {
			$scaffoldFilters = (array)$_scaffoldFilters;
			foreach ($scaffoldFilters as $_field => $scaffoldField) {
				$scaffoldFilters[$_field] = Hash::merge(array(
					'type' => 'value',
					'form' => array(
						'label' => false,
						'placeholder' => $_field,
					),
				), $scaffoldField);
				$scaffoldFilters[$_field] = $scaffoldFilters[$_field]['form'];

				if (!isset($scaffoldFilters[$_field]['value'])) {
					$scaffoldFilters[$_field]['value'] = $request->query($_field);
				}
			}
		}
		return $scaffoldFilters;
	}


/**
 * Returns links to be shown in actions section of scaffolded view
 *
 * @return array Array of link
 */
	protected function _sidebarActions() {
		$sidebarActions = $this->_action()->config('sidebarActions');
		if ($sidebarActions === null) {
			return true;
		}

		foreach ($sidebarActions as $i => $sidebarAction) {
			$sidebarActions[$i] = array_merge(array(
				'title' => null,
				'url' => null,
				'options' => array(),
				'confirmMessage' => false,
				'type' => 'link',
			), $sidebarActions[$i]);

			$sidebarActions[$i]['type'] = strtolower($sidebarActions[$i]['type']);
			if (!in_array($sidebarActions[$i]['type'], array('link', 'post'))) {
				$sidebarActions[$i]['type'] = 'link';
			}
		}
		return $sidebarActions;
	}

/**
 * Returns associations for controllers models.
 *
 * @param Model $model
 * @return array Associations for model
 */
	protected function _associations(Model $model) {
		$associations = array();

		$associated = $model->getAssociated();
		foreach ($associated as $assocKey => $type) {
			if (!isset($associations[$type])) {
				$associations[$type] = array();
			}

			$assocDataAll = $model->$type;

			$assocData = $assocDataAll[$assocKey];
			$associatedModel = $model->{$assocKey};

			$associations[$type][$assocKey]['primaryKey'] = $associatedModel->primaryKey;
			$associations[$type][$assocKey]['displayField'] = $associatedModel->displayField;
			$associations[$type][$assocKey]['foreignKey'] = $assocData['foreignKey'];

			list($plugin, $modelClass) = pluginSplit($assocData['className']);

			if ($plugin) {
				$plugin = Inflector::underscore($plugin);
			}

			$associations[$type][$assocKey]['plugin'] = $plugin;
			$associations[$type][$assocKey]['controller'] = Inflector::pluralize(Inflector::underscore($modelClass));

			if ($type === 'hasAndBelongsToMany') {
				$associations[$type][$assocKey]['with'] = $assocData['with'];
			}
		}

		return $associations;
	}

}
