<?php

	Class extension_fop extends Extension{
		
		protected static $fields = array();
		
		public function about(){
			return array('name' => 'FOP - xml graphics processor',
						 'version' => '0.1',
						 'release-date' => '2011-12-21',
						 'author' => array('name' => 'BBOX',
										   'email' => 'nany@bbox.it')
				 		);
		}
		
		public function uninstall(){
			Symphony::Database()->query("DROP TABLE `tbl_fields_fop`");
		}


		public function install(){

			return Symphony::Database()->query("CREATE TABLE `tbl_fields_fop` (
			  `id` int(11) unsigned NOT NULL auto_increment,
			  `field_id` int(11) unsigned NOT NULL,
			  `page` int(11) unsigned NOT NULL,
			  `destination` varchar(255) NOT NULL,
			  `filename` varchar(255) NOT NULL,
			  PRIMARY KEY  (`id`),
			  UNIQUE KEY `field_id` (`field_id`)
			) TYPE=MyISAM");

		}

		
		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendOutputPostGenerate',
					'callback' => 'exportFrontpageAsPdf'
				),
				array(
					'page' => '/publish/edit/',
					'delegate' => 'EntryPostEdit',
					'callback' => 'createPdfWithFop'
				)
				,
				array(
					'page' => '/publish/new/',
					'delegate' => 'EntryPostCreate',
					'callback' => 'createPdfWithFop'
				)
			);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/
		
		public function getXPath($entry) {
			//create entry XML for replacing variable part of filename with xpath
			$fieldManager = new FieldManager(Symphony::Engine());
			$entry_xml = new XMLElement('entry');
			$section_id = $entry->get('section_id');
			$data = $entry->getData(); $fields = array();
			
			$entry_xml->setAttribute('id', $entry->get('id'));
			
			$associated = $entry->fetchAllAssociatedEntryCounts();
			
			if (is_array($associated) and !empty($associated)) {
				foreach ($associated as $section => $count) {
					$handle = Symphony::Database()->fetchVar('handle', 0, "
						SELECT
							s.handle
						FROM
							`tbl_sections` AS s
						WHERE
							s.id = '{$section}'
						LIMIT 1
					");
					
					$entry_xml->setAttribute($handle, (string)$count);
				}
			}
			
			// Add fields:
			foreach ($data as $field_id => $values) {
				if (empty($field_id)) continue;
				
				$field = $fieldManager->fetch($field_id);
				$field->appendFormattedElement($entry_xml, $values, false, null);
			}
			
			$xml = new XMLElement('data');
			$xml->appendChild($entry_xml);
		
			$dom = new DOMDocument();
			$dom->strictErrorChecking = false;
			$dom->loadXML($xml->generate(true));
			
			$xpath = new DOMXPath($dom);
			
			if (version_compare(phpversion(), '5.3', '>=')) {
				$xpath->registerPhpFunctions();
			}
			
			return $xpath;
		}

	/*-------------------------------------------------------------------------
		field:
	-------------------------------------------------------------------------*/
		public function registerField($field) {
			self::$fields[] = $field;
		}
		
		
		public function createPdfWithFop($context){
			//compile filename
			
			foreach (self::$fields as $field) {
				$field->compile($context['entry']);
			}
			
			return;			
		}
		
	/*-------------------------------------------------------------------------
		Pseudo-Event: this allows to transform a frontend page in pdf
	-------------------------------------------------------------------------*/
		 function exportFrontpageAsPdf($context) {
			//request must contain a pdfname url-parameter
			if(isset($_GET['pdfname'])) {
				$tstamp = time();
			//save xsl:fo to the temp folder
				$foname = MANIFEST.'/tmp/foptemp_'.$tstamp.'xml';
				$fofile = file_put_contents($foname,$context['output']);
			//define pdf name
				$pdffile = MANIFEST.'/tmp/'.$_GET['pdfname'];
			//run FOP to create pdf
				shell_exec(EXTENSIONS.'/fop/lib/fop -fo '.$foname.' -pdf '.$pdffile);
			//prompt for download
				header('Content-Description: File Transfer');
			    header('Content-Type: application/pdf');
			    header('Content-Disposition: attachment; filename='.$_GET['pdfname']);
			    header('Content-Transfer-Encoding: binary');
			    header('Expires: 0');
			    header('Cache-Control: must-revalidate');
			    header('Pragma: public');
			    header('Content-Length: ' . filesize($pdffile));
			    readfile($pdffile);
			//cleanup: delete temp files
				unlink($foname);
				unlink($pdffile);
				exit;
				}
		}
		
	}

?>