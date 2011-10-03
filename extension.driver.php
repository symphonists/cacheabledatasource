<?php

	Class Extension_CacheableDatasource extends Extension {

		public function about(){
			return array('name' => 'Cacheable Datasource',
						 'version' => '0.5',
						 'release-date' => '2011-09-01',
						 'author' => array('name' => 'Nick Dunn',
										   'website' => 'http://nick-dunn.co.uk'),
						'description' => 'Create custom Data Sources that implement output caching');

		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'DataSourcePreExecute',
					'callback'	=> 'dataSourcePreExecute'
				),
			);
		}
		
		public function dataSourcePreExecute($context) {
			$ds = $context['datasource'];
			$param_pool = $context['param_pool'];
			
			// Check that this DS has a cache time set
			if (isset($ds->dsParamCACHE) && is_numeric($ds->dsParamCACHE) && $ds->dsParamCACHE > 0) {
				
				$filename = NULL;
				$file_age = 0;

				if ($this->__buildCacheFilename($ds, $filename, $file_age)) {	

					// HACK: peek at the first line of XML to see if it's a serialised array
					// which contains cached output parameters

					$xml = file_get_contents($filename);

					// split XML into an array of each line
					$xml_lines = explode("\n",$xml);

					// output params are a serialised array on line 1
					$output_params = @unserialize(trim($xml_lines[0]));

					// there are cached output parameters
					if (is_array($output_params)) {

						// remove line 1 and join XML into a string again
						unset($xml_lines[0]);
						$xml = join('', $xml_lines);

						// add cached output params back into the pool
						foreach ($output_params as $key => $value) {
							$param_pool[$key] = $value;
						}
					}

					// set cache age in the XML result
					$xml = preg_replace('/cache-age="fresh"/', 'cache-age="'.$file_age.'s"', $xml);

				} else {
					// Backup the param pool, and see what's been added
					$tmp = array();

					// Fetch the contents
					$xml = $this->__executeDatasource($ds, $tmp);

					$output_params = null;

					// Push into the params array
					foreach ($tmp as $name => $value) {
						$param_pool[$name] = $value;
					}

					if (count($tmp) > 0) $output_params = sprintf("%s\n", serialize($tmp));

					// Add an attribute to preg_replace later
					$xml->setAttribute("cache-age", "fresh");

					// Write the cached XML to disk
					file_put_contents($filename, $output_params . $xml->generate(true, 1));

				}																														
			}

			$context['xml'] = $xml;
			$context['param_pool'] = $param_pool;
			
		}
		
		private function __buildCacheFilename($datasource, &$filename, &$file_age) {
			$filename = null;

			// get resolved values of each public property of this DS
			// (sort, filters, included elements etc.)
			foreach (get_class_vars(get_class($datasource)) as $key => $value) {
				if (substr($key, 0, 2) == 'ds') {
					$value = $datasource->{$key};
					$filename .= $key . (is_array($value) ? implode($value) : $value);
				}
			}

			$filename = sprintf(
				"%s/cache/datasources/%s_%s.xml",
				MANIFEST,
				preg_replace("/^datasource/", '', get_class($datasource)),
				md5($filename)
			);

			if (!file_exists($filename)) return false;

			$file_age = (int)(floor(time() - filemtime($filename)));

			return ($file_age < ($datasource->dsParamCACHE));
		}

		private function __executeDatasource($datasource, &$param_pool=array()) {

			$result = $datasource->grab($param_pool);
			$xml = is_object($result) ? $result->generate(true, 1) : $result;

			// Parse DS XML to check for errors. If contains malformed XML such as
			// an unescaped database error, the error is escaped in CDATA
			$doc = new DOMDocument('1.0', 'utf-8');

			libxml_use_internal_errors(true);
	        $doc->loadXML($xml);            
	        $errors = libxml_get_errors();
			libxml_clear_errors();
			libxml_use_internal_errors(false);

	    	// No error, just return the result
	        if (empty($errors)) return $result;

			// There's an error, so $doc will be empty
			// Use regex to get the root node			
			// If something's wrong, just push back the broken XML			
			if (!preg_match('/<([^ \/>]+)/', $xml, $matches)) return $result;

			$ret = new XMLElement($matches[1]);

			// Set the invalid flag
			$ret->setAttribute("xml-invalid", "true");

			$errornode = new XMLElement("errors");

			// Store the errors
			foreach ($errors as $error) {
				$item = new XMLElement("error", trim($error->message));
				$item->setAttribute('line', $error->line);
				$item->setAttribute('column', $error->column);
				$errornode->appendChild($item);
			}

			$ret->appendChild($errornode);

			// Return the XML
			$ret->appendChild(new XMLElement('broken-xml', "<![CDATA[" . $xml . "]]>"));

			return $ret;									
		}		
	}

?>