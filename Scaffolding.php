<?php
/*
Copyright (c) 2011, Alex Oroshchuk
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.

    * Neither the name of Zend Technologies USA, Inc. nor the names of its
      contributors may be used to endorse or promote products derived from this
      software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

/**
 * This Zend controller extension class allows you to quickly scaffold
 * and admin interface for an application, using Zend MVC core components.
 * The controllers you would like to scaffold must extend this one, and you will
 * automatically have create, update, delete and list actions.
 *
 * @author Alex Oroshchuk (oroshchuk@gmail.com)
 * @copyright 2011 Alex Oroshchuk
 * @version 0.8.1
 */

class Zend_Controller_Scaffolding extends Zend_Controller_Action
{

    /**
     * Controller actions used as CRUD operations.
     */
    const ACTION_INDEX  = 'index';
    const ACTION_LIST   = 'list';
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';

    /**
     * Create form button definitions.
     */
    const BUTTON_SAVE       = 'save';
    const BUTTON_SAVEEDIT   = 'saveedit';
    const BUTTON_SAVECREATE = 'savecreate';

    /**
     * Message types.
     */
    const MSG_OK  = 'OK';
    const MSG_ERR = 'ERR';

    /**
     * Identifier used in view generation.
     */
    const CSS_ID  = 'zs';

    /**
     * Create form button default labels.
     */
    protected $buttonLabels    = array(
        self::BUTTON_SAVE       => 'Save',
        self::BUTTON_SAVEEDIT   => 'Save and continue editing',
        self::BUTTON_SAVECREATE => 'Save and create new one'
    );

    /**
     * Messages displayed upon record creation, update or deletion.
     */
    protected $messages = array(
        self::ACTION_CREATE => array(
            self::MSG_OK  => 'New %s has been created.',
            self::MSG_ERR => 'Failed to create new %s.'
        ),
        self::ACTION_UPDATE => array(
            self::MSG_OK  => 'The %s has been updated.',
            self::MSG_ERR => 'Failed to update %s.'
        ),
        self::ACTION_DELETE => array(
            self::MSG_OK  => 'The %s has been deleted.',
            self::MSG_ERR => 'Failed to delete %s.'
        )
    );

    /**
     * Data providing class.
     * @var Zend_Db_Table_Abstract|Zend_Db_Table_Select|Zend_Db_Select
     */
    /**
     * @todo: URGENT refactor scaffSelectCriteria
     */
    // protected $scaffSelectCriteria;

    /**
     * Default scaffolding options.
     * @var Array
     */
    private $options = array(
        'pkEditable'        => false,
        'viewFolder'        => 'scaffolding',
        'entityTitle'       => 'entity',
        'createEntityText'  => null,
        'updateEntityText'  => null,
        'deleteEntityText'  => null,
        'readonly'          => false,
        'disabledActions'   => array(),
        'editFormButtons'     => array(
            self::BUTTON_SAVE,
            self::BUTTON_SAVEEDIT,
            self::BUTTON_SAVECREATE
        ),
        'csrfProtected'     => true,
        'customMessenger'   => false,
        'translator'        => null,
        'actionParams'      => null,
        'editLayout'        => null
    );

    /**
     * Scaffolding field definitions.
     * @var Array
     */
    private $fields;

    /**
     * Data providing class.
     * @var Zend_Db_Table_Abstract|Zend_Db_Table_Select|Zend_Db_Select
     */
    private $dbSource;

    /**
     * Cached table metadata.
     * @var Array
     */
    private $metaData;

    /**
     * Initializes scaffolding.
     *
     * @param Zend_Db_Table_Abstract|Zend_Db_Select $dbSource respective model instance
     * @param array $fields field definitions
     * @param Zend_Config|Array $options
     */
    protected function scaffold($dbSource, $fields = array(), $options = null)
    {
        // Check arguments.
        if (!($dbSource instanceof Zend_Db_Table_Abstract
                || $dbSource instanceof Zend_Db_Table_Select)) {
            throw new Zend_Controller_Exception(
                    'Scaffolding initialization requires an instance of '
                    . 'Zend_Db_Table_Abstract or Zend_Db_Table_Select.');
        }

        $this->dbSource = $dbSource;
        $this->fields = $fields;
        if (is_array($options)) {
            $this->options = array_merge($this->options, $options);
        }

        if (isset($this->options['translator']) && !($this->options['translator'] instanceof Zend_Translate)) {
            throw new Zend_Controller_Exception("'translator' option must be instance of Zend_Translate.");
        }

        // If readonly restrict all other actions except for index and list
        // @todo: reverse check - enable readonly if all actions disabled
        if (!empty($options['readonly'])) {
            $this->options['disabledActions'] =
                array(self::ACTION_CREATE, self::ACTION_DELETE, self::ACTION_UPDATE);
        }

        $action = $this->getRequest()->getActionName();
        if (in_array($action, $this->options['disabledActions'])) {
            throw new Zend_Controller_Exception("'$action' action is disabled.");
        }

        // Do not override view script path if the action requested is not
        // one of the standard scaffolding actions
        $scaffActions   = array(self::ACTION_LIST, self::ACTION_INDEX,
                              self::ACTION_CREATE, self::ACTION_UPDATE,
                              self::ACTION_DELETE);
        $indexActionScript = null;
        if (!empty($this->options['useIndexAction'])) {
            $scaffActions[]     = $action;
            $indexActionScript  = 'index';
        }
        if (in_array($action, $scaffActions)) {
            $this->getHelper('ViewRenderer')
                 ->setViewScriptPathSpec(
                        sprintf('%s/' . ($indexActionScript ? $indexActionScript : ':action') . '.:suffix', $this->options['viewFolder']));
        }

        // Prepare view variables.
        $this->view->action       = $action;
        $this->view->module       = $this->getRequest()->getModuleName();
        $this->view->controller   = $this->getRequest()->getControllerName();
        $this->view->actionParams = $this->options['actionParams'];

        if (!$this->options['customMessenger']) {
            $this->view->messages   = $this->_helper->getHelper('FlashMessenger')->getMessages();
        }

        $this->view->entityTitle    = $this->options['entityTitle'] = $this->translate($this->options['entityTitle']);
        $this->view->createEntityText  = $this->options['createEntityText'];
        $this->view->updateEntityText  = $this->options['updateEntityText'];
        $this->view->deleteEntityText  = $this->options['deleteEntityText'];

        $this->view->headLink()->appendStylesheet($this->view->baseUrl('/css/zstyles.css'), 'screen, projection');
    }

