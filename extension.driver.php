<?php

	Class Extension_CacheableDatasource extends Extension {

		public function about(){
			return array('name' => 'Cacheable Datasource',
						 'version' => '0.4',
						 'release-date' => '2011-02-18',
						 'author' => array('name' => 'Nick Dunn',
										   'website' => 'http://nick-dunn.co.uk'),
						'description' => 'Create custom Data Sources that implement output caching');

		}
		
		public function fetchNavigation(){			
			return array(
				array(
					'location'	=> __('System'),
					'name'	=> __('Cacheable Data Sources'),
					'link'	=> '/view/'
				)
			);		
		}
		
	}

?>