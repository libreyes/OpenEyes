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

Yii::import('application.controllers.*');

class PatientController extends BaseController
{
	public $layout = '//layouts/main';
	public $renderPatientPanel = true;
	public $patient;
	public $firm;
	public $editable;
	public $editing;
	public $event;
	public $event_type;
	public $title;
	public $event_type_id;
	public $episode;
	public $current_episode;
	public $event_tabs = array();
	public $event_actions = array();
	public $episodes = array();
	public $page_size = 20;

	public function accessRules()
	{
		return array(
			array('allow',
				'actions' => array('search', 'results', 'view'),
				'users' => array('@')
			),
			array('allow',
				'actions' => array('episode', 'episodes', 'hideepisode', 'showepisode'),
				'roles' => array('OprnViewClinical'),
			),
			array('allow',
				'actions' => array('verifyAddNewEpisode', 'addNewEpisode'),
				'roles' => array('OprnCreateEpisode'),
			),
			array('allow',
				'actions' => array('updateepisode'),	// checked in action
				'users' => array('@'),
			),
			array('allow',
				'actions' => array('possiblecontacts', 'associatecontact', 'unassociatecontact', 'getContactLocation', 'institutionSites', 'validateSaveContact', 'addContact', 'validateEditContact', 'editContact', 'sendSiteMessage'),
				'roles' => array('OprnEditContact'),
			),
			array('allow',
				'actions' => array('addAllergy', 'removeAllergy'),
				'roles' => array('OprnEditAllergy'),
			),
			array('allow',
				'actions' => array('adddiagnosis', 'validateAddDiagnosis', 'removediagnosis'),
				'roles' => array('OprnEditOtherOphDiagnosis'),
			),
			array('allow',
				'actions' => array('editOphInfo'),
				'roles' => array('OprnEditOphInfo'),
			),
			array('allow',
				'actions' => array('addPreviousOperation', 'getPreviousOperation', 'removePreviousOperation'),
				'roles' => array('OprnEditPreviousOperation'),
			),
			array('allow',
				'actions' => array('drugList', 'drugDefaults', 'getDrugRouteOptions', 'validateAddMedication', 'addMedication', 'getMedication', 'removeMedication','validateMedication'),
				'roles' => array('OprnEditMedication'),
			),
			array('allow',
				'actions' => array('addFamilyHistory', 'removeFamilyHistory'),
				'roles' => array('OprnEditFamilyHistory')
			),
			array('allow',
				'actions' => array('validatePatientDetails', 'updatePatientDetails', 'create', 'validatePatientContactDetails', 'updatePatientContactDetails', 'GPSearch', 'getGPDetails', 'practiceSearch', 'getPracticeDetails', 'updatePatientGPAndPracticeDetails', 'getAge', 'getYearOfBirth'),
				'roles' => array('OprnEditPatientDetails')
			),
			array('allow',
				'actions' => array('delete'),
				'roles' => array('admin')
			),
		);
	}

	protected function beforeAction($action)
	{
		parent::storeData();

		$this->firm = Firm::model()->findByPk($this->selectedFirmId);

		if (!isset($this->firm)) {
			// No firm selected, reject
			throw new CHttpException(403, 'You are not authorised to view this page without selecting a firm.');
		}

		return parent::beforeAction($action);
	}

	/**
	 * Displays a particular model.
	 * @param integer $id the ID of the model to be displayed
	 */
	public function actionView($id)
	{
		Yii::app()->assetManager->registerScriptFile('js/patientSummary.js');

		$this->patient = $this->loadModel($id);

		$tabId = !empty($_GET['tabId']) ? $_GET['tabId'] : 0;
		$eventId = !empty($_GET['eventId']) ? $_GET['eventId'] : 0;

		$episodes = $this->patient->episodes;
		// TODO: verify if ordered_episodes complete supercedes need for unordered $episodes
		$ordered_episodes = $this->patient->getOrderedEpisodes();

		$legacyepisodes = $this->patient->legacyepisodes;
		// NOTE that this is not being used in the render
		$supportserviceepisodes = $this->patient->supportserviceepisodes;

		Audit::add('patient summary','view');

		$this->logActivity('viewed patient');

		$episodes_open = 0;
		$episodes_closed = 0;

		foreach ($episodes as $episode) {
			if ($episode->end_date === null) {
				$episodes_open++;
			} else {
				$episodes_closed++;
			}
		}

		$this->jsVars['currentContacts'] = $this->patient->currentContactIDS();

		$this->breadcrumbs=array(
			$this->patient->first_name.' '.$this->patient->last_name. '('.$this->patient->hos_num.')',
		);

		$this->render('view', array(
			'tab' => $tabId,
			'event' => $eventId,
			'episodes' => $episodes,
			'ordered_episodes' => $ordered_episodes,
			'legacyepisodes' => $legacyepisodes,
			'episodes_open' => $episodes_open,
			'episodes_closed' => $episodes_closed,
			'firm' => Firm::model()->findByPk(Yii::app()->session['selected_firm_id']),
			'supportserviceepisodes' => $supportserviceepisodes,
		));
	}

	public function sanitiseSearchParams($params)
	{
		$ok = false;

		foreach (PatientSearchField::model()->findAll() as $field) {
			if (@$params[$field->name]) {
				$ok = true;
				break;
			}
		}

		if (!$ok) return false;

		foreach ($params as $key => $value) {
			$params[$key] = trim($value);

			if ($key == 'hos_num' && $value) {
				$params[$key] = sprintf('%07s',$value);
			}
		}

		return $params;
	}

	public function actionSearch()
	{
		$search_terms = $this->sanitiseSearchParams($_GET);

		if (!YII_DEBUG && !$search_terms['hos_num'] && !$search_terms['nhs_num'] && !($search_terms['first_name'] && $search_terms['last_name'])) {
			Yii::app()->user->setFlash('warning.invalid-search', 'Please enter a valid search.');
			return $this->redirect(Yii::app()->homeUrl);
		}

		$patients = Patient::model()->search($search_terms);

		if (count($patients) == 0) {
			Audit::add('search','search-results',implode(',',$search_terms) ." : No results");

			$message = 'Sorry, no results ';
			if ($search_terms['hos_num']) {
				$message .= 'for '.Patient::model()->getAttributeLabel('hos_num').' <strong>"'.$search_terms['hos_num'].'"</strong>';
			} elseif ($search_terms['nhs_num']) {
				$message .= 'for '.Patient::model()->getAttributeLabel('nhs_num').' <strong>"'.$search_terms['nhs_num'].'"</strong>';
			} elseif ($search_terms['first_name'] && $search_terms['last_name']) {
				$message .= 'for Patient Name <strong>"'.$search_terms['first_name'] . ' ' . $search_terms['last_name'].'"</strong>';
			} else {
				$message .= 'found for your search.';
			}
			Yii::app()->user->setFlash('warning.no-results', $message);

			return $this->redirect(Yii::app()->homeUrl);
		}

		if (count($patients) == 1) {
			return $this->redirect(array('/patient/view/'.$patients[0]->id));
		}

		$this->redirect(array(Yii::app()->createUrl('/patient/results', $search_terms)));
	}

	public function actionResults()
	{
		if (!$search_terms = $this->sanitiseSearchParams($_GET)) {
			$data = array();
			$total_items = 0;
			$page = 1;
			$pages = 1;
			$message = 'Please enter at least one search criteria.';
		} else {
			$data = Patient::model()->search(array_merge($search_terms,array(
				'items_per_page' => $this->page_size,
			)));

			$total_items = $data['total_items'];
			$data = $data['data'];

			if ($total_items == 1) {
				return $this->redirect(array('/patient/view/'.$data[0]->id));
			}
			$page = @$_GET['page'];
			$pages = ceil($total_items / $this->page_size);
			if ($page <1) $page = 1;
			if ($page > $pages) $page = $pages;
		}

		$this->renderPatientPanel = false;

		$this->render('results', array(
			'data' => $data,
			'page' => $page,
			'pages' => $pages,
			'items_per_page' => $this->page_size,
			'total_items' => $total_items,
			'search_terms' => $search_terms,
			'sort_by' => (integer) @$_GET['sort_by'],
			'sort_dir' => (integer) @$_GET['sort_dir'],
			'message' => @$message,
		));
	}