    /**
     * Display the list of entries, as well as optional elements
     * like paginator, search form and sortable headers as specified
     * in field definition.
     */
    public function indexAction()
    {
        $fields         = array();
        $searchFields   = array();
        $sortingFields  = array();
        $defSortField   = null;
        $searchForm     = null;
        $searchActive   = false;

        $tableInfo      = $this->getMetadata();
        $pks            = $tableInfo['primary'];
        $tableRelations = array_keys($tableInfo['referenceMap']);
        $joinOn         = array();

        // Use all fields if no field settings were provided.
        if (!count($this->fields)) {
            $this->fields = array_combine($tableInfo['cols'], array_fill(0, count($tableInfo['cols']), array()));
        }
        // Add PK(s) to select query.
        else {
            foreach ($pks as $pk) {
                $fields[$tableInfo['name']][] = $pk;
            }
        }

        // Process primary/related table fields.
        $defaultOrder = 1;
        foreach ($this->fields as $columnName => $columnDetails) {
            $tableName      = $tableInfo['name'];
            $defColumnName  = $columnName;
            $this->fields[$columnName]['order'] = $defaultOrder++;

            /**
             * Check if the column belongs to a related table.
             */
            $fullColumnName = explode('.', $columnName);
            if (count($fullColumnName) == 2) {
                // Column is a FK.
                if (in_array($fullColumnName[0], $tableRelations)) {
                  $ruleDetails = $tableInfo['referenceMap'][$fullColumnName[0]];
                  // @todo: what if columns are an array?
                  $mainColumn = $ruleDetails['columns'];
                  $refColumn = is_array($ruleDetails['refColumns']) ?
                                array_shift($ruleDetails['refColumns']) : $ruleDetails['refColumns'];

                  $relatedModel         = new $ruleDetails['refTableClass']();
                  $relatedTableMetadata = $relatedModel->info();
                  $relatedTableName     = $relatedTableMetadata['name'];


                  $joinOn[$relatedTableName] = "$tableName.$mainColumn = $relatedTableName.$refColumn";

                  // Change current table and column to be used later.
                  // Aliases are used to evade same column names from joined tables.
                  $tableName  = $relatedTableName;
                  $columnName = array($defColumnName => $fullColumnName[1]);
                } else {
                    // Column is a FK for a dependent table
                    // so we can't show it, search or sort by it.
                    unset($this->fields[$columnName]);
                    continue;
                }
            }

            // Fetch fields respecting their order.
            // @todo: implement!
            $order = isset($this->fields[$defColumnName]['order']) ?
                     $this->fields[$defColumnName]['order'] : null;
            if ($order) {
                if (empty($fields[$tableName])) {
                    $fields[$tableName] = array();
                }

                if (!empty($fields[$tableName][$order])) {
                    $fields[$tableName][$order] = $columnName;
                } else {
                    $fields[$tableName][] = $columnName;
                }
            } else {
                $fields[$tableName][] = $columnName;
            }

            // Prepare search form fields.
            if (!empty($this->fields[$defColumnName]['searchable'])) {
                $searchFields[$defColumnName] = $columnDetails;
            }

            // Prepare sortable fields.
            if (!empty($this->fields[$defColumnName]['sortable'])) {
                $sortingFields[$tableName] = $columnName;
            }

            $this->fields[$defColumnName]['sqlName'] =
                "$tableName." . (is_array($columnName) ? current($columnName) : $columnName);

            $defSortField = empty($defSortField) ?
                            (empty($this->fields[$defColumnName]['sortBy']) ? null : $defColumnName)
                            : $defSortField;
        }

        if ($this->dbSource instanceof Zend_Db_Table_Abstract) {
            $select = $this->dbSource->select();
            $select->from($this->dbSource, $this->getFullColumnNames($tableInfo['name'], $fields));
        } else {
            $select = $this->dbSource;
            $select->from($this->dbSource->getTable(), $this->getFullColumnNames($tableInfo['name'], $fields));
        }

        if (count($joinOn)) {
            // Workaround required by Zend_Db_Table_Select.
            $select->setIntegrityCheck(false);
            foreach ($joinOn as $table => $joinCond) {
                $select->joinLeft($table, $joinCond, $this->getFullColumnNames($table, $fields));
            }
        }

        /**
         * Apply search filter, storing search criteria in session.
         */
        $searchActive = false;
        if (count($searchFields)) {
            // Create unique search session variable.
            // @todo: test if it is unique in ALL cases
            $nsName = $tableInfo['name'] . '_' . join('_', array_keys($searchFields));
            $searchParams   = new Zend_Session_Namespace($nsName);
            $searchForm     = $this->buildSearchForm($searchFields);

            if ($this->getRequest()->isPost() && $searchForm->isValid($this->getRequest()->getPost())) {
                if (isset($_POST['reset'])) {
                    $filterFields = array();
                } else {
                    $filterFields = $searchForm->getValues();
                }
                $searchParams->search   = $filterFields;
            } else {
                $filterFields = isset($searchParams->search) ? $searchParams->search : array();
            }
            $searchForm->populate($filterFields);

            foreach ($filterFields as $field => $value) {
              if ($value || is_numeric($value)) {
                // Search by date.
                // Date is a period, need to handle both start and end date.
                if (strpos($field, self::CSS_ID . '_from')) {
                    $field = str_replace('_' . self::CSS_ID . '_from', '', $field);
                    $select->where("{$tableInfo['name']}.$field >= ?", $value);
                } elseif (strpos($field, self::CSS_ID . '_to')) {
                    $field = str_replace('_' . self::CSS_ID . '_to', '', $field);
                    $select->where("{$tableInfo['name']}.$field <= ?", $value);
                } else {
                  // Search all other native fields.
                  if (isset($tableInfo['metadata'][$field])) {
                      $dataType = strtolower($tableInfo['metadata'][$field]['DATA_TYPE']);
                      $fieldType = isset($this->fields[$field]['type']) ? $this->fields[$field]['type'] : '';
                      $tableName = $tableInfo['name'];
                  } else {
                      // Search by related table field.
                      // Column name was normalized, need to find it.
                      $fieldDefs = array_keys($this->fields);
                      $fieldFound = false;
                      foreach ($fieldDefs as $fieldName) {
                          if (strpos($fieldName, '.') !== false && str_replace('.', '', $fieldName) == $field) {
                              $field = $fieldName;
                              $fieldFound = true;
                              break;
                          }
                      }

                      // The submitted form value is not from model, skip it.
                      if (!$fieldFound) {
                        continue;
                      }

                      $dataType = $this->fields[$field]['type'];
                      list($tableName, $field) = explode('.', $this->fields[$field]['sqlName']);
                  }

                  if (in_array($dataType, array('char', 'varchar', 'text')) || $fieldType == 'text') {
                      $select->where("$tableName.$field LIKE ?", $value);
                  } else {
                      $select->where("$tableName.$field = ?", $value);
                  }
                }

                $searchActive = true;
              }
          }
        }

        // Save criteria
        // @todo: this was used to allow additional filtering using existing select
        // $this->scaffSelectCriteria = clone $select;

        /**
         * Handle sorting by modifying SQL and building header sorting links.
         */
        $sortField  = $this->_getParam('orderby');
        $sortMode   = $this->_getParam('mode') == 'desc' ? 'desc' : 'asc';
        if (!$sortField && $defSortField) {
            $sortField  = $defSortField;
            $sortMode   = $this->fields[$sortField]['sortBy'] == 'desc' ? 'desc' : 'asc';
        }
        if ($sortField) {
            $select->order("{$this->fields[$sortField]['sqlName']} $sortMode");
        }

        // Sort fields for listing.
        $this->fields = array_filter($this->fields, array($this, 'removeHiddenListItems'));
        uasort($this->fields, array($this, 'sortByListOrder'));

        /**
         * Prepare table header.
         */
        $header = array();
        foreach ($this->fields as $columnName => $columnDetails) {
            if (!empty($columnDetails['hide']) && ($columnDetails['hide'] === true
                 || $columnDetails['hide'] == 'list')) {
                 continue;
            }

            $name = $this->translate($this->getColumnTitle($columnName));
            // Generate sorting link
            if (!empty($this->fields[$columnName]['sortable'])) {

                $currentMode = ($sortField == $columnName ? $sortMode : '');

                if ($currentMode == 'asc') {
                    $counterOrder   = 'desc';
                    $class          = self::CSS_ID . '-sort-desc';
                } elseif ($currentMode == 'desc') {
                    $counterOrder   = 'asc';
                    $class          = self::CSS_ID . '-sort-asc';
                } else {
                    $counterOrder   = 'asc';
                    $class          = '';
                }

                $sortParams = array(
                    'orderby'   => $columnName,
                    'mode'      => $counterOrder
                    );

                $href = $this->view->url($sortParams, 'default');
                $header[$columnName] = "<a class=\"" . self::CSS_ID . "-sort-link $class\" href=\"$href\">$name</a>";
            } else {
                $header[$columnName] = $name;
            }
        }

        // Enable paginator if needed
        if (isset($this->options['pagination'])) {
            $pageNumber = $this->_getParam('page');
            $paginator = Zend_Paginator::factory($select);

            $paginator->setCurrentPageNumber($pageNumber);
            $itemPerPage = isset($this->options['pagination']['itemsPerPage']) ?
                            $this->options['pagination']['itemsPerPage'] : 10;
            $paginator->setItemCountPerPage($itemPerPage);

            $items = $paginator->getItemsByPage($pageNumber);

            if ($items instanceof Zend_Db_Table_Rowset) {
                $items = $items->toArray();
            } elseif ($items instanceof ArrayIterator) {
                $items = $items->getArrayCopy();
            }

            $entries = $this->prepareList($items);
            $this->view->paginator = $paginator;
            $this->view->pageNumber = $pageNumber;
        } else {
            $entries = $this->prepareList($select->query()->fetchAll());
        }

        $this->view->headers        = $header;
        $this->view->entries        = $entries;
        $this->view->readonly       = $this->options['readonly'];
        $this->view->searchActive   = $searchActive;
        $this->view->searchForm     = $searchForm;
        $this->view->primaryKey     = $pks;

        $this->view->canCreate      = !in_array(self::ACTION_CREATE, $this->options['disabledActions']);
        $this->view->canUpdate      = !in_array(self::ACTION_UPDATE, $this->options['disabledActions']);
        $this->view->canDelete      = !in_array(self::ACTION_DELETE, $this->options['disabledActions']);
    }

