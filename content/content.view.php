<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.datasourcemanager.php');
	require_once(TOOLKIT . '/class.datasource.php');

	Class contentExtensionCacheabledatasourceView extends AdministrationPage{

		protected $_cachefiles = array();

		function __construct(&$parent){
			parent::__construct($parent);
			$this->setTitle(__('Symphony') .' &ndash; ' . __('Cacheable Data Sources'));
		}
		
		// This seems retarded, but it's effiecient
		private function __preliminaryFilenameCheck($filename) {
			// Stop at 't' because it's not a valid hash character
			return ($filename{0} == 'd' && $filename{0} == 'a' && $filename{0} == 't');	
		}
		
		// Build a list of all DS-cache files
		private function __buildCacheFileList() {
			if ($this->_cachefiles != null) return $this->_cachefiles;
			
			if (!$oDirHandle = opendir(CACHE)) trigger_error("Panic! DS cache doesn't exists");
				
			// Initialise the array outside the loop for speed
			$matches = array();
			
			while (($file = readdir($oDirHandle)) !== false) {		
				// Check some initial characters		
				if ($this->__preliminaryFilenameCheck($file)) continue;

				// Drop it if it's not a match
				if (!preg_match('/^datasource(?P<name>[A-Za-z_]+)-(?P<hash>[^\.]+).+/', $file, $matches)) continue;
				
				$last_modified = filemtime(CACHE . '/' . $file);
				
				// Insert into the array
				if (!isset($this->_cachefiles[$matches['name']])) {
					$this->_cachefiles[$matches['name']] = array(
						'count' => 1,
						'size' => filesize(CACHE . '/' . $file),
						'files' => array(CACHE . '/' . $file),
						'last-modified' => $last_modified
					);
				}
				else {
					$this->_cachefiles[$matches['name']]['count']++;
					$this->_cachefiles[$matches['name']]['size'] += filesize(CACHE . '/' . $file);
					array_push(
						$this->_cachefiles[$matches['name']]['files'],
						CACHE . '/' . $file
					);
					if($last_modified > $this->_cachefiles[$matches['name']]['last-modified']) {
						$this->_cachefiles[$matches['name']]['last-modified'] = $last_modified;
					}
				}					            
	        }	 	   	        	        	              
        
      	  	closedir($oDirHandle);
      	  	
      	  	return $this->_cachefiles;			
		}
		
		private function __clearCache($handles) {
			$files = $this->__buildCacheFileList();
			foreach ($handles as $handle) {
				if (array_key_exists($handle, $files)) {		
					foreach($files[$handle]['files'] as $file) {
						unlink($file);
					}
				}					
			}
		}
	
		function view(){
			
			$this->setPageType('table');
			$this->appendSubheading(__('Cacheable Data Sources'));
			
			$aTableHead = array(
				array('Data Source', 'col'),
				array('Lifetime', 'col'),
				array('Cache Files', 'col'),
				array('Size', 'col'),
				array('State', 'col'),
			);
			
			$dsm = new DatasourceManager(Administration::instance());
			$cacheable = new Cacheable(Administration::instance()->Database());
			
			$datasources = $dsm->listAll();	
			
			// read XML from "Cacheable Datasource" extension
			$cachedata = $this->__buildCacheFileList();
			
			$aTableBody = array();

			if(!is_array($datasources) || empty($datasources)){
				$aTableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', NULL, count($aTableHead))))
				);
			} else {
				
				$params = array();
				
				foreach($datasources as $ds) {
					
					$datasource = $dsm->create($ds['handle'], $params);
					
					$has_files = false;
					$has_size = false;
					
					$name = Widget::TableData($ds['name']);
					$name->appendChild(Widget::Input("items[{$ds['handle']}]", null, 'checkbox'));
					
					// if data source is using Cacheable Datasource
					if ($datasource instanceOf CacheableDatasource){
						
						$lifetime = Widget::TableData($datasource->dsParamCACHE . ' ' . ($datasource->dsParamCACHE == 1 ? __('minute') : __('minutes')));
						
						$has_files = isset($cachedata[$ds['handle']]['count']);
						$files = Widget::TableData(
							($has_files ? $cachedata[$ds['handle']]['count'] . ' ' . ($cachedata[$ds['handle']]['count'] == 1 ? __('file') : __('files')) : __('None')),
							($has_files ? NULL : 'inactive')
						);
						
						$has_size = isset($cachedata[$ds['handle']]['size']);
						if ($has_size) {
							if ($cachedata[$ds['handle']]['size'] < 1024) {
								$size_str = $cachedata[$ds['handle']]['size'] . "b";
							} else {
								$size_str = floor($cachedata[$ds['handle']]['size']/1024) . "kb";
							}
						} else {
							$size_str = __('None');
						}
						
						$size = Widget::TableData(
							$size_str,
							($has_size ? NULL : 'inactive')
						);
						
						$last_modified = $cachedata[$ds['handle']]['last-modified'];
						$expires = Widget::TableData(__('None'), 'inactive');
						
						if ($last_modified) {
							$file_age = (int)(floor(time() - $last_modified));
							$expires_at = $last_modified + ($datasource->dsParamCACHE * 60);
							$expires_in = (int)(($expires_at - time()) / 60);
							
							if ($file_age > ($datasource->dsParamCACHE * 60)) {
								$expires = Widget::TableData('Expired');
							} else if($expires_in == 0) {
								$expires = Widget::TableData(__('Cache expires in') . ' ' . ($expires_at - time()) . 's');
							} else {
								$expires = Widget::TableData(__('Cache expires in') . ' ' . $expires_in . ' ' . ($expires_in == 1 ? __('minute') : __('minutes')));
							}
							
						}
						
						$aTableBody[] = Widget::TableRow(array($name, $lifetime, $files, $size, $expires));

					}

				}
			}
						
			$table = Widget::Table(
				Widget::TableHead($aTableHead), 
				NULL, 
				Widget::TableBody($aTableBody),
				'selectable'
			);

			$this->Form->appendChild($table);
			
			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');
			
			$options = array(
				array(null, false, __('With Selected...')),
				array('clear', false, __('Clear Cache'))							
			);
			
			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));
			
			$this->Form->appendChild($tableActions);
			
		}
		
		function __actionIndex(){
			$checked = @array_keys($_POST['items']);
			if(is_array($checked) && !empty($checked)){
				switch($_POST['with-selected']) {
					case 'clear':								
						$this->__clearCache($checked);
						redirect(Administration::instance()->getCurrentPageURL());
					break;
				}
			}
		}
	
	}
	
?>