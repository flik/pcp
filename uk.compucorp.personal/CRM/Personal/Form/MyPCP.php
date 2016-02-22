<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Personal_Form_MyPCP extends CRM_Core_Form {
  
  static $_links = NULL;

  public function buildQuickForm() {

    // add form elements
    $this->add(
      'select', // field type
      'favorite_color', // field name
      'Favorite Color', // field label
      $this->getColorOptions(), // list of options
      TRUE // is required
    );
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();


   
    
    /*////////////////// Start Contact Query ////////////////////////*/
   $this->_sortByCharacter = CRM_Utils_Request::retrieve('sortByCharacter',
      'String',
      $this
    );
    if ($this->_sortByCharacter == 1 ||
      !empty($_POST)
    ) {
      $this->_sortByCharacter = '';
    }

    $status = CRM_PCP_BAO_PCP::buildOptions('status_id', 'create');

    $pcpSummary = $params = array();
    $whereClause = NULL;

    if (!empty($_POST) || !empty($_GET['page_type'])) {
      if (!empty($_POST['status_id'])) {
        $whereClause = ' AND cp.status_id = %1';
        $params['1'] = array($_POST['status_id'], 'Integer');
      }

      if (!empty($_POST['page_type'])) {
        $whereClause .= ' AND cp.page_type = %2';
        $params['2'] = array($_POST['page_type'], 'String');
      }
      elseif (!empty($_GET['page_type'])) {
        $whereClause .= ' AND cp.page_type = %2';
        $params['2'] = array($_GET['page_type'], 'String');
      }

      if (!empty($_POST['page_id'])) {
        $whereClause .= ' AND cp.page_id = %4 AND cp.page_type = "contribute"';
        $params['4'] = array($_POST['page_id'], 'Integer');
      }

      if (!empty($_POST['event_id'])) {
        $whereClause .= ' AND cp.page_id = %5 AND cp.page_type = "event"';
        $params['5'] = array($_POST['event_id'], 'Integer');
      }

      if ($whereClause) {
        $this->set('whereClause', $whereClause);
        $this->set('params', $params);
      }
      else {
        $this->set('whereClause', NULL);
        $this->set('params', NULL);
      }
    }

    $approvedId = CRM_Core_OptionGroup::getValue('pcp_status', 'Approved', 'name');

    //check for delete CRM-4418
    $allowToDelete = CRM_Core_Permission::check('delete in CiviContribute');

    // get all contribution pages
    $query = "SELECT id, title, start_date, end_date FROM civicrm_contribution_page WHERE (1)";
    $cpages = CRM_Core_DAO::executeQuery($query);
    while ($cpages->fetch()) {
      $pages['contribute'][$cpages->id]['id'] = $cpages->id;
      $pages['contribute'][$cpages->id]['title'] = $cpages->title;
      $pages['contribute'][$cpages->id]['start_date'] = $cpages->start_date;
      $pages['contribute'][$cpages->id]['end_date'] = $cpages->end_date;
    }

    // get all event pages. pcp campaign start and end dates for event related pcp's use the online registration start and end dates,
    // although if target is contribution page this might not be correct. fixme? dgg
    $query = "SELECT id, title, start_date, end_date, registration_start_date, registration_end_date
                  FROM civicrm_event
                  WHERE is_template IS NULL OR is_template != 1";
    $epages = CRM_Core_DAO::executeQuery($query);
    while ($epages->fetch()) {
      $pages['event'][$epages->id]['id'] = $epages->id;
      $pages['event'][$epages->id]['title'] = $epages->title;
      $pages['event'][$epages->id]['start_date'] = $epages->registration_start_date;
      $pages['event'][$epages->id]['end_date'] = $epages->registration_end_date;
    }

    $params = $this->get('params') ? $this->get('params') : array();

    $title = '1';
    if ($this->_sortByCharacter !== NULL) {
      $clauses[] = "cp.title LIKE '" . strtolower(CRM_Core_DAO::escapeWildCardString($this->_sortByCharacter)) . "%'";
    }

    $query = "
        SELECT cp.id, cp.contact_id , cp.status_id, cp.title, cp.is_active, cp.page_type, cp.page_id
        FROM civicrm_pcp cp
        WHERE $title" . $this->get('whereClause') . " ORDER BY cp.status_id";

    $pcp = CRM_Core_DAO::executeQuery($query, $params);
    while ($pcp->fetch()) {
      $action = array_sum(array_keys($this->links()));
      $contact = CRM_Contact_BAO_Contact::getDisplayAndImage($pcp->contact_id);

      $class = '';

      if ($pcp->status_id != $approvedId || $pcp->is_active != 1) {
        $class = 'disabled';
      }

      switch ($pcp->status_id) {
        case 2:
          $action -= CRM_Core_Action::RENEW;
          break;

        case 3:
          $action -= CRM_Core_Action::REVERT;
          break;
      }

      switch ($pcp->is_active) {
        case 1:
          $action -= CRM_Core_Action::ENABLE;
          break;

        case 0:
          $action -= CRM_Core_Action::DISABLE;
          break;
      }

      if (!$allowToDelete) {
        $action -= CRM_Core_Action::DELETE;
      }

      $page_type = $pcp->page_type;
      $page_id = (int) $pcp->page_id;
      if ($pages[$page_type][$page_id]['title'] == '' || $pages[$page_type][$page_id]['title'] == NULL) {
        $title = '(no title found for ' . $page_type . ' id ' . $page_id . ')';
      }
      else {
        $title = $pages[$page_type][$page_id]['title'];
      }

      if ($pcp->page_type == 'contribute') {
        $pageUrl = CRM_Utils_System::url('civicrm/' . $page_type . '/transact', 'reset=1&id=' . $pcp->page_id);
      }
      else {
        $pageUrl = CRM_Utils_System::url('civicrm/' . $page_type . '/register', 'reset=1&id=' . $pcp->page_id);
      }

    $prms = array('id' => $pcp->id);

    CRM_Core_DAO::commonRetrieve('CRM_PCP_DAO_PCP', $prms, $pcpInfo);

      //Getting Amount of the Compaign
      $targetAmount = CRM_PCP_BAO_PCP::thermoMeter($pcp->id);
     // $raisedAmount = round($targetAmount / $pcpInfo['goal_amount'] * 100, 2);

      // Getting No of contributors
      $donors = CRM_PCP_BAO_PCP::honorRoll($pcp->id);
      $NoOfContributors = count($donors);
      
      $pcpSummary[$pcp->id] = array(
        'id' => $pcp->id,
        'start_date' => $pages[$page_type][$page_id]['start_date'],
        'end_date' => $pages[$page_type][$page_id]['end_date'],
        'supporter' => $contact['0'],
        'supporter_id' => $pcp->contact_id,
        'status_id' => $status[$pcp->status_id],
        'page_id' => $page_id,
        'page_title' => $title,
        'page_url' => $pageUrl,
        'page_type' => $page_type,
        'action' => CRM_Core_Action::formLink(self::links(), $action,
          array('id' => $pcp->id), ts('more'), FALSE, 'contributionpage.pcp.list', 'PCP', $pcp->id
        ),
        'title' => $pcp->title,
        'class' => $class,
'no_of_contributors' => $NoOfContributors ? $NoOfContributors : '0.0',
        'target_amount' => $pcpInfo['goal_amount'] ? $pcpInfo['goal_amount'] : 0.00, 
        'raised_amount' => $targetAmount ? $targetAmount : '0.0',
      );
    }

    $this->search();
    $this->pagerAToZ($this->get('whereClause'), $params);
 
    $this->assign('rows', $pcpSummary);

    // Let template know if user has run a search or not
    if ($this->get('whereClause')) {
      $this->assign('isSearch', 1);
    }
    else {
      $this->assign('isSearch', 0);
    }
    
    /*//////////////////////////////////*/ 


  }

  public function postProcess() {
    $values = $this->exportValues();
    $options = $this->getColorOptions();
    CRM_Core_Session::setStatus(ts('You picked color "%1"', array(
      1 => $options[$values['favorite_color']]
    )));
    parent::postProcess();
  }

  public function getColorOptions() {
    $options = array(
      '' => ts('- select -'),
      '#f00' => ts('Red'),
      '#0f0' => ts('Green'),
      '#00f' => ts('Blue'),
      '#f0f' => ts('Purple'),
    );
    foreach (array('1','2','3','4','5','6','7','8','9','a','b','c','d','e') as $f) {
      $options["#{$f}{$f}{$f}"] = ts('Grey (%1)', array(1 => $f));
    }
    return $options;
  }




  /**
   * @TODO this function changed, debug this at runtime
   * @param $whereClause
   * @param array $whereParams
   */
  public function pagerAtoZ($whereClause, $whereParams) {
    $where = '';
    if ($whereClause) {
      if (strpos($whereClause, ' AND') == 0) {
        $whereClause = substr($whereClause, 4);
      }
      $where = 'WHERE ' . $whereClause;
    }

    $query = "
 SELECT UPPER(LEFT(cp.title, 1)) as sort_name
 FROM civicrm_pcp cp
   " . $where . "
 ORDER BY LEFT(cp.title, 1);
        ";

    $dao = CRM_Core_DAO::executeQuery($query, $whereParams);

    $aToZBar = CRM_Utils_PagerAToZ::getAToZBar($dao, $this->_sortByCharacter, TRUE);
    $this->assign('aToZ', $aToZBar);
  }
  
  /*//////////////////Search Function ///////////////*/
  
  public function search() {

    if ($this->_action & CRM_Core_Action::DELETE) {
      return;
    }

//    $form = new CRM_Core_Controller_Simple('CRM_Myextension_Form_Personal', ts('Search Campaign Pages'), CRM_Core_Action::ADD);
//    $form->setEmbedded(TRUE);
//    $form->setParent($this);
//    $form->process();
//    $form->run();
  }
  





  /**
   * Get action Links.
   *
   * @return array
   *   (reference) of action links
   */
  public function &links() {
    if (!(self::$_links)) {
      // helper variable for nicer formatting
      $deleteExtra = ts('Are you sure you want to delete this Campaign Page ?');

      self::$_links = array(
        CRM_Core_Action::UPDATE => array(
          'name' => ts('Edit'),
          'url' => 'civicrm/pcp/info',
          'qs' => 'action=update&reset=1&id=%%id%%&context=dashboard',
          'title' => ts('Edit Personal Campaign Page'),
        ),
      );
    }
    return self::$_links;
  }


  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
