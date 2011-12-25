<?php
	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	Class fieldfop extends Field{
		protected static $ready = true;
		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'pdf-version';
			$this->_required = true;
			$this->set('required', 'yes');
		}

		function isSortable()
		{
			return false;
		}

		function canFilter()
		{
			return false;
		}

		function allowDatasourceParamOutput()
		{
			return false;
		}

		function canPrePopulate()
		{
			return true;
		}

		public function findAllFields($section_id) {
			$fieldManager = new FieldManager(Symphony::Engine());
			$fields = $fieldManager->fetch(NULL, $section_id, 'ASC', 'sortorder', NULL, NULL, 'AND (type != "fop")');
			if(is_array($fields) && !empty($fields)) {
				foreach($fields as $field) {
					$options[] = 'entry/'.$field->get('element_name');
				}
			};
			return $options;
			}


		public function displaySettingsPanel(&$wrapper, $errors=NULL)
		{
			// get current section id
			parent::displaySettingsPanel($wrapper, $errors);
			
			$section_id = Administration::instance()->Page->_context[1];
			//choose page

			$label = Widget::Label(__('xsl:fo page'));
			$pages = Symphony::Database()->fetch("SELECT tbl_pages.id FROM `tbl_pages` LEFT JOIN `tbl_pages_types` ON tbl_pages.id=tbl_pages_types.page_id WHERE tbl_pages_types.type = 'fop' ORDER BY sortorder ASC");
			
			if(!empty($pages)) foreach($pages as $page) {
				$options[] = array(
					$page['id'],
					($page['id'] == $this->get('page')),
					'/' . Administration::instance()->resolvePagePath($page['id'])
				);
			}

			$label->appendChild(Widget::Select('fields['.$this->get('sortorder').'][page]', $options));
			$wrapper->appendChild($label);
			
			## Destination Folder
			$ignore = array(
				'/workspace/events',
				'/workspace/data-sources',
				'/workspace/text-formatters',
				'/workspace/pages',
				'/workspace/utilities'
			);
			$directories = General::listDirStructure(WORKSPACE, null, true, DOCROOT, $ignore);

			$label = Widget::Label(__('Destination Directory'));

			$options = array();
			$options[] = array('/workspace', false, '/workspace');
			if(!empty($directories) && is_array($directories)){
				foreach($directories as $d) {
					$d = '/' . trim($d, '/');
					if(!in_array($d, $ignore)) $options[] = array($d, ($this->get('destination') == $d), $d);
				}
			}

			$label->appendChild(Widget::Select('fields['.$this->get('sortorder').'][destination]', $options));

			if(isset($errors['destination'])) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $errors['destination']));
			else $wrapper->appendChild($label);
			
			//filename
			$label = Widget::Label(__('Filename pattern'));
			$label->appendChild(new XMLElement('i', 'You can use xPath to build dinamic values'));
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][filename]',$this->get('filename')));
			$wrapper->appendChild($label);
			$available_fields = $this->findAllFields($section_id);

				if(is_array($available_fields) && !empty($available_fields)){
					$fieldslist = new XMLElement('ul');
					$fieldslist->setAttribute('class', 'tags inline');

					foreach($available_fields as $field) {
						$fieldslist->appendChild(
							new XMLElement('li', '{'.$field.'}')
						);
					}
					$wrapper->appendChild($fieldslist);
				}			

		}

		public function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL)
		{
			//this field shows the created pdf path
			//TODO: add maybe a remove link?
			if(!is_dir(DOCROOT . $this->get('destination') . '/')){
				$flagWithError = __('The destination directory, <code>%s</code>, does not exist.', array($this->get('destination')));
			}

			elseif(!$flagWithError && !is_writable(DOCROOT . $this->get('destination') . '/')){
				$flagWithError = __('Destination folder, <code>%s</code>, is not writable. Please check permissions.', array($this->get('destination')));
			}

			$label = Widget::Label($this->get('label'));
			$class = 'notice';
			$label->setAttribute('class', $class);

			$span = new XMLElement('span', NULL, array('class' => 'frame'));
			if($data['file']) { 
				$span->appendChild(Widget::Anchor($data['file'], URL . $data['file']));
				} else {
				$span->appendChild(new XMLElement('i', 'not set yet'));	
				}

			$label->appendChild($span);

			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);
			
		}
		
		
		
		public function commit()
		{
			if(!parent::commit()) return false;

			$id		= $this->get('id');
			$page 	= $this->get('page');
			$destination = $this->get('destination');
			$filename = $this->get('filename');

			if($id === false) return false;

			$fields = array();

			$fields['field_id'] = $id;
			$fields['page'] = $page;
			$fields['destination'] = $destination;
			$fields['filename'] = $filename;

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");

			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());

		}
		
		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			$status = self::__OK__;
			
			return array(
				'file'	=> null
			);			
		
		}
		
		

		public function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL)
		{
			

		}

		public function checkPostFieldData($data, &$message, $entry_id=NULL)
		{
			$driver = Symphony::ExtensionManager()->create('fop');
			$driver->registerField($this);
			
			return self::__OK__;
		}

		public function createTable()
		{
			return Symphony::Database()->query(

				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `file` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `file` (`file`)
				) TYPE=MyISAM;"

			);
		}

		function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false)
		{
			if(preg_match('/^mysql:/i', $data[0])){

				$field_id = $this->get('id');

				$expression = str_replace(array('mysql:', 'value'), array('', " `t$field_id`.`file` " ), $data[0]);

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND $expression ";

			}

			else parent::buildDSRetrivalSQL($data, $joins, $where, $andOperation);

			return true;

		}
		
	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data){
			// It is possible an array of NULL data will be passed in. Check for this.
			if(!is_array($data) || !isset($data['file']) || is_null($data['file'])){
				return;
			}

			$item = new XMLElement($this->get('element_name'));
			$file = DOCROOT . $data['file'];
			$item->setAttributeArray(array(
				'size' => (file_exists($file) && is_readable($file) ? General::formatFilesize(filesize($file)) : 'unknown'),
			 	'path' => str_replace(WORKSPACE, NULL, dirname(WORKSPACE . $data['file'])),
				'type' => 'application/pdf',
			));

			$item->appendChild(new XMLElement('filename', General::sanitize(basename($data['file']))));
			$wrapper->appendChild($item);
		}

		public function prepareTableValue($data, XMLElement $link=NULL, $entry_id = null){
			if(!$file = $data['file']) return NULL;

			if($link){
				$link->setValue(basename($file));
				return $link->generate();
			}

			else{
				$link = Widget::Anchor(basename($file), URL . '/workspace' . $file);
				return $link->generate();
			}
		}
		
		public function compile($entry) {
			//this function gets called upon saving/editing the entry, here we define pdf name and path
			self::$ready = false;
			
			$driver = Symphony::ExtensionManager()->create('fop');
			$xpath = $driver->getXPath($entry); //see getXPath() function in extension driver
						
			self::$ready = true;
			
			$entry_id = $entry->get('id');
			$field_id = $this->get('id');
			$expression = $this->get('filename');
			$replacements = array();
			
			// Find queries:
			preg_match_all('/\{[^\}]+\}/', $expression, $matches);
			
			// Find replacements:
			foreach ($matches[0] as $match) {
				$result = @$xpath->evaluate('string(' . trim($match, '{}') . ')');
				if (!is_null($result)) {
					$replacements[$match] = trim($result);
				}
				
				else {
					$replacements[$match] = '';
				}
			};
			
			// Apply replacements:
			$filename = str_replace(
				array_keys($replacements),
				array_values($replacements),
				$expression
			);
			
			//this is the resolved filename.
			$file = $this->get('destination').$filename;
			
			
			// Saves field informations:
			$result = $this->Database->update(
				array(
					'file' => $this->get('destination').$filename
				),
				"tbl_entries_data_{$field_id}",
				"`entry_id` = '{$entry_id}'"
			);
			
			//make sure folder structure exists before trying to generate the file:			
			$folders = explode('/',$filename);
			$f = array_pop($folders);
			$startpath = $this->get('destination');
			//This loop is messy and not really tested, same result can probably can be achieved with only one line of code... 
			foreach($folders as $folder) {
				if($folder != '') {
					$checkpath = DOCROOT.$startpath.'/'.$folder;
					//echo $folder.'<br>';
					if(is_dir($checkpath)) {
						//echo $checkpath.': folder exists'.'<br>';					
						} else if(file_exists($checkpath))  {
						//echo $checkpath.': this is a file'.'<br>';	
						} else {
						//echo 'create folder:'.$checkpath.'<br>';
						mkdir($checkpath,0775);
						}
					$startpath .= '/'.$folder;
				}
			}
			//create pdf file
			//load frontend-page with cUrl ( quick and dirty; probably it would be better to use symphony gateway class)
			
			$url = URL.'/'.Administration::instance()->resolvePagePath($this->get('page')).'/'.$entry_id;
			$cookie = 'PHPSESSID=' . $_COOKIE['PHPSESSID'] . '; path=/';
			session_write_close();
			
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); //commented because it causes errors with safe_mode
			curl_setopt($ch, CURLOPT_COOKIE, $cookie);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			$result = curl_exec($ch);
			curl_close($ch);
			
			//saves xsl:fo to manifest/tmp
			$tempname = $this->get('page').'_'.$entry_id;
			$foname = MANIFEST.'/tmp/foptemp_'.$tempname.'.xml';
			$fofile = file_put_contents($foname,$result);
			
			$foprequest = EXTENSIONS.'/fop/lib/fop -fo '.$foname.' -pdf '.DOCROOT.$file;
			//in case nothing happens try uncomment the following line and run the code in the terminal
			//echo $foprequest; die;
			
			//run FOP to create pdf
			shell_exec($foprequest);
			
			//some cleanup: delete temp file
			unlink($foname);
			
		}
	}

?>