	public function getPatientSearchUrl($sort_by)
	{
		$search_terms = $this->sanitiseSearchParams($_GET);
		if (@$search_terms['sort_by'] == $sort_by) {
			$search_terms['sort_dir'] = @$search_terms['sort_dir'] == 'asc' ? 'desc' : 'asc';
		} else {
			$search_terms['sort_by'] = $sort_by;
			$search_terms['sort_dir'] = 'asc';
		}

		unset($search_terms['Patient_page']);

		return Yii::app()->createUrl('/patient/results', $search_terms);
	}

	public function actionEpisodes()
	{
		$this->layout = '//layouts/events_and_episodes';
		$this->patient = $this->loadModel($_GET['id']);

		$episodes = $this->patient->episodes;
		$legacyepisodes = $this->patient->legacyepisodes;
		$site = Site::model()->findByPk(Yii::app()->session['selected_site_id']);

		if (!$current_episode = $this->patient->getEpisodeForCurrentSubspecialty()) {
			$current_episode = empty($episodes) ? false : $episodes[0];
			if (!empty($legacyepisodes)) {
				$criteria = new CDbCriteria;
				$criteria->compare('episode_id',$legacyepisodes[0]->id);
				$criteria->order = 'created_date desc';

				foreach (Event::model()->findAll($criteria) as $event) {
					if (in_array($event->eventType->class_name,Yii::app()->modules) && (!$event->eventType->disabled)) {
						$this->redirect(array($event->eventType->class_name.'/default/view/'.$event->id));
						Yii::app()->end();
					}
				}
			}
		} elseif ($current_episode->end_date == null) {
			$criteria = new CDbCriteria;
			$criteria->compare('episode_id',$current_episode->id);
			$criteria->order = 'created_date desc';

			if ($event = Event::model()->find($criteria)) {
				$this->redirect(array($event->eventType->class_name.'/default/view/'.$event->id));
				Yii::app()->end();
			}
		} else {
			$current_episode = null;
		}

		$this->current_episode = $current_episode;
		$this->title = 'Episode summary';

		$this->render('episodes', array(
			'title' => empty($episodes) ? '' : 'Episode summary',
			'episodes' => $episodes,
			'site' => $site,
		));
	}

	public function actionEpisode($id)
	{
		if (!$this->episode = Episode::model()->findByPk($id)) {
			throw new SystemException('Episode not found: '.$id);
		}

		$this->layout = '//layouts/events_and_episodes';
		$this->patient = $this->episode->patient;

		$episodes = $this->patient->episodes;

		$site = Site::model()->findByPk(Yii::app()->session['selected_site_id']);

		$this->title = 'Episode summary';
		$this->event_tabs = array(
				array(
						'label' => 'View',
						'active' => true,
				)
		);

		if ($this->checkAccess('OprnEditEpisode', $this->firm, $this->episode) && $this->episode->firm) {
			$this->event_tabs[] = array(
					'label' => 'Edit',
					'href' => Yii::app()->createUrl('/patient/updateepisode/'.$this->episode->id),
			);
		}
		$this->current_episode = $this->episode;
		$status = Yii::app()->session['episode_hide_status'];
		$status[$id] = true;
		Yii::app()->session['episode_hide_status'] = $status;

		$this->render('episodes', array(
			'title' => empty($episodes) ? '' : 'Episode summary',
			'episodes' => $episodes,
			'site' => $site,
		));
	}

	public function actionUpdateepisode($id)
	{
		if (!$this->episode = Episode::model()->findByPk($id)) {
			throw new SystemException('Episode not found: '.$id);
		}

		if (!$this->checkAccess('OprnEditEpisode', $this->firm, $this->episode) || isset($_POST['episode_cancel'])) {
			$this->redirect(array('patient/episode/'.$this->episode->id));
			return;
		}

		if (!empty($_POST)) {
			if ((@$_POST['eye_id'] && !@$_POST['DiagnosisSelection']['disorder_id'])) {
				$error = "Please select a disorder for the principal diagnosis";
			} elseif (!@$_POST['eye_id'] && @$_POST['DiagnosisSelection']['disorder_id']) {
				$error = "Please select an eye for the principal diagnosis";
			} else {
				if (@$_POST['eye_id'] && @$_POST['DiagnosisSelection']['disorder_id']) {
					if ($_POST['eye_id'] != $this->episode->eye_id || $_POST['DiagnosisSelection']['disorder_id'] != $this->episode->disorder_id) {
						$this->episode->setPrincipalDiagnosis($_POST['DiagnosisSelection']['disorder_id'],$_POST['eye_id']);
					}
				}

				if ($_POST['episode_status_id'] != $this->episode->episode_status_id) {
					$this->episode->episode_status_id = $_POST['episode_status_id'];

					if (!$this->episode->save()) {
						throw new Exception('Unable to update status for episode '.$this->episode->id.' '.print_r($this->episode->getErrors(),true));
					}
				}

				$this->redirect(array('patient/episode/'.$this->episode->id));
			}
		}

		$this->patient = $this->episode->patient;

		$episodes = $this->patient->episodes;
		// TODO: verify if ordered_episodes complete supercedes need for unordered $episodes
		$ordered_episodes = $this->patient->getOrderedEpisodes();
		$legacyepisodes = $this->patient->legacyepisodes;
		$supportserviceepisodes = $this->patient->supportserviceepisodes;

		$site = Site::model()->findByPk(Yii::app()->session['selected_site_id']);

		$this->title = 'Episode summary';
		$this->event_tabs = array(
				array(
						'label' => 'View',
						'href' => Yii::app()->createUrl('/patient/episode/'.$this->episode->id),
				),
				array(
						'label' => 'Edit',
						'active' => true,
				),
		);

		$status = Yii::app()->session['episode_hide_status'];
		$status[$id] = true;
		Yii::app()->session['episode_hide_status'] = $status;

		$this->editing = true;

		$this->render('episodes', array(
			'title' => empty($episodes) ? '' : 'Episode summary',
			'episodes' => $episodes,
			'ordered_episodes' => $ordered_episodes,
			'legacyepisodes' => $legacyepisodes,
			'supportserviceepisodes' => $supportserviceepisodes,
			'eventTypes' => EventType::model()->getEventTypeModules(),
			'site' => $site,
			'current_episode' => $this->episode,
			'error' => @$error,
		));
	}

	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer $id the ID of the model to be loaded
	 */
	public function loadModel($id)
	{
		$model = Patient::model()->findByPk((int) $id);
		if ($model === null)
			throw new CHttpException(404, 'The requested page does not exist.');
		return $model;
	}

