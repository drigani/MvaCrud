<?php

namespace MvaCrud\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class CrudIndexController extends AbstractActionController
{
    
    protected   $I_service;
    protected   $I_form;
    private     $s_entityName;
    
    protected $s_indexTitle;
    protected $s_indexTemplate;
    protected $s_newTitle;
    protected $s_newTemplate;
    protected $s_editTitle;
    protected $s_editTemplate;
    
    protected $s_processErrorTitle;
    protected $s_processErrorTemplate;

    protected $s_processRouteRedirect;
    protected $s_deleteRouteRedirect;
    
    private $as_config;
    
    public function __construct($s_entityName, $I_service, $I_form, $as_config) {
        $this->s_entityName = $s_entityName;
        $this->I_service = $I_service;
        $this->I_form = $I_form;
        
        $this->as_config = $as_config;
        
        $as_namespace = explode('\\',get_class($this));
        $s_namespace = $as_namespace[0];
        
        // IndexAction
        $this->s_indexTitle = $this->getDefaultValue('s_indexTitle',$s_namespace);
        $this->s_indexTemplate = $this->getDefaultValue('s_indexTemplate',$s_namespace);
        
        // NewAction
        $this->s_newTitle = $this->getDefaultValue('s_newTitle',$s_namespace);
        $this->s_newTemplate = $this->getDefaultValue('s_newTemplate',$s_namespace);
        
        // EditAction
        $this->s_editTitle = $this->getDefaultValue('s_editTitle',$s_namespace);
        $this->s_editTemplate = $this->getDefaultValue('s_editTemplate',$s_namespace);
        
        // DeleteAction
        // Alert on delete true/false
        // Confirm on delete true/false
            // Confirm title
            // Confirm template

        // Process Delete Action
        // Redirect on delete true false
            // Redirect route
        // Success page
        // Success template
        
        // Process New, Edit Action
            // Error page
            $this->s_processErrorTitle = 'Some errors during entity editing';
            $this->s_processErrorTemplate  = 'crud/index/default-form';
        
            // Success page
            // Redirect on success true/false
                // Redirect route
            // Success title
            // Success template
        
        $this->s_processRouteRedirect   = 'crud';
        $this->s_deleteRouteRedirect    = 'crud';
        
    }
    
    public function indexAction(){
        $I_view = new ViewModel(array(
            's_title' => $this->s_indexTitle,
            'aI_entities' => $this->I_service->getAllEntities(),
            'as_messages' => $this->flashMessenger()->setNamespace($this->s_entityName)->getMessages(),
        ));
        $I_view->setTemplate($this->s_indexTemplate);
        return $I_view;
    }
    
    public function newAction(){
        $I_view = new ViewModel(array('form' => $this->I_form, 'title' => $this->s_newTitle));
        $I_view->setTemplate($this->s_newTemplate);
        return $I_view;
    }
    
    public function editAction($I_entity=null){
        if ( null == $I_entity ){
            $I_entity = $this->getEntityFromQuerystring();
        }
                
        // bind entity values to form
        $this->I_form->bind($I_entity);
        
        $I_view = new ViewModel(array('form' => $this->I_form, 'title' => $this->s_editTitle));
        $I_view->setTemplate($this->s_editTemplate);
        return $I_view;
    }
    
    public function deleteAction(){
        $I_entity = $this->getEntityFromQuerystring();
        $this->I_service->deleteEntity($I_entity);
        return $this->crudRedirect('delete');
    }
    
    public function processAction(){
        if ($this->request->isPost()) {
            // get post data
            $as_post = $this->request->getPost()->toArray();
            // fill form
            $this->I_form->setData($as_post);
            // check if form is valid
            if(!$this->I_form->isValid()) {
                // prepare view
                $I_view = new ViewModel(array('form'  => $this->I_form,
                                               'title' => $this->s_processErrorTitle));
                $I_view->setTemplate($this->s_processErrorForm);
                return $I_view;
            }
    
            // use service to save data
            $I_entity = $this->I_service->upsertEntityFromArray($as_post);
    
            if ( $as_post['id'] > 0 ) {
                $this->flashMessenger()->setNamespace($this->s_entityName)->addMessage($this->s_entityName . $I_entity->getName() . ' updated successfully');
            } else {
                $this->flashMessenger()->setNamespace($this->s_entityName)->addMessage($this->s_entityName . $I_entity->getName() . ' inserted successfully');
            }
            
            return $this->crudRedirect('process');
        }
        $this->getResponse()->setStatusCode(404);
        return;
    }
    
    
    /*
     * Private methods
     */
    private function getEntityFromQuerystring() {
        $i_id = (int)$this->params('id');
        
        if (empty($i_id) || $i_id <= 0){
            $this->getResponse()->setStatusCode(404);    //@todo there is a better way?
                                                         // Probably triggering Not Found Event SM
                                                         // Zend\Mvc\Application: dispatch.error 
            return;
        }
        $I_entity = $this->I_service->getEntity($i_id);
        return $I_entity;
    }
    
    private function crudRedirect($s_action){
        $s_currentRoute =  $this->getEvent()->getRouteMatch()->getMatchedRouteName();
        $as_routeParams =  $this->getEvent()->getRouteMatch()->getParams();
        $controller = $as_routeParams['controller'];

        switch ($s_action){
            case 'process':
                if ($this->s_processRouteRedirect != 'crud') {
                    return $this->redirect()->toRoute($this->s_processRouteRedirect);
                }
                else {
                    return $this->redirect()->toRoute($s_currentRoute,array('controller'=>$controller,'action'=>'index'));
                }
                break;
            case 'delete':
                if ($this->s_deleteRouteRedirect != 'crud') {
                    return $this->redirect()->toRoute($this->s_deleteRouteRedirect);
                }
                else {
                    return $this->redirect()->toRoute($s_currentRoute,array('controller'=>$controller,'action'=>'index'));
                }
                break;
            default:
                throw new Exception('Invalid redirect action');
                
        }
    }
    
    /**
     * 
     * Legge il valore di default dei parametri di configurazione del modulo.
     * I valori possono essere definiti con la chiave 'MvaCrud' e valgono globalmente
     * oppure con la sottochiave __NAMESPACE__ e valgono per le entità di quel namespace
     * Nel module.config.php posso definire
     * 'MvaCrud' => array() // configurazione globale
     * oppure
     * 'MvaCrud' => array( __NAMESPACE__ => array()) // configurazione locale al namespace del modulo
     * oppure
     * definire il valore delle variabili direttamente a livello di controller nel modulo concreto che estende questo
     * dopo aver chiamato il costruttore
     * 
     * @param string $s_variableName
     * @param string $s_namespace
     * @return type
     */
    private function getDefaultValue($s_variableName,$s_namespace){
        if (isset($this->as_config[$s_namespace][$s_variableName])) {
            return $this->$s_variableName = $this->as_config[$s_namespace][$s_variableName];
        }  else {
            return $this->$s_variableName = $this->as_config[$s_variableName];
        }
    }
    
    
}