    /**
     * Alias of index action.
     */
    public function listAction()
    {
        $this->_forward('index');
    }

    /**
     * Create entity handler.
     */
    public function createAction()
    {
        $info = $this->getMetadata();

        if (count($info['primary']) == 0) {
            throw new Zend_Controller_Exception('The model you provided does not have a primary key.');
        }

        $form = $this->buildEditForm();

        if ($this->getRequest()->isPost() && $form->isValid($this->_getAllParams())) {
            list($values, $relData) = $this->getDbValuesInsert($form->getValues());

            if ($this->beforeCreate($form, $values)) {

                try {
                    Zend_Db_Table::getDefaultAdapter()->beginTransaction();
                    $insertId = $this->dbSource->insert($values);
                    // Save many-to-many field to the corresponding table
                    if (count($relData)) {
                        foreach ($relData as $m2mData) {
                            $m2mTable   = $m2mData[0];
                            $m2mValues  = $m2mData[1];

                            if (count($m2mValues)) {
                                $m2mInfo    = $m2mTable->info();
                                $tableClass = get_class($this->dbSource);
                                foreach ($m2mInfo['referenceMap'] as $rule => $ruleDetails) {
                                    if ($ruleDetails['refTableClass'] == $tableClass) {
                                        $selfRef = $ruleDetails['columns'];
                                    } else {
                                        $relatedRef = $ruleDetails['columns'];
                                    }
                                }

                                foreach ($m2mValues as $v) {
                                    $m2mTable->insert(array($selfRef => $insertId, $relatedRef => $v));
                                }
                            }
                        }
                    }

                    Zend_Db_Table::getDefaultAdapter()->commit();

                    $this->_helper->FlashMessenger($this->getActionMessage(self::ACTION_CREATE, self::MSG_OK));

                    if ($this->afterCreate($form, $insertId)) {
                        if (isset($_POST[self::BUTTON_SAVE])) {
                            $redirect = "{$this->view->module}/{$this->view->controller}/index";
                        } elseif (isset($_POST[self::BUTTON_SAVEEDIT])) {
                            $redirect = "{$this->view->module}/{$this->view->controller}/update/id/$insertId";
                        } elseif (isset($_POST[self::BUTTON_SAVECREATE])) {
                            $redirect = "{$this->view->module}/{$this->view->controller}/create";
                        }

                        $this->_redirect($redirect);
                    }
                }
                catch (Zend_Db_Exception $e) {
                    Zend_Db_Table::getDefaultAdapter()->rollBack();
                    $this->_helper->FlashMessenger($this->getActionMessage(self::ACTION_CREATE, self::MSG_ERR));
                }
            }
        }

        $this->view->form           = $form;
        if (isset($this->options['editLayout'])) {
            $this->_helper->layout->setLayout($this->options['editLayout']);
        }
    }

    /**
     * Entity deletion handler.
     */
    public function deleteAction()
    {

        $params = $this->_getAllParams();
        $info = $this->getMetadata();

        if (count($info['primary']) == 0) {
            throw new Zend_Controller_Exception('The model you provided does not have a primary key, scaffolding is impossible!');
        }
        // Compound key support
        $primaryKey = array();
        foreach ($params AS $k => $v) {
            if (in_array($k, $info['primary'])) {
                $primaryKey["$k = ?"] = $v;
            }
        }

        try {
            $row = $this->dbSource->fetchAll($primaryKey);
            if ($row->count()) {
                $row = $row->current();
            } else {
                throw new Zend_Controller_Exception('Invalid request.');
            }

            $originalRow = clone $row;

            if ($this->beforeDelete($originalRow)) {
                $row->delete();
                $this->_helper->FlashMessenger($this->getActionMessage(self::ACTION_DELETE, self::MSG_OK));
                if ($this->afterDelete($originalRow)) {
                    $this->_redirect("{$this->view->module}/{$this->view->controller}/index");
                }
            }
            else {
                $this->_helper->FlashMessenger($this->getActionMessage(self::ACTION_DELETE, self::MSG_ERR));
                $this->_redirect("{$this->view->module}/{$this->view->controller}/index");
            }
        } catch (Zend_Db_Exception $e) {
            $this->_helper->FlashMessenger($this->getActionMessage(self::ACTION_DELETE, self::MSG_OK));
            $this->_redirect("{$this->view->module}/{$this->view->controller}/index");
        }
    }