	/**
	 * Performs the AJAX validation.
	 * @param CModel $model the model to be validated
	 */
	protected function performAjaxValidation($model)
	{
		if (isset($_POST['ajax']) && $_POST['ajax'] === 'patient-form') {
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}

	protected function getEventTypeGrouping()
	{
		return array(
			'Examination' => array('visual fields', 'examination', 'question', 'outcome'),
			'Treatments' => array('oct', 'laser', 'operation'),
			'Correspondence' => array('letterin', 'letterout'),
			'Consent Forms' => array(''),
		);
	}

	/**
	 * Perform a search on a model and return the results
	 * (separate function for unit testing)
	 *
	 * @param array $data form data of search terms
	 * @return CDataProvider
	 */
	public function getSearch($data)
	{
		$model = new Patient;
		$model->attributes = $data;
		return $model->search();
	}

	public function getTemplateName($action, $eventTypeId)
	{
		$template = 'eventTypeTemplates' . DIRECTORY_SEPARATOR . $action . DIRECTORY_SEPARATOR . $eventTypeId;

		if (!file_exists(Yii::app()->basePath . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'clinical' . DIRECTORY_SEPARATOR . $template . '.php')) {
			$template = $action;
		}

		return $template;
	}

	/**
	 * Get all the elements for a the current module's event type
	 *
	 * @param $event_type_id
	 * @return array
	 */
	public function getDefaultElements($action, $event_type_id=false, $event=false)
	{
		$etc = new BaseEventTypeController(1);
		$etc->event = $event;
		return $etc->getDefaultElements($action, $event_type_id);
	}

	/**
	 * Get the optional elements for the current module's event type
	 * This will be overriden by the module
	 *
	 * @param $event_type_id
	 * @return array
	 */
	public function getOptionalElements($action, $event=false)
	{
		return array();
	}

	public function actionPossiblecontacts()
	{
		$term = strtolower(trim($_GET['term'])).'%';

		switch (strtolower(@$_GET['filter'])) {
			case 'staff':
				$contacts = User::model()->findAsContacts($term);
				break;
			case 'nonspecialty':
				if (!$specialty = Specialty::model()->find('code=?',array(Yii::app()->params['institution_specialty']))) {
					throw new Exception("Unable to find specialty: ".Yii::app()->params['institution_specialty']);
				}
				$contacts = Contact::model()->findByLabel($term, $specialty->default_title, true, 'person');
				break;
			default:
				$contacts = Contact::model()->findByLabel($term, @$_GET['filter'], false, 'person');
		}

		echo CJavaScript::jsonEncode($contacts);
	}

	public function actionAssociatecontact()
	{
		if (!$patient = Patient::model()->findByPk(@$_GET['patient_id'])) {
			throw new Exception('Patient not found: '.@$_GET['patient_id']);
		}

		if (@$_GET['contact_location_id']) {
			if (!$location = ContactLocation::model()->findByPk(@$_GET['contact_location_id'])) {
				throw new Exception("Can't find contact location: ".@$_GET['contact_location_id']);
			}
			$contact = $location->contact;
		} else {
			if (!$contact = Contact::model()->findByPk(@$_GET['contact_id'])) {
				throw new Exception("Can't find contact: ".@$_GET['contact_id']);
			}
		}

		// Don't assign the patient's own GP
		if ($contact->label == 'General Practitioner') {
			if ($gp = Gp::model()->find('contact_id=?',array($contact->id))) {
				if ($gp->id == $patient->gp_id) {
					return;
				}
			}
		}

		if (isset($location)) {
			if (!$pca = PatientContactAssignment::model()->find('patient_id=? and location_id=?',array($patient->id,$location->id))) {
				$pca = new PatientContactAssignment;
				$pca->patient_id = $patient->id;
				$pca->location_id = $location->id;

				if (!$pca->save()) {
					throw new Exception("Unable to save patient contact assignment: ".print_r($pca->getErrors(),true));
				}
			}
		} else {
			if (!$pca = PatientContactAssignment::model()->find('patient_id=? and contact_id=?',array($patient->id,$contact->id))) {
				$pca = new PatientContactAssignment;
				$pca->patient_id = $patient->id;
				$pca->contact_id = $contact->id;

				if (!$pca->save()) {
					throw new Exception("Unable to save patient contact assignment: ".print_r($pca->getErrors(),true));
				}
			}
		}

		$this->renderPartial('_patient_contact_row',array('pca'=>$pca));
	}

	public function actionUnassociatecontact()
	{
		if (!$pca = PatientContactAssignment::model()->findByPk(@$_GET['pca_id'])) {
			throw new Exception("Patient contact assignment not found: ".@$_GET['pca_id']);
		}

		if (!$pca->delete()) {
			echo "0";
		} else {
			$pca->patient->audit('patient','unassociate-contact',$pca->getAuditAttributes());
			echo "1";
		}
	}

	/**
	 * Add patient/allergy assignment
	 *
	 * @throws Exception
	 */
	public function actionAddAllergy()
	{
		if (!empty($_POST)) {
			if (!isset($_POST['patient_id']) || !$patient_id = $_POST['patient_id']) {
				throw new Exception('Patient ID required');
			}
			if (!$patient = Patient::model()->findByPk($patient_id)) {
				throw new Exception('Patient not found: '.$patient_id);
			}
			if (@$_POST['no_allergies']) {
				$patient->setNoAllergies();
			}
			else	{
				if (!isset($_POST['allergy_id']) || !$allergy_id = $_POST['allergy_id']) {
					throw new Exception('Allergy ID required');
				}
				if (!$allergy = Allergy::model()->findByPk($allergy_id)) {
					throw new Exception('Allergy not found: '.$allergy_id);
				}
				$patient->addAllergy($allergy_id);
			}

		}

		$this->redirect(array('patient/view/'.$patient->id));
	}

	/**
	 * Remove patient/allergy assignment
	 *
	 * @throws Exception
	 */
	public function actionRemoveAllergy()
	{
		if (!isset($_GET['patient_id']) || !$patient_id = $_GET['patient_id']) {
			throw new Exception('Patient ID required');
		}
		if (!$patient = Patient::model()->findByPk($patient_id)) {
			throw new Exception('Patient not found: '.$patient_id);
		}
		if (!isset($_GET['allergy_id']) || !$allergy_id = $_GET['allergy_id']) {
			throw new Exception('Allergy ID required');
		}
		if (!$allergy = Allergy::model()->findByPk($allergy_id)) {
			throw new Exception('Allergy not found: '.$allergy_id);
		}
		$patient->removeAllergy($allergy_id);

		echo 'success';
	}

	/**
	 * List of allergies
	 */
	public function allergyList()
	{
		$allergy_ids = array();
		foreach ($this->patient->allergies as $allergy) {
			$allergy_ids[] = $allergy->id;
		}
		$criteria = new CDbCriteria;
		!empty($allergy_ids) && $criteria->addNotInCondition('id',$allergy_ids);
		$criteria->order = 'name asc';
		return Allergy::model()->findAll($criteria);
	}

	public function actionHideepisode()
	{
		$status = Yii::app()->session['episode_hide_status'];

		if (isset($_GET['episode_id'])) {
			$status[$_GET['episode_id']] = false;
		}

		Yii::app()->session['episode_hide_status'] = $status;
	}

	public function actionShowepisode()
	{
		$status = Yii::app()->session['episode_hide_status'];

		if (isset($_GET['episode_id'])) {
			$status[$_GET['episode_id']] = true;
		}

		Yii::app()->session['episode_hide_status'] = $status;
	}

	private function processDiagnosisDate()
	{
		$date = $_POST['fuzzy_year'];

		if ($_POST['fuzzy_month']) {
			$date .= '-'.str_pad($_POST['fuzzy_month'],2,'0',STR_PAD_LEFT);
		} else {
			$date .= '-00';
		}

		if ($_POST['fuzzy_day']) {
			$date .= '-'.str_pad($_POST['fuzzy_day'],2,'0',STR_PAD_LEFT);
		} else {
			$date .= '-00';
		}

		return $date;
	}

	public function actionAdddiagnosis()
	{
		if (isset($_POST['DiagnosisSelection']['ophthalmic_disorder_id'])) {
			$disorder = Disorder::model()->findByPk(@$_POST['DiagnosisSelection']['ophthalmic_disorder_id']);
		} else {
			$disorder = Disorder::model()->findByPk(@$_POST['DiagnosisSelection']['systemic_disorder_id']);
		}

		if (!$disorder) {
			throw new Exception('Unable to find disorder: '.@$_POST['DiagnosisSelection']['ophthalmic_disorder_id'].' / '.@$_POST['DiagnosisSelection']['systemic_disorder_id']);
		}

		if (!$patient = Patient::model()->findByPk(@$_POST['patient_id'])) {
			throw new Exception('Unable to find patient: '.@$_POST['patient_id']);
		}

		$date = $this->processDiagnosisDate();

		if (!$_POST['diagnosis_eye']) {
			if (!SecondaryDiagnosis::model()->find('patient_id=? and disorder_id=? and date=?',array($patient->id,$disorder->id,$date))) {
				$patient->addDiagnosis($disorder->id,null,$date);
			}
		} elseif (!SecondaryDiagnosis::model()->find('patient_id=? and disorder_id=? and eye_id=? and date=?',array($patient->id,$disorder->id,$_POST['diagnosis_eye'],$date))) {
			$patient->addDiagnosis($disorder->id, $_POST['diagnosis_eye'], $date);
		}

		$this->redirect(array('patient/view/'.$patient->id));
	}

	public function actionValidateAddDiagnosis()
	{
		$errors = array();

		if (!$patient = Patient::model()->findByPk(@$_POST['patient_id'])) {
			throw new Exception("Patient not found: ".@$_POST['patient_id']);
		}

		if (isset($_POST['DiagnosisSelection']['ophthalmic_disorder_id'])) {
			$disorder_id = $_POST['DiagnosisSelection']['ophthalmic_disorder_id'];
		} elseif (isset($_POST['DiagnosisSelection']['systemic_disorder_id'])) {
			$disorder_id = $_POST['DiagnosisSelection']['systemic_disorder_id'];
		}

		$sd = new SecondaryDiagnosis;
		$sd->patient_id = $patient->id;
		$sd->date = @$_POST['fuzzy_year'].'-'.str_pad(@$_POST['fuzzy_month'],2,'0',STR_PAD_LEFT).'-'.str_pad(@$_POST['fuzzy_day'],2,'0',STR_PAD_LEFT);
		$sd->disorder_id = @$disorder_id;
		$sd->eye_id = @$_POST['diagnosis_eye'];

		$errors = array();

		if (!$sd->validate()) {
			foreach ($sd->getErrors() as $field => $_errors) {
				$errors[$field] = $_errors[0];
			}
		}

		// Check the diagnosis isn't currently set at the episode level for this patient
		foreach ($patient->episodes as $episode) {
			if ($episode->disorder_id == $sd->disorder_id && ($episode->eye_id == $sd->eye_id || $episode->eye_id == 3 || $sd->eye_id == 3)) {
				$errors['disorder_id'] = "The disorder is already set at the episode level for this patient";
			}
		}

		// Check that the date isn't in the future
		if (@$_POST['fuzzy_year'] == date('Y')) {
			if (@$_POST['fuzzy_month'] > date('n')) {
				$errors['date'] = "The date cannot be in the future.";
			} elseif (@$_POST['fuzzy_month'] == date('n')) {
				if (@$_POST['fuzzy_day'] > date('j')) {
					$errors['date'] = "The date cannot be in the future.";
				}
			}
		}

		// Check that the date is valid
		$v = new OEFuzzyDateValidator;
		$v->validateAttribute($sd,'date');

		echo json_encode($errors);
	}

	public function actionRemovediagnosis()
	{
		if (!$patient = Patient::model()->findByPk(@$_GET['patient_id'])) {
			throw new Exception('Unable to find patient: '.@$_GET['patient_id']);
		}

		$patient->removeDiagnosis(@$_GET['diagnosis_id']);

		echo "success";
	}

	public function actionEditOphInfo()
	{
		$cvi_status = PatientOphInfoCviStatus::model()->findByPk(@$_POST['PatientOphInfo']['cvi_status_id']);

		if (!$cvi_status) {
			throw new Exception('invalid cvi status selection:' . @$_POST['PatientOphInfo']['cvi_status_id']);
		}

		if (!$patient = Patient::model()->findByPk(@$_POST['patient_id'])) {
			throw new Exception('Unable to find patient: '.@$_POST['patient_id']);
		}

		$cvi_status_date = $this->processDiagnosisDate();

		$result = $patient->editOphInfo($cvi_status, $cvi_status_date);

		echo json_encode($result);
	}

	public function reportDiagnoses($params)
	{
		$patients = array();

		$where = '';
		$select = "p.id as patient_id, p.hos_num, c.first_name, c.last_name";

		if (empty($params['selected_diagnoses'])) {
			return array('patients'=>array());
		}

		$command = Yii::app()->db->createCommand()
			->from("patient p")
			->join("contact c","p.contact_id = c.id");

		if (!empty($params['principal'])) {
			foreach ($params['principal'] as $i => $disorder_id) {
				$command->join("episode e$i","e$i.patient_id = p.id");
				$command->join("eye eye_e_$i","eye_e_$i.id = e$i.eye_id");
				$command->join("disorder disorder_e_$i","disorder_e_$i.id = e$i.disorder_id");
				if ($i>0) $where .= ' and ';
				$where .= "e$i.disorder_id = $disorder_id ";
				$select .= ", e$i.last_modified_date as episode{$i}_date, eye_e_$i.name as episode{$i}_eye, disorder_e_$i.term as episode{$i}_disorder";
			}
		}

		foreach ($params['selected_diagnoses'] as $i => $disorder_id) {
			if (empty($params['principal']) || !in_array($disorder_id,$params['principal'])) {
				$command->join("secondary_diagnosis sd$i","sd$i.patient_id = p.id");
				$command->join("eye eye_sd_$i","eye_sd_$i.id = sd$i.eye_id");
				$command->join("disorder disorder_sd_$i","disorder_sd_$i.id = sd$i.disorder_id");
				if ($where) $where .= ' and ';
				$where .= "sd$i.disorder_id = $disorder_id ";
				$select .= ", sd$i.date as sd{$i}_date, sd$i.eye_id as sd{$i}_eye_id, eye_sd_$i.name as sd{$i}_eye, disorder_sd_$i.term as sd{$i}_disorder";
			}
		}

		$results = array();

		foreach ($command->select($select)->where($where)->queryAll() as $row) {
			$date = $this->reportEarliestDate($row);

			while (isset($results[$date['timestamp']])) {
				$date['timestamp']++;
			}

			$results['patients'][$date['timestamp']] = array(
				'patient_id' => $row['patient_id'],
				'hos_num' => $row['hos_num'],
				'first_name' => $row['first_name'],
				'last_name' => $row['last_name'],
				'date' => $date['date'],
				'diagnoses' => array(),
			);

			foreach ($row as $key => $value) {
				if (preg_match('/^episode([0-9]+)_eye$/',$key,$m)) {
					$results['patients'][$date['timestamp']]['diagnoses'][] = array(
						'eye' => $value,
						'diagnosis' => $row['episode'.$m[1].'_disorder'],
					);
				}
				if (preg_match('/^sd([0-9]+)_eye$/',$key,$m)) {
					$results['patients'][$date['timestamp']]['diagnoses'][] = array(
						'eye' => $value,
						'diagnosis' => $row['sd'.$m[1].'_disorder'],
					);
				}
			}
		}

		ksort($results['patients'], SORT_NUMERIC);

		return $results;
	}

	public function reportEarliestDate($row)
	{
		$dates = array();

		foreach ($row as $key => $value) {
			$value = substr($value,0,10);

			if (preg_match('/_date$/',$key) && !in_array($value,$dates)) {
				$dates[] = $value;
			}
		}

		sort($dates, SORT_STRING);

		if (preg_match('/-00-00$/',$dates[0])) {
			return array(
				'date' => substr($dates[0],0,4),
				'timestamp' => strtotime(substr($dates[0],0,4).'-01-01'),
			);
		} elseif (preg_match('/-00$/',$dates[0])) {
			$date = Helper::getMonthText(substr($dates[0],5,2)).' '.substr($dates[0],0,4);
			return array(
				'date' => $date,
				'timestamp' => strtotime($date),
			);
		}

		return array(
			'date' => date('j M Y',strtotime($dates[0])),
			'timestamp' => strtotime($dates[0]),
		);
	}

	public function actionAddPreviousOperation()
	{
		if (!$patient = Patient::model()->findByPk(@$_POST['patient_id'])) {
			throw new Exception("Patient not found:".@$_POST['patient_id']);
		}

		if (!isset($_POST['previous_operation'])) {
			throw new Exception("Missing previous operation text");
		}

		if (@$_POST['edit_operation_id']) {
			if (!$po = PreviousOperation::model()->findByPk(@$_POST['edit_operation_id'])) {
				$po = new PreviousOperation;
			}
		} else {
			$po = new PreviousOperation;
		}

		$po->patient_id = $patient->id;
		$po->side_id = @$_POST['previous_operation_side'] ? @$_POST['previous_operation_side'] : null;
		$po->operation = @$_POST['previous_operation'];
		$po->date = str_pad(@$_POST['fuzzy_year'],4,'0',STR_PAD_LEFT).'-'.str_pad(@$_POST['fuzzy_month'],2,'0',STR_PAD_LEFT).'-'.str_pad(@$_POST['fuzzy_day'],2,'0',STR_PAD_LEFT);

		if (!$po->save()) {
			echo json_encode($po->getErrors());
			return;
		}

		echo json_encode(array());
	}

	public function actionAddMedication()
	{
		if (!$patient = Patient::model()->findByPk(@$_POST['patient_id'])) {
			throw new Exception("Patient not found:".@$_POST['patient_id']);
		}

		if (!$drug = Drug::model()->findByPk(@$_POST['selectedMedicationID'])) {
			throw new Exception("Drug not found: ".@$_POST['selectedMedicationID']);
		}

		if (!$route = DrugRoute::model()->findByPk(@$_POST['route_id'])) {
			throw new Exception("Route not found: ".@$_POST['route_id']);
		}

		if (!empty($route->options)) {
			if (!$option = DrugRouteOption::model()->findByPk(@$_POST['option_id'])) {
				throw new Exception("Route option not found: ".@$_POST['option_id']);
			}
		}

		if (!$frequency = DrugFrequency::model()->findByPk(@$_POST['frequency_id'])) {
			throw new Exception("Frequency not found: ".@$_POST['frequency_id']);
		}

		if (!strtotime(@$_POST['start_date'])) {
			throw new Exception("Invalid date: ".@$_POST['start_date']);
		}

		if (@$_POST['edit_medication_id']) {
			if (!$m = Medication::model()->findByPk(@$_POST['edit_medication_id'])) {
				throw new Exception("Medication not found: ".@$_POST['edit_medication_id']);
			}
			$patient->updateMedication($m,array(
				'drug_id' => $drug->id,
				'route_id' => $route->id,
				'option_id' => @$option ? $option->id : null,
				'frequency_id' => $frequency->id,
				'start_date' => $_POST['start_date'],
			));
		} else {
			$patient->addMedication(array(
				'drug_id' => $drug->id,
				'route_id' => $route->id,
				'option_id' => @$option ? $option->id : null,
				'frequency_id' => $frequency->id,
				'start_date' => $_POST['start_date'],
			));
		}

		$this->redirect(array('/patient/view/'.$patient->id));
	}

	public function actionAddFamilyHistory()
	{
		if (!$patient = Patient::model()->findByPk(@$_POST['patient_id'])) {
			throw new Exception("Patient not found:".@$_POST['patient_id']);
		}

		if (!$relative = FamilyHistoryRelative::model()->findByPk(@$_POST['relative_id'])) {
			throw new Exception("Unknown relative: ".@$_POST['relative_id']);
		}

		if (!$side = FamilyHistorySide::model()->findByPk(@$_POST['side_id'])) {
			throw new Exception("Unknown side: ".@$_POST['side_id']);
		}

		if (!$condition = FamilyHistoryCondition::model()->findByPk(@$_POST['condition_id'])) {
			throw new Exception("Unknown condition: ".@$_POST['condition_id']);
		}

		if (@$_POST['edit_family_history_id']) {
			if (!$fh = FamilyHistory::model()->findByPk(@$_POST['edit_family_history_id'])) {
				throw new Exception("Family history not found: ".@$_POST['edit_family_history_id']);
			}
			$fh->relative_id = $relative->id;
			$fh->side_id = $side->id;
			$fh->condition_id = $condition->id;
			$fh->comments = @$_POST['comments'];

			if (!$fh->save()) {
				throw new Exception("Unable to save family history: ".print_r($fh->getErrors(),true));
			}
		} else {
			$patient->addFamilyHistory($relative->id,$side->id,$condition->id,@$_POST['comments']);
		}

		$this->redirect(array('patient/view/'.$patient->id));
	}

	public function actionRemovePreviousOperation()
	{
		if (!$patient = Patient::model()->findByPk(@$_GET['patient_id'])) {
			throw new Exception("Patient not found: ".@$_GET['patient_id']);
		}

		if (!$po = PreviousOperation::model()->find('patient_id=? and id=?',array($patient->id,@$_GET['operation_id']))) {
			throw new Exception("Previous operation not found: ".@$_GET['operation_id']);
		}

		if (!$po->delete()) {
			throw new Exception("Failed to remove previous operation: ".print_r($po->getErrors(),true));
		}

		echo 'success';
	}

	public function actionGetPreviousOperation()
	{
		if (!$po = PreviousOperation::model()->findByPk(@$_GET['operation_id'])) {
			throw new Exception("Previous operation not found: ".@$_GET['operation_id']);
		}

		$date = explode('-',$po->date);

		echo json_encode(array(
			'operation' => $po->operation,
			'side_id' => $po->side_id,
			'fuzzy_year' => $date[0],
			'fuzzy_month' => preg_replace('/^0/','',$date[1]),
			'fuzzy_day' => preg_replace('/^0/','',$date[2]),
		));
	}

	public function actionRemoveMedication()
	{
		if (!$patient = Patient::model()->findByPk(@$_GET['patient_id'])) {
			throw new Exception("Patient not found: ".@$_GET['patient_id']);
		}

		if (!$m = Medication::model()->find('patient_id=? and id=?',array($patient->id,@$_GET['medication_id']))) {
			throw new Exception("Medication not found: ".@$_GET['medication_id']);
		}

		$m->end_date = date('Y-m-d');

		if (!$m->save()) {
			throw new Exception("Failed to remove medication: ".print_r($m->getErrors(),true));
		}

		echo 'success';
	}

	public function actionRemoveFamilyHistory()
	{
		if (!$patient = Patient::model()->findByPk(@$_GET['patient_id'])) {
			throw new Exception("Patient not found: ".@$_GET['patient_id']);
		}

		if (!$m = FamilyHistory::model()->find('patient_id=? and id=?',array($patient->id,@$_GET['family_history_id']))) {
			throw new Exception("Family history not found: ".@$_GET['family_history_id']);
		}

		if (!$m->delete()) {
			throw new Exception("Failed to remove family history: ".print_r($m->getErrors(),true));
		}

		echo 'success';
	}

	public function processJsVars()
	{
		if ($this->patient) {
			$this->jsVars['OE_patient_id'] = $this->patient->id;
		}
		$firm = Firm::model()->findByPk(Yii::app()->session['selected_firm_id']);
		$subspecialty_id = $firm->serviceSubspecialtyAssignment ? $firm->serviceSubspecialtyAssignment->subspecialty_id : null;

		$this->jsVars['OE_subspecialty_id'] = $subspecialty_id;

		parent::processJsVars();
	}

	public function actionInstitutionSites()
	{
		if (!$institution = Institution::model()->findByPk(@$_GET['institution_id'])) {
			throw new Exception("Institution not found: ".@$_GET['institution_id']);
		}

		echo json_encode(CHtml::listData($institution->sites,'id','name'));
	}

	public function actionValidateSaveContact()
	{
		if (!$patient = Patient::model()->findByPk(@$_POST['patient_id'])) {
			throw new Exception("Patient not found: ".@$_POST['patient_id']);
		}

		$errors = array();

		if (!$institution = Institution::model()->findByPk(@$_POST['institution_id'])) {
			$errors['institution_id'] = 'Please select an institution';
		}

		if (@$_POST['site_id']) {
			if (!$site = Site::model()->findByPk($_POST['site_id'])) {
				$errors['site_id'] = 'Invalid site';
			}
		}

		if (@$_POST['contact_label_id'] == 'nonspecialty' && !@$_POST['label_id']) {
			$errors['label_id'] = 'Please select a label';
		}

		$contact = new Contact;

		foreach (array('title','first_name','last_name') as $field) {
			if (!@$_POST[$field]) {
				$errors[$field] = $contact->getAttributeLabel($field).' is required';
			}
		}

		echo json_encode($errors);
	}

	public function actionGetDrugRouteOptions()
	{
		if (!$route = DrugRoute::model()->findByPk(@$_GET['route_id'])) {
			throw new Exception("Drug route not found: ".@$_GET['route_id']);
		}

		$this->renderPartial('_drug_route_options',array('route'=>$route));
	}

	public function actionValidateAddMedication()
	{
		$errors = array();

		if (!$patient = Patient::model()->findByPk(@$_POST['patient_id'])) {
			throw new Exception("Patient not found: ".@$_POST['patient_id']);
		}

		if (!Drug::model()->findByPk(@$_POST['selectedMedicationID'])) {
			$errors['selectedMedicationID'] = "Please select a drug";
		}
		if (!$route = DrugRoute::model()->findByPk(@$_POST['route_id'])) {
			$errors['route_id'] = "Please select a route";
		}
		if (!empty($route->options) && !DrugRouteOption::model()->findByPk(@$_POST['option_id'])) {
			$errors['option_id'] = "Please select a route option";
		}
		if (empty($_POST['frequency_id'])) {
			$errors['frequency_id'] = 'Please select a frequency';
		}
		if (empty($_POST['start_date'])) {
			$errors['start_date'] = 'Please select a date';
		} elseif (!strtotime($_POST['start_date'])) {
			$errors['start_date'] = 'Please enter a date in the format dd mmm yyyy (eg 01 Jan 2013)';
		}

		echo json_encode($errors);
	}

	public function actionAddContact()
	{
		if (@$_POST['site_id']) {
			if (!$site = Site::model()->findByPk($_POST['site_id'])) {
				throw new Exception("Site not found: ".$_POST['site_id']);
			}
		} else {
			if (!$institution = Institution::model()->findByPk(@$_POST['institution_id'])) {
				throw new Exception("Institution not found: ".@$_POST['institution_id']);
			}
		}
		if (!$patient = Patient::model()->findByPk(@$_POST['patient_id'])) {
			throw new Exception("patient required for contact assignment");
		}

		// Attempt to de-dupe by looking for an existing record that matches the user's input
		$criteria = new CDbCriteria;
		$criteria->compare('lower(title)',strtolower($_POST['title']));
		$criteria->compare('lower(first_name)',strtolower($_POST['first_name']));
		$criteria->compare('lower(last_name)',strtolower($_POST['last_name']));

		if (isset($site)) {
			$criteria->compare('site_id',$site->id);
		} else {
			$criteria->compare('institution_id',$institution->id);
		}

		if ($contact = Contact::model()->with('locations')->find($criteria)) {
			foreach ($contact->locations as $location) {
				$pca = new PatientContactAssignment;
				$pca->patient_id = $patient->id;
				$pca->location_id = $location->id;
				if (!$pca->save()) {
					throw new Exception("Unable to save patient contact assignment: ".print_r($pca->getErrors(),true));
				}

				$this->redirect(array('/patient/view/'.$patient->id));
			}
		}

		$contact = new Contact;
		$contact->attributes = $_POST;

		if (@$_POST['contact_label_id'] == 'nonspecialty') {
			if (!$label = ContactLabel::model()->findByPk(@$_POST['label_id'])) {
				throw new Exception("Contact label not found: ".@$_POST['label_id']);
			}
		} else {
			if (!$label = ContactLabel::model()->find('name=?',array(@$_POST['contact_label_id']))) {
				throw new Exception("Contact label not found: ".@$_POST['contact_label_id']);
			}
		}

		$contact->contact_label_id = $label->id;

		if (!$contact->save()) {
			throw new Exception("Unable to save contact: ".print_r($contact->getErrors(),true));
		}

		$cl = new ContactLocation;
		$cl->contact_id = $contact->id;
		if (isset($site)) {
			$cl->site_id = $site->id;
		} else {
			$cl->institution_id = $institution->id;
		}

		if (!$cl->save()) {
			throw new Exception("Unable to save contact location: ".print_r($cl->getErrors(),true));
		}

		$pca = new PatientContactAssignment;
		$pca->patient_id = $patient->id;
		$pca->location_id = $cl->id;

		if (!$pca->save()) {
			throw new Exception("Unable to save patient contact assignment: ".print_r($pca->getErrors(),true));
		}

		$this->redirect(array('/patient/view/'.$patient->id));
	}

	public function actionGetContactLocation()
	{
		if (!$location = ContactLocation::model()->findByPk(@$_GET['location_id'])) {
			throw new Exception("ContactLocation not found: ".@$_GET['location_id']);
		}

		$data = array();

		if ($location->site) {
			$data['institution_id'] = $location->site->institution_id;
			$data['site_id'] = $location->site_id;
		} else {
			$data['institution_id'] = $location->institution_id;
			$data['site_id'] = null;
		}

		$data['contact_id'] = $location->contact_id;
		$data['name'] = $location->contact->fullName;

		echo json_encode($data);
	}

	public function actionValidateEditContact()
	{
		if (!$patient = Patient::model()->findByPk(@$_POST['patient_id'])) {
			throw new Exception("Patient not found: ".@$_POST['patient_id']);
		}

		if (!$contact = Contact::model()->findByPk(@$_POST['contact_id'])) {
			throw new Exception("Contact not found: ".@$_POST['contact_id']);
		}

		$errors = array();

		if (!@$_POST['institution_id']) {
			$errors['institution_id'] = 'Please select an institution';
		} else {
			if (!$institution = Institution::model()->findByPk(@$_POST['institution_id'])) {
				throw new Exception("Institution not found: ".@$_POST['institution_id']);
			}
		}

		if (@$_POST['site_id']) {
			if (!$site = Site::model()->findByPk(@$_POST['site_id'])) {
				throw new Exception("Site not found: ".@$_POST['site_id']);
			}
		}

		echo json_encode($errors);
	}

	public function actionEditContact()
	{
		if (!$patient = Patient::model()->findByPk(@$_POST['patient_id'])) {
			throw new Exception("Patient not found: ".@$_POST['patient_id']);
		}

		if (!$contact = Contact::model()->findByPk(@$_POST['contact_id'])) {
			throw new Exception("Contact not found: ".@$_POST['contact_id']);
		}

		if (@$_POST['site_id']) {
			if (!$site = Site::model()->findByPk(@$_POST['site_id'])) {
				throw new Exception("Site not found: ".@$_POST['site_id']);
			}
			if (!$cl = ContactLocation::model()->find('contact_id=? and site_id=?',array($contact->id,$site->id))) {
				$cl = new ContactLocation;
				$cl->contact_id = $contact->id;
				$cl->site_id = $site->id;

				if (!$cl->save()) {
					throw new Exception("Unable to save contact location: ".print_r($cl->getErrors(),true));
				}
			}
		} else {
			if (!$institution = Institution::model()->findByPk(@$_POST['institution_id'])) {
				throw new Exception("Institution not found: ".@$_POST['institution_id']);
			}

			if (!$cl = ContactLocation::model()->find('contact_id=? and institution_id=?',array($contact->id,$institution->id))) {
				$cl = new ContactLocation;
				$cl->contact_id = $contact->id;
				$cl->institution_id = $institution->id;

				if (!$cl->save()) {
					throw new Exception("Unable to save contact location: ".print_r($cl->getErrors(),true));
				}
			}
		}

		if (!$pca = PatientContactAssignment::model()->findByPk(@$_POST['pca_id'])) {
			throw new Exception("PCA not found: ".@$_POST['pca_id']);
		}

		$pca->location_id = $cl->id;

		if (!$pca->save()) {
			throw new Exception("Unable to save patient contact assignment: ".print_r($pca->getErrors(),true));
		}

		$this->redirect(array('/patient/view/'.$patient->id));
	}

	public function actionSendSiteMessage()
	{
		$message = Yii::app()->mailer->newMessage();
		$message->setFrom(array($_POST['newsite_from'] => User::model()->findByPk(Yii::app()->user->id)->fullName));
		$message->setTo(array(Yii::app()->params['helpdesk_email']));
		$message->setSubject($_POST['newsite_subject']);
		$message->setBody($_POST['newsite_message']);
		echo Yii::app()->mailer->sendMessage($message) ? '1' : '0';
	}

	public function actionGetMedication()
	{
		if (!$m = Medication::model()->findByPk(@$_GET['medication_id'])) {
			throw new Exception("Medication not found: ".@$_GET['medication_id']);
		}

		echo json_encode(array(
			'drug_id' => $m->drug_id,
			'drug_name' => $m->drug->name,
			'route_id' => $m->route_id,
			'option_id' => $m->option_id,
			'frequency_id' => $m->frequency_id,
			'start_date' => Helper::convertMysql2NHS($m->start_date),
			'route_options' => $this->renderPartial('_drug_route_options',array('route'=>$m->route),true),
		));
	}

	public function actionDrugList()
	{
		if (Yii::app()->request->isAjaxRequest) {
			$criteria = new CDbCriteria();
			if (isset($_GET['term']) && $term = $_GET['term']) {
				$criteria->addCondition(array('LOWER(name) LIKE :term', 'LOWER(aliases) LIKE :term'), 'OR');
				$params[':term'] = '%' . strtolower(strtr($term, array('%' => '\%'))) . '%';
			}
			$criteria->order = 'name';
			$criteria->params = $params;
			$drugs = Drug::model()->findAll($criteria);
			$return = array();
			foreach ($drugs as $drug) {
				$return[] = array(
						'label' => $drug->tallmanlabel,
						'value' => $drug->tallman,
						'id' => $drug->id,
				);
			}
			echo CJSON::encode($return);
		}
	}

	public function actionDrugDefaults()
	{
		if (!$drug = Drug::model()->findByPk(@$_GET['drug_id'])) {
			throw new Exception("Unable to save drug: ".print_r($drug->getErrors(),true));
		}

		echo json_encode(array('route_id'=>$drug->default_route_id,'frequency_id'=>$drug->default_frequency_id));
	}

	public function actionVerifyAddNewEpisode()
	{
		if (!$patient = Patient::model()->findByPk(@$_GET['patient_id'])) {
			throw new Exception("Patient not found: ".@$_GET['patient_id']);
		}

		$firm = Firm::model()->findByPk(Yii::app()->session['selected_firm_id']);

		if ($patient->hasOpenEpisodeOfSubspecialty($firm->getSubspecialtyID())) {
			echo "0";
			return;
		}

		echo "1";
	}

	public function actionAddNewEpisode()
	{
		if (!$patient = Patient::model()->findByPk(@$_POST['patient_id'])) {
			throw new Exception("Patient not found: ".@$_POST['patient_id']);
		}

		if (!empty($_POST['firm_id'])) {
			$firm = Firm::model()->findByPk($_POST['firm_id']);
			if (!$episode = $patient->getOpenEpisodeOfSubspecialty($firm->getSubspecialtyID())) {
				$episode = $patient->addEpisode($firm);
			}

			$this->redirect(array('/patient/episode/'.$episode->id));
		}

		return $this->renderPartial('//patient/add_new_episode',array(
			'patient' => $patient,
			'firm' => Firm::model()->findByPk(Yii::app()->session['selected_firm_id']),
		),false, true);
	}

	public function getEpisodes()
	{
		if ($this->patient && empty($this->episodes)) {
			$this->episodes = array(
				'ordered_episodes'=>$this->patient->getOrderedEpisodes(),
				'legacyepisodes'=>$this->patient->legacyepisodes,
				'supportserviceepisodes'=>$this->patient->supportserviceepisodes,
			);
		}
		return $this->episodes;
	}

	/**
	 * Check create access for the specified event type
	 *
	 * @param Episode $episode
	 * @param EventType $event_type
	 * @return boolean
	 */
	public function checkCreateAccess(Episode $episode, EventType $event_type)
	{
		$oprn = 'OprnCreate' . ($event_type->class_name == 'OphDrPrescription' ? 'Prescription' : 'Event');
		return $this->checkAccess($oprn, $this->firm, $episode, $event_type);
	}

	public function actionValidatePatientDetails($id)
	{
		if (!$patient = Patient::model()->findByPk($id)) {
			throw new Exception("Patient not found: $id");
		}

		$errors = array();

		$patient->attributes = Helper::convertNHS2MySQL($_POST);

		if (!$patient->validate()) {
			$errors = $patient->getErrors();
		}

		if (!$contact = $patient->contact) {
			$contact = new Contact;
		}

		$contact->attributes = $_POST;

		if (!$contact->validate()) {
			$errors = array_merge($errors, $contact->getErrors());
		}

		if (!$contact->last_name) {
			$errors['last_name'] = array('Last name is required');
		}

		if (!$address = $contact->address) {
			$address = new Address;
		}

		$address->attributes = $_POST;

		if (!$address->validate()) {
			$errors = array_merge($errors, $address->getErrors());
		}

		foreach (PatientMetadataKey::model()->findAll(array('order'=>'display_order asc')) as $metadata_key) {
			if ($metadata_key->required && strlen($_POST[$metadata_key->key_name]) <1) {
				$errors[$metadata_key->key_name] = array($metadata_key->key_label.' is required');
			}
		}

		echo json_encode($errors);
	}

	public function actionUpdatePatientDetails($id)
	{
		if (!$patient = Patient::model()->findByPk($id)) {
			throw new Exception("Patient not found: $id");
		}

		$transaction = Yii::app()->db->beginTransaction();

		empty($_POST['date_of_death']) && $_POST['date_of_death'] = null;
		empty($_POST['dob']) && $_POST['dob'] = null;

		$patient->attributes = Helper::convertNHS2MySQL($_POST);

		if (!$patient->save()) {
			throw new Exception("Unable to save patient: ".print_r($patient->getErrors(),true));
		}

		if (!$contact = $patient->contact) {
			$contact = new Contact;

			$contact_is_new = true;
		}

		$contact->attributes = $_POST;

		if (!$contact->save()) {
			throw new Exception("Unable to save contact: ".print_r($contact->getErrors(),true));
		}

		if (@$contact_is_new) {
			$patient->contact_id = $contact->id;

			if (!$patient->save()) {
				throw new Exception("Unable to set patient contact_id: ".print_r($patient->getErrors(),true));
			}
		}

		if (!$address = $contact->address) {
			if (!$address_type = AddressType::model()->find('name=?',array('Home'))) {
				throw new Exception("AddressType 'Home' not found");
			}

			$address = new Address;
			$address->address_type_id = $address_type->id;
			$address->contact_id = $contact->id;
		}

		$address->attributes = $_POST;

		if (!$address->save()) {
			throw new Exception("Unable to save patient address: ".print_r($address->getErrors(),true));
		}

		$this->saveMetadataKeys($patient);

		$transaction->commit();

		$patient->audit('patient','update',serialize($_POST));

		echo "1";
	}

	public function saveMetadataKeys($patient)
	{
		foreach (PatientMetadataKey::model()->findAll() as $metadata_key) {
			if (isset($_POST[$metadata_key->key_name])) {
				$patient->setMetadata($metadata_key->key_name,$_POST[$metadata_key->key_name]);
			}
		}
	}

	public function actionValidatePatientContactDetails($id)
	{
		if (!$patient = Patient::model()->findByPk($id)) {
			throw new Exception("Patient not found: $id");
		}

		$errors = array();

		$contact = $patient->contact->isPatient();

		$contact->attributes = Helper::convertNHS2MySQL($_POST);

		if (!$contact->validate()) {
			$errors = $contact->getErrors();
		}

		if (!$address = $contact->address) {
			$address = new Address;
		}

		$address->attributes = $_POST;

		if (!$address->validate()) {
			$errors = array_merge($errors, $address->getErrors());
		}

		echo json_encode($errors);
	}

	public function actionUpdatePatientContactDetails($id)
	{
		if (!$patient = Patient::model()->findByPk($id)) {
			throw new Exception("Patient not found: $id");
		}

		$transaction = Yii::app()->db->beginTransaction();

		$contact = $patient->contact;

		$contact->attributes = Helper::convertNHS2MySQL($_POST);

		if (!$contact->save()) {
			throw new Exception("Unable to save patient contact: ".print_r($contact->getErrors(),true));
		}

		if (!$address = $patient->contact->address) {
			if (!$address_type = AddressType::model()->find('name=?',array('Home'))) {
				throw new Exception("AddressType 'Home' not found");
			}

			$address = new Address;
			$address->address_type_id = $address_type->id;
			$address->contact_id = $patient->contact->id;
		}

		$address->attributes = $_POST;

		if (!$address->save()) {
			throw new Exception("Unable to save patient address: ".print_r($address->getErrors(),true));
		}

		$this->saveMetadataKeys($patient);

		$transaction->commit();

		$patient->audit('patient','update',serialize($_POST));

		echo "1";
	}

	public function actionCreate()
	{
		Yii::app()->assetManager->registerScriptFile('js/patientSummary.js');

		$patient = new Patient;
		$contact = new Contact;
		$address = new Address;
		$gp = new Gp;
		$practice = new Practice;
		$address->country_id = Country::model()->find('name=?',array(Yii::app()->params['default_country']))->id;

		$errors = array();

		if (!empty($_POST)) {
			foreach (array('date_of_death','dob','gp_id','practice_id') as $field) {
				empty($_POST[$field]) && $_POST[$field] = null;
			}

			$transaction = Yii::app()->db->beginTransaction();

			!empty($_POST['gp_id']) && $gp = Gp::model()->findByPk($_POST['gp_id']);
			!empty($_POST['practice_id']) && $practice = Practice::model()->findByPk($_POST['practice_id']);

			$patient->attributes = Helper::convertNHS2MySQL($_POST);

			if (!$patient->save()) {
				$errors = $patient->getErrors();
			}

			$contact->isPatient();
			$contact->attributes = $_POST;

			if (!$contact->save()) {
				$errors = array_merge($errors, $contact->getErrors());
			}

			if ($patient->id && $contact->id) {
				$patient->contact_id = $contact->id;

				if (!$patient->save()) {
					throw new Exception("Unable to set patient contact_id: ".print_r($patient->getErrors(),true));
				}
			}

			$address_type = AddressType::model()->find('name=?',array('Home'));

			$address->address_type_id = $address_type->id;
			$address->contact_id = $contact->id;

			$address->attributes = $_POST;

			foreach (PatientMetadataKey::model()->findAll(array('order'=>'display_order asc')) as $metadata_key) {
				if ($metadata_key->required && strlen(@$_POST[$metadata_key->key_name]) <1) {
					$errors[$metadata_key->key_name] = array($metadata_key->key_label.' cannot be blank.');
				} else {
					if (!$metadata_key->disabled) {
						$patient->id && $patient->setMetadata($metadata_key->key_name,$_POST[$metadata_key->key_name]);
					}
				}
			}

			if (empty($errors)) {
				if (!$address->save()) {
					$errors = array_merge($errors, $address->getErrors());
				} else {
					$transaction->commit();

					$patient->audit('patient','create',serialize($_POST));

					return $this->redirect(array('/patient/view/'.$patient->id));
				}
			} else {
				if (!$address->validate()) {
					$errors = array_merge($errors, $address->getErrors());
				}
			}

			$transaction->rollback();
		}

		$this->renderPatientPanel = false;
		$this->render('create', array(
			'patient' => $patient,
			'contact' => $contact,
			'address' => $address,
			'gp' => $gp,
			'practice' => $practice,
			'errors' => $errors,
		));
	}

	public function actionGPSearch()
	{
		$term = '%'.strtolower($_GET['term']).'%';

		$gps = array();

		$command = Yii::app()->db->createCommand()
			->select("c.*, gp.id as gp_id, a.address1, a.city")
			->from("contact c")
			->join("gp","gp.contact_id = c.id")
			->leftJoin("address a","a.contact_id = c.id");

		if (strstr($term,' ')) {
			$command = $command->where("lower(concat(c.first_name,' ',c.last_name)) like :term",array(
				":term" => $term,
			));
		} else {
			$command = $command->where("lower(c.last_name) like :term",array(
				":term" => $term,
			));
		}

		foreach ($command->order("last_name asc, first_name asc")->queryAll() as $contact) {
			$line = trim($contact['title'].' '.$contact['first_name'].' '.$contact['last_name']);

			if (Yii::app()->user->checkAccess('admin')) {
				if ($contact['address1'] || $contact['city']) {
					$line .= ' (';

					if ($contact['address1']) {
						$line .= $contact['address1'];
					}

					if ($contact['city']) {
						if ($contact['address1']) {
							$line .= ", ";
						}
						$line .= $contact['city'];
					}

					$line .= ")";
				}
			}

			$gps[] = array(
				'line' => $line,
				'gp_id' => $contact['gp_id'],
			);
		}

		echo CJavaScript::jsonEncode($gps);
	}

	public function actionGetGPDetails()
	{
		if (!$gp = Gp::model()->with(array('contact' => array('with' => 'address')))->findByPk($_GET['gp_id'])) {
			throw new Exception("GP not found: ".$_GET['gp_id']);
		}

		$gp_details = array(
			'name' => trim($gp->contact->title.' '.$gp->contact->first_name.' '.$gp->contact->last_name),
		);

		if (Yii::app()->user->checkAccess('admin')) {
			$gp_details['address'] = $gp->contact->address ? $gp->contact->address->letterLine : 'Unknown';
			$gp_details['telephone'] = $gp->contact->primary_phone ? $gp->contact->primary_phone : 'Unknown';
		}

		echo CJavaScript::jsonEncode($gp_details);
	}

	public function actionPracticeSearch()
	{
		$term = '%'.strtolower($_GET['term']).'%';

		$practices = array();

		foreach (Yii::app()->db->createCommand()
			->select("p.phone, p.id as practice_id, a.*")
			->from("practice p")
			->join("contact c","p.contact_id = c.id")
			->join("address a","a.contact_id = c.id")
			->where("lower(concat(address1,' ',address2,' ',city,' ',county,' ',postcode,' ',phone)) like :term",array(
				":term" => $term,
			))
			->order("address1 asc,address2 asc,city asc,county asc,postcode asc,phone asc")
			->queryAll() as $practice) {

			$fields = array();

			foreach (array('address1','address2','city','county','postcode','phone') as $field) {
				if ($practice[$field]) {
					$fields[] = $practice[$field];
				}
			}

			$practices[] = array(
				'line' => implode(' ',$fields),
				'practice_id' => $practice['practice_id'],
			);
		}

		echo CJavaScript::jsonEncode($practices);
	}

	public function actionGetPracticeDetails()
	{
		if (!$practice = Practice::model()->with(array('contact' => array('with' => 'address')))->findByPk($_GET['practice_id'])) {
			throw new Exception("Practice not found: ".$_GET['practice_id']);
		}

		$practice_details = array(
			'address' => $practice->contact->address ? $practice->contact->address->letterLine : 'Unknown',
			'telephone' => $practice->phone ? $practice->phone : 'Unknown',
		);

		echo CJavaScript::jsonEncode($practice_details);
	}

	public function actionUpdatePatientGPAndPracticeDetails($id)
	{
		if (!$patient = Patient::model()->findByPk($id)) {
			throw new Exception("Patient not found: $id");
		}

		$patient->gp_id = $_POST['gp_id'] ? $_POST['gp_id'] : null;
		$patient->practice_id = $_POST['practice_id'] ? $_POST['practice_id'] : null;

		$patient->audit('patient','update-gp-and-practice',serialize($_POST));

		if (!$patient->save()) {
			throw new Exception("Unable to save patient: ".print_r($patient->getErrors(),true));
		}

		echo "1";
	}

	public function actionDelete($id)
	{
		Yii::app()->assetManager->registerScriptFile('js/patientSummary.js');

		$this->patient = $this->loadModel($id);

		if (@$_POST['delete']) {
			if (!$this->patient->softDelete()) {
				throw new Exception("Unable to soft-delete patient: ".print_r($this->patient->getErrors(),true));
			}

			$this->patient->audit('patient','delete');

			echo "1";
			return;
		}

		$tabId = !empty($_GET['tabId']) ? $_GET['tabId'] : 0;
		$eventId = !empty($_GET['eventId']) ? $_GET['eventId'] : 0;

		$episodes = $this->patient->episodes;
		// TODO: verify if ordered_episodes complete supercedes need for unordered $episodes
		$ordered_episodes = $this->patient->getOrderedEpisodes();

		$legacyepisodes = $this->patient->legacyepisodes;
		// NOTE that this is not being used in the render
		$supportserviceepisodes = $this->patient->supportserviceepisodes;

		$this->patient->audit('patient','view-delete-page');

		$this->logActivity('viewed patient');

		$episodes_open = 0;
		$episodes_closed = 0;

		foreach ($episodes as $episode) {
			if ($episode->end_date === null) {
				$episodes_open++;
			} else {
				$episodes_closed++;
			}
		}

		$this->jsVars['currentContacts'] = $this->patient->currentContactIDS();

		$this->breadcrumbs=array(
			$this->patient->first_name.' '.$this->patient->last_name. '('.$this->patient->hos_num.')',
		);

		$this->render('delete', array(
			'tab' => $tabId,
			'event' => $eventId,
			'firm' => Firm::model()->findByPk(Yii::app()->session['selected_firm_id']),
			'supportserviceepisodes' => $supportserviceepisodes,
		));
	}

	public function actionValidateMedication()
	{
		$medication = new Medication;
		$medication->attributes = Helper::convertNHS2MySQL($_POST);

		$errors = array();

		if (!$medication->validate()) {
			foreach ($medication->getErrors() as $error) {
				$errors[] = $error[0];
			}
		}

		if (!empty($errors)) {
			echo json_encode(array(
				'status' => 'error',
				'errors' => $errors,
			));
		} else {
			echo json_encode(array(
				'status' => 'ok',
				'row' => $this->widget('application.widgets.MedicationSelection',array(
					'medication' => $medication,
					'i' => @$_POST['i'],
					'edit' => true,
					'input_name' => @$_POST['input_name'],
				), true)
			));
		}
	}

	public function actionGetAge()
	{
		$patient = new Patient;

		$patient->dob = date('Y-m-d',strtotime($_GET['dob']));
		$patient->yob = $_GET['yob'];
		$patient->date_of_death = $_GET['dod'];

		echo $patient->age;
	}

	public function actionGetYearOfBirth()
	{
		if (isset($_GET['dob'])) {
			echo preg_replace('/[0-9]{4}$/','',$_GET['dob']).(date('Y') - $_GET['age']);
		} else {
			echo date('Y') - $_GET['age'];
		}
	}
}
