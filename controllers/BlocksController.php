<?php
/**
 * Blocks Controller
 * The blocks controller responsible for rendering both "static" and "dynamic" block content.
 * While the block helper could be used to render view templates from the /views folder, or any
 * folder underneath...For conventional and organizational reasons, "static" block templates should
 * live under the /views/blocks/static folder. 
 *
 * All block data for front-end use is accessed using the block helper.
 * (except when using the "ajax" method of the block helper and accessing the view method here)
 *
 * @author Tom Maiaroto
 * @website http://www.shift8creative.com
 * @modified 2010-06-10 15:13:50 
 * @created 2010-06-10 15:13:50 
 *
 */
namespace minerva\controllers;
use minerva\models\Block;
use li3_flash_message\extensions\storage\FlashMessage;
use li3_access\security\Access;
use \lithium\security\Auth;
use \lithium\storage\Session;
use \lithium\util\Set;

class BlocksController extends \lithium\action\Controller {

    /*
     * Rules used by Access::check() at the Dispatcher level.
     * The rules set here will be passed the Request object, but since
     * called at the Dispatcher level, document level access control isn't possible.
     * See the $document_access property below... All rules requiring document data
     * should be defined there.
     *
     * By default we're restricting all manipulation and index listing to managers.
     * Everyone should be able to view blocks.
     * 
    */
    static $access = array(
	'index' => array(
	    array('rule' => 'allowManagers', 'redirect' => '/users/login')
	),
	'create' => array(
	    array('rule' => 'allowManagers', 'redirect' => '/users/login')
	),
	'update' => array(
	    array('rule' => 'allowManagers', 'redirect' => '/users/login')
	),
	'delete' => array(
	    array('rule' => 'allowManagers', 'redirect' => '/users/login')
	),
	'read' => array(
	    array('rule' => 'allowAll')
	),
	'view' => array(
	    array('rule' => 'allowAll')
	)
    );
    
    static $document_access = array();

    public function view() {		
	if (empty($path)) {
	    $path = array('static', 'example');
	} else {
	    $path = array('static', func_get_args());
	}       
	$this->render(array('template' => join('/', $path), 'layout' => 'blank'));
    }
	
    // TODO: add caching
    public function read($url) {
	// get the page record (also within this record contains the library used, which is important)
	// TODO: make read conditions??
	$record = Block::find('first', array('conditions' => array('url' => $url)));	  	
	// Return an array. No rendering.
	return array('record' => $record);	
    }
    
    public function index() {
	// Default options for pagination
	$defaults = array('page' => 1, 'limit' => 10, 'order' => array('descending' => 'true'));
	$params = Set::merge($defaults, $this->request->params);
	if((isset($params['page'])) && ($params['page'] == 0)) { $params['page'] = 1; }
	list($limit, $page, $order) = array($params['limit'], $params['page'], $params['order']);
	
	if(isset($this->request->params['block_type'])) {
	    $conditions = array('block_type' => $this->request->params['block_type']);
	} else {
	    $conditions = array();
	}
	
	$documents = Block::find('all', array(
	    'limit' => $params['limit'],
	    'offset' => ($params['page'] - 1) * $params['limit'], // TODO: "offset" becomes "page" soon or already in some branch...
	    //'order' => $params['order']
	    'order' => array('_id' => 'asc'),
	    'conditions' => $conditions
	));

	$total = Block::count();
	
	return compact('documents', 'limit', 'page', 'total');		
    }
	
    /** 
     * Create a Block record that has some basic fields that get stored in the database.
     * 
     * Blocks, like pages, can be created and associated to a library. This allows the library to have a "Block" model that
     * can apply filters and perform other actions much like Pages. It gives the library (Minerva plugin) an opportunity to
     * do a little more with block content. Typically you would expect a block to just have some HTML content and sit there
     * being very plain and boring. In other CMS' the idea of these simple blocks can access other parts of the CMS by allowing
     * PHP code to be set into the block and stored in the database. That's not typically a good approach because of when that
     * code actually gets executed. Thanks to Lithium's filter system and by optionally allowing a block to instantiate a
     * library block model class (to apply the filters) we can do much more. Queries can be altered, rendering options can
     * change and even complete other classes and code can be included to perform many operations (simple or complex) in an
     * elegant way.
     *
     * For example, think of a "Gallery Block" and what it may need to contain. Going to the url: site.com/blocks/create/gallery
     * would create a block under the gallery library's control. It would essentially "belong" to the gallery library.
     * You may have your gallery library's Block model add new fields to the block record. This could be all the paths to some
     * images somewhere or a reference to gallery record generated by the gallery library and stored elsewhere.
     * Then in your template you could loop through the images and display a gallery within a block.
     *
     * This is much more user friendly than having a big empty form textarea where someone who knew a little about development
     * would paste in, or type in from scratch, some PHP code to get the data that was required and then loop through and do
     * all the output right there because they didn't have the fields they needed on the block record. Again, this is where the
     * power and flexibility shine with MongoDB. Of course don't forget Lithium's filter system or the way Minerva is setup,
     * they all have to work together to pull off this flexibility.
     * 
    */
    public function create() {
	// Get the name for the page, so if another page type library uses the "admin" (core) templates for this action, it will be shown
	$display_name = Block::display_name();
	
	// Get the fields so the view template can iterate through them and build the form
	$fields = Block::schema();
	// Don't need to have these fields in the form
	unset($fields[Block::key()]);
	// If a block type was passed in the params, we'll need it to save to the block document.
	$fields['block_type']['form']['value'] = (isset($this->request->params['block_type'])) ? $this->request->params['block_type']:null;
	
	// Save
	if ($this->request->data) {
	    $document = Block::create($this->request->data);	    
	    if($document->save()) {		
		$this->redirect(array('controller' => 'blocks', 'action' => 'index'));
	    }
	}
	
	if(empty($document)) {
	    $document = Block::create(); // Create an empty block document object
	}
    
	$this->set(compact('document', 'fields', 'display_name'));
    }
    
    /**
     * Update a block record.
     * 
    */
    public function update($url=null) {	
	$record = Block::find('first', array('conditions' => array('url' => $url)));
	
	$fields = Block::$fields;
	$fields[Block::key()] = array('type' => 'hidden', 'label' => false);
	//$fields['block_type'] = array('type' => 'hidden', 'label' => false);
	
	// Update the record
	if ($this->request->data) {
	    if($record->save($this->request->data)) {				
		$this->redirect(array('controller' => 'blocks', 'action' => 'index'));
	    }
	}
	$this->set(compact('record', 'fields'));
    }
	
    /** 
     *  Delete a block record.
     *  Plugins can apply filters within their Block model class in order to run filters for the delete.  
    */
    public function delete() {
	if(!isset($this->request->params['url'])) {
	    $this->redirect(array('controller' => 'blocks', 'action' => 'index'));
	}
	
	$document = Block::findByUrl($this->request->params['url']);
	
	if($document->delete()) {
	    FlashMessage::set('The block has been deleted.', array('options' => array('type' => 'success', 'pnotify_title' => 'Success', 'pnotify_opacity' => .8)));
	    $this->redirect(array('controller' => 'blocks', 'action' => 'index'));
	} else {
	    FlashMessage::set('The block could not be deleted, please try again.', array('options' => array('type' => 'error', 'pnotify_title' => 'Error', 'pnotify_opacity' => .8)));
	    $this->redirect(array('controller' => 'blocks', 'action' => 'index'));
	}		
    }	
    
    
    // test method
    public function foo() {
	$foo_data = array('string_data' => 'Hello foo.', 'int_data' => 4);		
	return compact('foo_data');
    }
    
}
?>