    /**
     * Entity update handler.
     */
    public function updateAction()
    {
        $info = $this->getMetadata();

        if (count($info['primary']) == 0) {
            throw new Zend_Controller_Exception('The model you provided does not have a primary key.');
        }

        // Support compound keys
        $primaryKey = array();
        $params = $this->_getAllParams();
        foreach($params AS $k => $v) {
            if (in_array($k, $info['primary'])) {
                $primaryKey["$k = ?"] = $v;
            }
        }

        $entity = $this->dbSource->fetchAll($primaryKey);
        if ($entity->count() == 1) {
            $entity = $entity->current()->toArray();
        } else {
            throw new Zend_Controller_Exception('Invalid primary key specified.');
        }

        $form = $this->buildEditForm($entity);
        $populate = true;

        if ($this->getRequest()->isPost() && $form->isValid($params)) {
            $populate = false;
            $formValues = $form->getValues();
            $pkValue = $formValues[array_shift($info['primary'])];

            list($values, $where, $relData) = $this->getDbValuesUpdate($entity, $formValues);

            // Save common submitted fields
            if (!is_null($values) && !is_null($where)) {
                if ($this->beforeUpdate($form, $values)) {

                    try {
                        Zend_Db_Table::getDefaultAdapter()->beginTransaction();
                        $this->dbSource->update($values, $where);
                        // Save many-to-many field to the corresponding table
                        if (count($relData)) {
                            foreach ($relData as $m2mData) {
                                $m2mTable   = $m2mData[0];
                                $m2mValues  = is_array($m2mData[1]) ? $m2mData[1] : array();

                                $m2mInfo    = $m2mTable->info();
                                $tableClass = get_class($this->dbSource);
                                foreach ($m2mInfo['referenceMap'] as $rule => $ruleDetails) {
                                    if ($ruleDetails['refTableClass'] == $tableClass) {
                                        $selfRef = $ruleDetails['columns'];
                                    } else {
                                        $relatedRef = $ruleDetails['columns'];
                                    }
                                }

                                $m2mTable->delete("$selfRef = $pkValue");
                                foreach ($m2mValues as $v) {
                                    $m2mTable->insert(array($selfRef => $pkValue, $relatedRef => $v));
                                }
                            }
                        }

                        Zend_Db_Table::getDefaultAdapter()->commit();
                        $this->_helper->FlashMessenger($this->getActionMessage(self::ACTION_UPDATE, self::MSG_OK));

                        if ($this->afterUpdate($form)) {
                            $this->_redirect("{$this->view->module}/{$this->view->controller}/index");
                        }
                    } catch (Zend_Db_Exception $e) {
                        Zend_Db_Table::getDefaultAdapter()->rollBack();
                        $this->_helper->FlashMessenger($this->getActionMessage(self::ACTION_UPDATE, self::MSG_ERR));
                    }
                }
            }
        }

        if ($populate === true) {
            // Load common field values
            foreach ($entity as $field => $value) {
                // Apply field modifier if any
                if (isset($this->fields[$field]['loadModifier'])) {
                    if (function_exists($this->fields[$field]['loadModifier'])) {
                        $entity[$field] = call_user_func($this->fields[$field]['loadModifier'], $value);
                    } else {
                        $entity[$field] = $this->fields[$field]['loadModifier'];
                    }
                }
            }

            // Load many-to-many field values
            foreach ($this->fields as $field => $fieldDetails) {
                if (isset($fieldDetails['manyToManyTable'])) {
                    $m2mTable = $fieldDetails['manyToManyTable'];
                    $m2mInfo = $m2mTable->info();

                    $tableClass = get_class($this->dbSource);
                    foreach ($m2mInfo['referenceMap'] as $rule => $ruleDetails) {
                        if ($ruleDetails['refTableClass'] == $tableClass) {
                            $selfRef = $ruleDetails['columns'];
                        } else {
                            $relatedRef = $ruleDetails['columns'];
                        }
                    }

                    $m2mValues = $m2mTable->select()
                                          ->from($m2mTable, $relatedRef)
                                          ->where("$selfRef = ?", $primaryKey)
                                          ->query(Zend_Db::FETCH_ASSOC)->fetchAll();

                    $multiOptions = array();
                    foreach ($m2mValues as $_value) {
                        $multiOptions[] = $_value[$relatedRef];
                    }

                    // Column name must be normalized
                    // (Zend_Form_Element::filterName does it anyway).
                    $field = str_replace('.', '', $field);
                    $entity[$field] = $multiOptions;
                }
            }

            $form->setDefaults($entity);
        }

        $this->view->form = $form;
        if (isset($this->options['editLayout'])) {
            $this->_helper->layout->setLayout($this->options['editLayout']);
        }
    }

    private function getActionMessage($action, $msgType)
    {
        return sprintf($this->translate($this->messages[$action][$msgType]), $this->options['entityTitle']);
    }

    /**
     * Generates the create/update form based on table metadata
     * and field definitions provided at initialization.
     *
     * @param array $entityData currently editable entity data
     * @return Zend_Form
     */
    private function buildEditForm(array $entityData = array())
    {
        $info       = $this->getMetadata();
        $metadata   = $info['metadata'];
        $tableClass = get_class($this->dbSource);
        $action     = $this->getRequest()->getActionName();
        $form       = array();
        $rteFields  = $datePickerFields = array();
        $handledRefs  = array();

        // Look through native table columns.
        foreach ($metadata as $columnName => $columnDetails) {

            // Primary key is hidden by default.
            if (in_array($columnName, $info['primary']) && $this->options['pkEditable'] == false) {
                $form['elements'][$columnName] = array(
                    'hidden', array(
                        'value' => 0,
                    )
                );
                continue;
            }

            // Skip the field?
            if (!empty($this->fields[$columnName]['hide']) && ($this->fields[$columnName]['hide'] === true
                 || $this->fields[$columnName]['hide'] == 'edit')) {
                 continue;
            }

            // Is the field mandatory?
            if (isset($this->fields[$columnName]['required'])) {
                if (is_string($this->fields[$columnName]['required'])) {
                    if ($this->fields[$columnName]['required'] == self::ACTION_CREATE && $action != self::ACTION_CREATE) {
                        $required = false;
                    }
                } else {
                    $required = $this->fields[$columnName]['required'];
                }
            } else {
                $required = $columnDetails['NULLABLE'] == 1 ? false : true;
            }

            // Does it have a default value?
            if (!is_null($columnDetails['DEFAULT'])) {
                $defaultValue = $columnDetails['DEFAULT'];
            } else {
                $defaultValue = '';
            }

            // Specially handle the column if it is a foreign key
            // and build necessary select/multicheckbox field.
            if (!empty($this->fields[$columnName]['displayField'])) {
                list($refName, $displayField) = explode('.', $this->fields[$columnName]['displayField']);
                if (!empty($info['referenceMap'][$refName])) {
                    $ruleDetails = $info['referenceMap'][$refName];
                    $refColumn = is_array($ruleDetails['refColumns']) ?
                                    array_shift($ruleDetails['refColumns']) : $ruleDetails['refColumns'];

                    $options = array();
                    // Is value required?
                    if (!$required) {
                        $options[''] = '';
                    }

                    $relatedModel = new $ruleDetails['refTableClass']();
                    foreach ($relatedModel->fetchAll()->toArray() as $k => $v) {
                        $key = $v[$refColumn]; // obtain value of partner column
                        if (!isset($options[$key])) {
                            $options[$key] = $v[$displayField];
                        }
                    }

                    $form['elements'][$columnName] = array(
                        'select', array(
                            'multiOptions'  => $options,
                            'label'         => $this->getColumnTitle($columnName),
                            'description'   => $this->getColumnDescription($columnName),
                            'required'      => $required,
                            'value'         => $defaultValue,
                        )
                    );
                }
                else {
                    throw new Zend_Controller_Exception("No references are defined for '$displayField'.");
                }

                $handledRefs[] = $this->fields[$columnName]['displayField'];
                continue;
            }

            $elementOptions = array(
                'label'         => $this->getColumnTitle($columnName),
                'description'   => $this->getColumnDescription($columnName),
                'required'      => $required,
                'value'         => $defaultValue,
                'validators'    => isset($this->fields[$columnName]['validators'])
                                        ? $this->prepareValidators($columnName, $this->fields[$columnName]['validators'], $entityData)
                                        : array(),
                'filters'       => isset($this->fields[$columnName]['filters'])
                                        ? $this->fields[$columnName]['filters'] : array(),
            );

            // Build enum column as select or multicheckbox.
            $enumDefinition = null;
            if (isset($this->fields[$columnName]['options'])) {
                // Pseudo data type
                $dataType = 'options';
            } elseif (preg_match('/^enum/i', $columnDetails['DATA_TYPE'])) {
                $enumDefinition = $columnDetails['DATA_TYPE'];
                $dataType       = 'enum';
            } else {
                $dataType = strtolower($columnDetails['DATA_TYPE']);
            }

            $textFieldOptions   = array();
            $textFieldType      = null;

            if (isset($this->fields[$columnName]['type'])) {
                switch ($this->fields[$columnName]['type']) {
                    case 'textarea': case 'richtextarea':
                        $textFieldType  = 'textarea';
                        $rteFields[]    = $columnName;
                        break;

                    case 'text':
                        $textFieldType = 'text';
                        break;

                    case 'datepicker':
                        $datePickerFields[] = $columnName;
                        break;
                }

                if ($textFieldType == 'text') {
                    if (isset($this->fields[$columnName]['size'])) {
                        $textFieldOptions['size'] = $this->fields[$columnName]['size'];
                    }
                    if (isset($this->fields[$columnName]['maxlength'])) {
                        $textFieldOptions['maxlength'] = $this->fields[$columnName]['maxlength'];
                    } elseif (isset($metadata[$columnName]['LENGTH'])) {
                        $textFieldOptions['maxlength'] = $metadata[$columnName]['LENGTH'];
                    }
                } elseif ($textFieldType == 'textarea') {
                    if (isset($this->fields[$columnName]['cols'])) {
                        $textFieldOptions['cols'] = $this->fields[$columnName]['cols'];
                    }
                    if (isset($this->fields[$columnName]['rows'])) {
                        $textFieldOptions['rows'] = $this->fields[$columnName]['rows'];
                    }
                }
            }

            switch ($dataType) {
                // Build radio/select element from enum/options
                case 'enum': case 'options':
                    // Try to parse enum definition
                    if (isset($enumDefinition)) {
                        preg_match_all('/\'(.*?)\'/', $enumDefinition, $matches);

                        $options = array();
                        foreach ($matches[1] as $match) {
                            $options[$match] = ucfirst($match);
                        }
                    } else {
                        // Not enum - use options provided
                        $options = $this->fields[$columnName]['options'];
                    }

                    if (isset($this->fields[$columnName]['type']) && $this->fields[$columnName]['type'] == 'radio') {
                        $elementType = 'radio';
                    } else {
                        $elementType = 'select';
                    }

                    $form['elements'][$columnName] = array(
                        $elementType,
                        array_merge(array('multiOptions'  => $options), $elementOptions)
                    );

                    break;

                // Generate fields for numerics.
                case 'tinyint':
                case 'bool':
                case 'smallint':
                case 'int':
                case 'integer':
                case 'mediumint':
                case 'bigint':

                    if (isset($this->fields[$columnName]['type'])
                            && $this->fields[$columnName]['type'] == 'checkbox') {
                        $form['elements'][$columnName] = array(
                            'checkbox',
                            $elementOptions
                        );
                    } else {
                        $form['elements'][$columnName] = array(
                            'text',
                            array_merge(array('size' => 10), $elementOptions)
                        );
                    }
                    break;

                case 'decimal':
                case 'float':
                case 'double':
                    $form['elements'][$columnName] = array(
                        'text',
                        $elementOptions
                    );
                    break;

                // Generate single-line input or multiline input for string fields.
                case 'char':
                case 'varchar':
                case 'smalltext':
                    $form['elements'][$columnName] = array(
                        $textFieldType ? $textFieldType : 'text',
                        array_merge($elementOptions, $textFieldOptions)
                    );
                    break;

                case 'text':
                case 'mediumtext':
                case 'longtext':
                    $form['elements'][$columnName] = array(
                        $textFieldType ? $textFieldType : 'textarea',
                        array_merge($elementOptions, $textFieldOptions)
                    );
                    break;

                // Date/time fields.
                case 'date':
                case 'time':
                case 'datetime':
                case 'timestamp':
                    $form['elements'][$columnName] = array(
                        'text',
                        $elementOptions
                    );
                    break;

                default:
                    throw new Zend_Controller_Exception("Unsupported data type '$dataType' encountered, scaffolding is not possible.");
                    break;
            }

            // Save custom attributes
            if (isset($this->fields[$columnName]['attribs'])
                    && is_array($this->fields[$columnName]['attribs'])) {
                $form['elements'][$columnName][1] = array_merge($form['elements'][$columnName][1], $this->fields[$columnName]['attribs']);
            }
        }

        /**
         * Look for additional field definitions (not from current model).
         */
        foreach ($this->fields as $columnName => $columnDetails) {

            if (in_array($columnName, $handledRefs)) {
                continue;
            }

            $fullColumnName = explode('.', $columnName);
            if (count($fullColumnName) == 2) {
                $refName = $fullColumnName[0];
                $refDisplayField = $fullColumnName[1];
                foreach ($info['dependentTables'] as $depTableClass)  {
                    $dependentTable = new $depTableClass;
                    if (!$dependentTable instanceof Zend_Db_Table_Abstract) {
                        throw new Zend_Controller_Exception('Zend_Controller_Scaffolding requires a Zend_Db_Table_Abstract as model providing class.');
                    }

                    $references = $dependentTable->info(Zend_Db_Table::REFERENCE_MAP);
                    // Reference with such name may not be defined...
                    if (!isset($references[$refName])) {
                        continue;
                    }
                    else {
                        $reference = $references[$refName];
                    }

                    $optionsTable = new $reference['refTableClass'];
                    // Auto-detect PK based on metadata
                    if (!isset($reference['refColumns'])) {
                        $optionsTableInfo = $optionsTable->info();
                        $reference['refColumns'] = array_shift($optionsTableInfo['primary']);
                    }

                    // Value required?
                    $required = isset($columnDetails['required']) && $columnDetails['required'] ? true : false;

                    $options = array();
                    foreach($optionsTable->fetchAll()->toArray() AS $k => $v) {
                        $key = $v[$reference['refColumns']];
                        if (!isset($options[$key])) {
                            $options[$key] = $v[$refDisplayField];
                        }
                    }

                    if (isset($columnDetails['type']) && $columnDetails['type'] == 'multicheckbox') {
                        $elementType = 'MultiCheckbox';
                    } else {
                        $elementType = 'Multiselect';
                    }

                    // Column name must be normalized
                    // (Zend_Form_Element::filterName does it anyway).
                    $formColumnName = str_replace('.', '', $columnName);
                    $form['elements'][$formColumnName] = array(
                        $elementType, array(
                            'multiOptions' => $options,
                            'label' => $this->getColumnTitle($columnName),
                            'description'   => $this->getColumnDescription($columnName),
                            'required'  => $required,
                            'validators'    => isset($this->fields[$columnName]['validators']) ?
                                               $this->prepareValidators($columnName, $this->fields[$columnName]['validators'], $entityData)
                                               : array(),
                        )
                    );

                    // Save manyToMany table information.
                    $this->fields[$columnName]['manyToManyTable'] = $dependentTable;
                    break;
                }
            }

            // Save custom attributes
            // @todo: why here?
            if (isset($this->fields[$columnName]['attribs'])
                    && is_array($this->fields[$columnName]['attribs'])) {
                $form['elements'][$columnName][1] = array_merge($form['elements'][$columnName][1], $this->fields[$columnName]['attribs']);
            }
        }

        // Cross Site Request Forgery protection
        if ($this->options['csrfProtected']) {
            $form['elements']['csrf_hash'] = array('hash', array('salt' => 'sea_salt_helps'));
        }

        // Generate create form buttons
        if ($action == self::ACTION_CREATE) {
            foreach ($this->options['editFormButtons'] as $btnId) {
                $form['elements'][$btnId] = array(
                    'submit',
                    array(
                        'label' => $this->buttonLabels[$btnId],
                        'class' => self::CSS_ID . '-' . $btnId
                    ),
                );
            }
        } else {
            $form['elements'][self::BUTTON_SAVE] = array(
                'submit',
                array(
                    'label' => $this->buttonLabels[ self::BUTTON_SAVE],
                    'class' => self::CSS_ID . '-' . self::BUTTON_SAVE
                ),
            );
        }

        $form['action'] = $this->view->url();

        // Enable rich text editor for necessary fields
        if (count($rteFields)) {
            $this->loadRichTextEditor($rteFields);
        }

        // Enable date picker
        if (count($datePickerFields)) {
            $this->loadDatePicker($datePickerFields);
        }

        // Additionally process form
        return $this->prepareEditForm($form);
    }

    /**
     * Initializes entity search form.
     * @param array $fields list of searchable fields.
     * @return Zend_Form instance of form object
     */
    private function buildSearchForm(array $fields)
    {
        $info               = $this->getMetadata();
        $metadata           = $info['metadata'];
        $tableRelations     = array_keys($info['referenceMap']);

        $datePickerFields   = array();
        $form               = array();

        foreach ($fields as $columnName => $columnDetails) {
            $defColumnName = $columnName;
            if (isset($metadata[$columnName])) {
                $dataType = strtolower($metadata[$columnName]['DATA_TYPE']);
                $fieldType = isset($columnDetails['type']) ? $columnDetails['type'] : '';
            } else {
                /**
                 * Check if the column belongs to a related table.
                 * @todo: support for n-n relations.
                 */
                $fullColumnName = explode('.', $columnName);
                if (count($fullColumnName) == 2) {
                    // Column is a FK.
                    if (in_array($fullColumnName[0], $tableRelations)) {
                        $ruleDetails = $info['referenceMap'][$fullColumnName[0]];
                        // @todo: what if columns are an array?
                        $refColumn = is_array($ruleDetails['refColumns']) ?
                                      array_shift($ruleDetails['refColumns']) : $ruleDetails['refColumns'];

                        $relatedModel         = new $ruleDetails['refTableClass'];
                        $relatedTableInfo = $relatedModel->info();
                        $relatedTableMetadata = $relatedTableInfo['metadata'];

                        $dataType = strtolower($relatedTableMetadata[$fullColumnName[1]]['DATA_TYPE']);
                        $fieldType = isset($columnDetails['type']) ? $columnDetails['type'] : '';

                        // Save data type for further usage.
                        $this->fields[$columnName]['type'] = $dataType;
                    }

                    // Column name must be normalized
                    // (Zend_Form_Element::filterName does it anyway).
                    $columnName = str_replace('.', '', $columnName);
                }
                // @todo: remove the code below if not needed
//                $dataType = '';
//                if (in_array($columnDetails['type'], array('date', 'datepicker', 'datetime'))) {
//                    $fieldType = 'date';
//                } elseif ($columnDetails['type'] == 'text') {
//                    $fieldType = 'text';
//                } else {
//                    throw new Zend_Controller_Exception("Fields of type '{$columnDetails['type']}' are not searchable.");
//                }
            }

            $matches = array();
            $set = false;
            if (isset($metadata[$columnName]) && preg_match('/^enum/i', $metadata[$columnName]['DATA_TYPE'])
                    || (isset($columnDetails['searchOptions'])
                            && is_array($columnDetails['searchOptions']) && $set = true)) {
                $options = array();
                // Try to use the specified options
                if ($set) {
                    $options = $columnDetails['searchOptions'];
                }
                // or extract options from enum
                elseif (preg_match_all('/\'(.*?)\'/', $metadata[$columnName]['DATA_TYPE'], $matches)) {
                    foreach ($matches[1] as $match) {
                        $options[$match] = $match;
                    }
                }
                $options[''] = 'any';
                ksort($options);

                if (isset($columnDetails['type']) && $columnDetails['type'] == 'radio') {
                    $elementType = 'radio';
                } else {
                    $elementType = 'select';
                }

                $form['elements'][$columnName] = array(
                    $elementType,
                    array(
                        'multiOptions' => $options,
                        'label' => $this->getColumnTitle($defColumnName),
                        'class' => self::CSS_ID . '-search-' . $elementType,
                        'value' => ''
                    )
                );
            } elseif (in_array($dataType, array('date', 'datetime', 'timestamp')) || $fieldType == 'date') {
                $form['elements'][$columnName . '_' . self::CSS_ID . '_from'] =
                    array(
                        'text', array(
                            'label'         => $this->getColumnTitle($defColumnName) . ' from',
                            'class'         => self::CSS_ID . '-search-' . $dataType . $fieldType,
                        )
                    );

                $form['elements'][$columnName . '_' . self::CSS_ID . '_to'] =
                    array(
                        'text', array(
                            'label' => 'to',
                            'class' => self::CSS_ID . '-search-' . $dataType . $fieldType,
                        )
                    );

                $datePickerFields[] = $columnName . '_' . self::CSS_ID . '_from';
                $datePickerFields[] = $columnName . '_' . self::CSS_ID . '_to';
            } elseif (in_array($dataType, array('char', 'varchar')) || $fieldType == 'text') {
                    $length     = isset($columnDetails['size']) ? $columnDetails['size'] : '';
                    $maxlength  = isset($columnDetails['maxlength']) ? $columnDetails['maxlength'] :
                                      isset($metadata[$columnName]['LENGTH'])
                                          ? $metadata[$columnName]['LENGTH'] : '';

                    $form['elements'][$columnName] = array(
                        'text',
                        array(
                            'class'     => self::CSS_ID . '-search-text',
                            'label'     => $this->getColumnTitle($defColumnName),
                            'size'      => $length,
                            'maxlength' => $maxlength,
                        )
                    );
            } elseif (in_array($dataType, array('tinyint', 'int', 'integer', 'bool'))) {
                // Specially handle the column if it is a foreign key
                // and build necessary select/multicheckbox field.
                if (!empty($this->fields[$columnName]['displayField'])) {
                    list($refName, $displayField) = explode('.', $this->fields[$columnName]['displayField']);
                    if (!empty($info['referenceMap'][$refName])) {
                        $ruleDetails = $info['referenceMap'][$refName];
                        $refColumn = is_array($ruleDetails['refColumns']) ?
                                        array_shift($ruleDetails['refColumns']) : $ruleDetails['refColumns'];

                        $options = array();
                        $options[''] = '';

                        $relatedModel = new $ruleDetails['refTableClass']();
                        foreach ($relatedModel->fetchAll()->toArray() as $k => $v) {
                            $key = $v[$refColumn]; // obtain value of partner column
                            if (!isset($options[$key])) {
                                $options[$key] = $v[$displayField];
                            }
                        }

                        $form['elements'][$columnName] = array(
                            'select', array(
                                'multiOptions'  => $options,
                                'label'         => $this->getColumnTitle($columnName),
                                'class'         => self::CSS_ID . '-search-select',
                            )
                        );
                    }
                    else {
                        throw new Zend_Controller_Exception("No references are defined for '$displayField'.");
                    }
                }
                else {
                    $form['elements'][$columnName] = array(
                            'checkbox',
                            array(
                                'class' => self::CSS_ID . '-search-radio',
                                'label' => $this->getColumnTitle($columnName),
                            )
                        );
                }
            } else {
                throw new Zend_Controller_Exception("Fields of type $dataType are not searchable.");
            }

            // Allow to search empty records
            if (isset($this->fields[$columnName]['searchEmpty'])) {
                $form['elements']["{$columnName}searchempty"] = array(
                        'checkbox',
                        array(
                            'class' => self::CSS_ID . '-search-radio',
                            'label' => $this->getColumnTitle($columnName) . _(' is empty'),
                        )
                    );
            }
        }

        $form['elements']['submit'] = array(
            'submit',
            array(
                'ignore'   => true,
                'class' => self::CSS_ID . '-btn-search',
                'label' => 'Search',
            )
        );

        $form['elements']['reset'] = array(
            'submit',
            array(
                'ignore'   => true,
                'class' => self::CSS_ID . '-btn-reset',
                'label' => 'Reset',
                'onclick' => 'ssfResetForm(this.form);'
            ),
        );

        // Load JS files
        if (count($datePickerFields)) {
            $this->loadDatePicker($datePickerFields);
        }
        $this->view->headScript()->appendFile($this->view->baseUrl("/js/zsutils.js"));

        $form['action'] = $this->view->url();

        return $this->prepareSearchForm($form);
    }

    /**
     * Filters form values making them ready to be used by Zend_Db_Table_Abstract.
     *
     * @param Array $values form values
     * @return Array $values filtered values
     */
    private function getDbValues(array $values)
    {
        if (count($values) > 0) {
            if (isset($values['csrf_hash'])) {
                unset($values['csrf_hash']);
            }
            unset($values['submit']);
        }

        return $values;
    }

    /**
     * Prepare form values for insertion. Applies field save modifiers
     * and handles many-to-many synthetic fields.
     *
     * @param Array $values initial values
     * @return Array $values modified values
     */
    private function getDbValuesInsert(array $values)
    {
        $values = $this->getDbValues($values);
        $relData= array();

        if (count($values) > 0) {
            $info = $this->getMetadata();
            if (!$this->options['pkEditable']) {
                foreach ($info['primary'] AS $primaryKey) {
                    unset($values[$primaryKey]);
                }
            }
        }

        foreach ($values AS $field => $value) {
            $originalField = $field;
            // Many-to-many field has to be saved into another table
            // Column name was normalized, need to find it.
            $fields = array_keys($this->fields);
            foreach ($fields as $fieldName) {
                if (strpos($fieldName, '.') !== false && str_replace('.', '', $fieldName) == $field) {
                    $field = $fieldName;
                    break;
                }
            }

            if (isset($this->fields[$field]['manyToManyTable'])) {
                // Many-to-many field has to be saved into another table.
                $relData[] = array($this->fields[$field]['manyToManyTable'], $value);
                unset($values[$originalField]);
            } else {
                // Apply field modifier if any
                if (isset($this->fields[$field]['saveModifier'])) {
                    $values[$field] = call_user_func($this->fields[$field]['saveModifier'], $value);
                }
            }
        }

        return array($values, $relData);
    }

    /**
     * Prepare form values for update. Applies field save modifiers
     * and handles many-to-many synthetic fields.
     *
     * @param Array $entity original values (before update)
     * @param Array $values new values
     * @return Array modified values in form array($values => Array, $where => String)
     */
    private function getDbValuesUpdate(array $entity, array $values)
    {
        $values = $this->getDbValues($values);
        $info   = $this->getMetadata();
        $where  = array();
        $update = array();
        $relData= array();

        foreach ($values AS $field => $value) {
            // PK must be used in where clause.
            if (in_array($field, $info['primary'])) {
                $where[] = $this->dbSource->getAdapter()->quoteInto("$field = ?", $entity[$field]);
            }

            // Original table column.
            if (in_array($field, $info['cols'])) {
                // Normal table field has to be directly saved
                if (!(isset($this->fields[$field]['required']) && $this->fields[$field]['required'] == 'onCreate' && empty($value)))
                    // Apply field modifier if any
                    if (isset($this->fields[$field]['saveModifier'])) {
                        $update[$field] = call_user_func($this->fields[$field]['saveModifier'], $value);
                    } else {
                        $update[$field] = $value;
                    }
            } else {
                // Column name was normalized, need to find it.
                $fields = array_keys($this->fields);
                foreach ($fields as $fieldName) {
                    if (strpos($fieldName, '.') !== false && str_replace('.', '', $fieldName) == $field) {
                        $field = $fieldName;
                        break;
                    }
                }
                if (isset($this->fields[$field]['manyToManyTable'])) {
                    // Many-to-many field has to be saved into another table.
                    $relData[] = array($this->fields[$field]['manyToManyTable'], $value);
                }
            }
        }

        if (count($where) > 0) {
            $where = implode(" AND ", $where);
            return array($update, $where, $relData);
        } else {
            return array(null, null, null);
        }
    }

    /**
     * Prepares the list of records. Optionally applies field listing modifiers.
     *
     * @param Array $entries entries to be displayed
     * @return Array $list resulting list of entries
     */
    private function prepareList(array $entries)
    {
        $info = $this->getMetadata();
        $list = array();

        foreach ($entries as $entry) {
            $keys = array();

            // Convert to array if object.
            if (is_object($entry)) {
                $entry = (array)$entry;
            }

            // Fetch PK(s).
            foreach ($info['primary'] as $pk) {
                $keys[$pk] = $entry[$pk];
            }

            foreach ($this->fields as $columnName => $columnDetails) {

                if (!empty($columnDetails['hide']) && ($columnDetails['hide'] === true
                     || $columnDetails['hide'] == 'list')) {
                     continue;
                }

                list($table, $column) = explode('.', $columnDetails['sqlName']);
                // If alias exist or column not found by its SQL primary name, let's try alias.
                if (strpos($columnName, '.') || empty($entry[$column])) {
                    $column = $columnName;
                }
                $value  = $entry[$column];

                // Call list view modifier for specific column if set
                if (isset($columnDetails['listModifier'])) {
                    $value = call_user_func($columnDetails['listModifier'], $value);
                }

                if (!empty($columnDetails['translate'])) {
                    $value = $this->view->translate($value);
                }

                $row[$columnName] = $value;
            }

            $row['pkParams'] = $keys;
            $list[] = $row;
        }

        return $list;
    }

    /**
     * Retrieve model table metadata.
     * @return Array
     */
    private function getMetadata()
    {
        if (is_null($this->metaData)) {
            if ($this->dbSource instanceof Zend_Db_Table_Abstract) {
                $this->metaData = $this->dbSource->info();
            } elseif ($this->dbSource instanceof Zend_Db_Table_Select) {
                $this->metaData = $this->dbSource->getTable()->info();
            }
        }

        return $this->metaData;
    }

    /**
     * Get fully qualified (table.column) colunm names.
     * @param String $table
     * @param Array $fields
     * @return Array
     */
    private function getFullColumnNames($table, $fields) {
        $result = array();
        foreach ($fields[$table] as $field) {
            if (is_array($field)) {
                $fieldName = current($field);
                $alias = array_search($fieldName, $field);
                $field = $fieldName;
                $result[] = "$table.$field AS $alias";
            }
            else {
              $result[] = "$table.$field";
            }
        }
        return $result;
    }

    /**
     * Looks if there is a custom defined name for the column for displaying
     * @param String $columnFieldName
     * @return String $columnLabel
     */
    private function getColumnTitle($columnName)
    {
        if (isset($this->fields[$columnName]['title'])) {
            return $this->fields[$columnName]['title'];
        } else {
            return ucfirst($columnName);
        }
    }

    /**
     * Looks if there is a custom defined name for the column for displaying
     * @param String $columnFieldName
     * @return String $columnLabel
     */
    private function getColumnDescription($columnName)
    {
        if (isset($this->fields[$columnName]['description'])) {
            return $this->fields[$columnName]['description'];
        }
        return null;
    }

    /**
     * Additionally handles validators (adds/removes options if needed).
     *
     * @param String $field database field name
     * @param array $validators list of custom validators
     * @param array $dbRecord entity record
     */
    private function prepareValidators($field, $validators, $dbRecord)
    {
        if (is_array($validators)) {
            foreach ($validators as $i => &$validator) {
                // Validation options provided
                if (is_array($validator)) {
                    // Add exclusion when validating existing value
                    if ($validator[0] == 'Db_NoRecordExists') {
                        if ($this->getRequest()->getActionName() == self::ACTION_UPDATE) {
                            $validator[2]['exclude'] = array('field' => $field, 'value' => $dbRecord[$field]);
                        }
                    }
                }
            }
        } else {
            $validators = array();
        }

        return $validators;
    }

    /**
     * Builds the edition form object. Use this method to apply custom logic like decorators etc.
     *
     * @param array $form form configuration array
     * @return Zend_Form instance of Zend_Form
     */
    protected function prepareEditForm(array &$form)
    {
        $formObject = new Zend_Form($form);

        // Add required flag
        foreach ($formObject->getElements() as $element) {
            $label = $element->getDecorator('Label');
            if (is_object($label)) {
                $label->setOption('requiredSuffix', ' *');
            }

            // Override default form decorator for certain elements that cause spaces
            if ($element instanceof Zend_Form_Element_Button || $element instanceof Zend_Form_Element_Submit
                    || $element instanceof Zend_Form_Element_Hash || $element instanceof Zend_Form_Element_Hidden) {
                $element->setDecorators(array('ViewHelper'));
            }
        }

        $formObject->setAttrib('class', self::CSS_ID . '-edit-form');

        return $formObject;
    }

    /**
     * Builds the search form object. Use this method to apply custom logic like decorators etc.
     *
     * @param array $form form configuration array
     * @return Zend_Form instance of Zend_Form
     */
    protected function prepareSearchForm(array &$form)
    {
        $formObject = new Zend_Form($form);

        foreach ($formObject->getElements() as $element) {
            // Override default form decorator for certain elements that cause spaces
            if ($element instanceof Zend_Form_Element_Button || $element instanceof Zend_Form_Element_Submit
                    || $element instanceof Zend_Form_Element_Hash || $element instanceof Zend_Form_Element_Hidden) {
                $element->setDecorators(array('ViewHelper'));
            }
        }

        $formObject->setAttrib('class', self::CSS_ID . '-search-form');
        return $formObject;
    }

    /**
     * Allows to initialize a JavaScript date picker.
     * Typically you should include here necessary JS files.
     *
     * @param array $fields fields that use date picking
     */
    protected function loadDatePicker(array $fields)
    {
    }

    /**
     * Allows to initialize a JavaScript rich text editor.
     * Typically you should include here necessary JS files.
     *
     * @param array $fields fields that use rich text editor
     */
    protected function loadRichTextEditor(array $fields)
    {
    }

    /**
     * The function called every time BEFORE entity is created.
     *
     * @param Zend_Form $form submitted form object
     * @return true if creation must happen or false otherwise
     */
    protected function beforeCreate(Zend_Form $form, array &$formValues)
    {
        return true;
    }

    /**
     * The function called every time AFTER entity has been created.
     *
     * @param Zend_Form $form submitted form object
     * @param int $insertId just inserted entity's id
     * @return true if automatic redirect must happen and false if user will
     *          redirect manually
     */
    protected function afterCreate(Zend_Form $form, $insertId)
    {
        return true;
    }

    /**
     * The function called every time BEFORE entity is updated.
     *
     * @param Zend_Form $form submitted form object
     * @param array $formValues values as returned by getDbValuesUpdate method
     * @return true if update must happen or false otherwise
     */
    protected function beforeUpdate(Zend_Form $form, array &$formValues)
    {
        return true;
    }

    /**
     * The function called every time AFTER entity has been updated.
     *
     * @param Zend_Form $form submitted form object
     * @return true if automatic redirect must happen and false if user will
     *          redirect manually
     */
    protected function afterUpdate(Zend_Form $form)
    {
        return true;
    }

    /**
     * The function called every time BEFORE entity is deleted.
     *
     * @param Zend_Db_Table_Row_Abstract $entity record to be deleted
     * @return true if deletion must happen or false otherwise
     */
    protected function beforeDelete(Zend_Db_Table_Row_Abstract $entity)
    {
        return true;
    }

    /**
     * The function called every time AFTER entity has been deleted.
     *
     * @param Zend_Db_Table_Row_Abstract $entity the deleted record
     * @return true if automatic redirect must happen and false if user will
     *          redirect manually
     */
    protected function afterDelete(Zend_Db_Table_Row_Abstract $entity)
    {
        return true;
    }

    /**
     * Translates a string using a translator, or returns original if none was defined.
     * @param string $string original string
     * @return string translated string
     */
    private function translate($string) {
        if (isset($this->options['translator'])) {
            return $this->options['translator']->translate($string);
        }

        return $string;
    }

    /**
     * Sorts fields for listing.
     */
    function sortByListOrder($a, $b) {
        if (!isset($a['listOrder'])) {
            $a['listOrder'] = $a['order'];
        }

        if (!isset($b['listOrder'])) {
            $b['listOrder'] = $b['order'];
        }

        return $a['listOrder'] - $b['listOrder'];
    }

    /**
     * Sorts fields for listing.
     */
    function sortByEditOrder($a, $b) {
        if (!isset($a['editOrder'])) {
            $a['editOrder'] = $a['order'];
        }

        if (!isset($b['editOrder'])) {
            $b['editOrder'] = $b['order'];
        }

        return $a['editOrder'] - $b['editOrder'];
    }

    /**
     * Removes elements that must be skipped from listing.
     */
    function removeHiddenListItems($value) {
        if (!empty($value['hide']) && ($value['hide'] === true || $value['hide'] == 'list')) {
            return false;
        }
        return true;
    }
}

?>