<?php

Class CacheableDatasource extends Datasource {
	
	private function buildCacheFilename(&$filename, &$file_age) {
		$filename = null;
			
		// get resolved values of each public property of this DS
		// (sort, filters, included elements etc.)
		foreach (get_class_vars(get_class($this)) as $key => $value) {
			if (substr($key, 0, 2) == 'ds') {
				$value = $this->{$key};
				$filename .= $key . (is_array($value) ? implode($value) : $value);
			}
		}
		
		$filename = sprintf(
			"%s/cache/%s-%s.xml",
			MANIFEST,
			get_class($this),
			md5($filename)
		);
		
		if (!file_exists($filename)) return false;
		
		$file_age = (int)(floor(time() - filemtime($filename)));
		
		return ($file_age < ($this->dsParamCACHE * 60));
	}
	
	private function grabResult(&$param_pool=array()) {
		
		libxml_use_internal_errors(true);
		
		$result = $this->grab_xml($param_pool);
		$xml = is_object($result) ? $result->generate(true, 1) : $result;
		
		// Parse DS XML to check for errors. If contains malformed XML such as
		// an unescaped database error, the error is escaped in CDATA
		$doc = new DOMDocument('1.0', 'utf-8');
        $doc->loadXML($xml);            
        
        $errors = libxml_get_errors();
    
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
	
	public function grab(&$param_pool=array()) {
		
		// Check that this DS has a cache time set
		if (isset($this->dsParamCACHE) && is_numeric($this->dsParamCACHE) && $this->dsParamCACHE > 0) {
			$filename = null;
			$file_age = 0;
			
			if ($this->buildCacheFilename($filename, $file_age)) {	
				
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
				return preg_replace('/cache-age="fresh"/', 'cache-age="'.$file_age.'s"', $xml);
				
			} else {
				// Backup the param pool, and see what's been added
				$tmp = array();
												
				// Fetch the contents
				$contents = $this->grabResult($tmp);
				
				$output_params = null;
				
				// Push into the params array
				foreach ($tmp as $name => $value) {
					$param_pool[$name] = $value;
				}
				
				if (count($tmp) > 0) $output_params = sprintf("%s\n", serialize($tmp));
				
				// Add an attribute to preg_replace later
				$contents->setAttribute("cache-age", "fresh");
				
				// Write the cached XML to disk
				file_put_contents($filename, $output_params . $contents->generate(true, 1));
				
				return $contents;
			}																														
		}
		
		return $this->grabResult($param_pool);
	}			
	
	// The original grab() function from native Data Sources
	public function grab_xml(&$param_pool){
					
		$result = new XMLElement($this->dsParamROOTELEMENT);
			
		try{
			if ($this->getSource() == 'navigation') {
	            include(TOOLKIT . '/data-sources/datasource.navigation.php');
	        } else {
	            include(TOOLKIT . '/data-sources/datasource.section.php');
	        }
		}
		catch(FrontendPageNotFoundException $e){
			// Work around. This ensures the 404 page is displayed and
			// is not picked up by the default catch() statement below
			FrontendPageNotFoundExceptionHandler::render($e);
		}
		catch(Exception $e){
			$result->appendChild(new XMLElement('error', $e->getMessage()));
			return $result;
		}	

		if($this->_force_empty_result) $result = $this->emptyXMLSet();
		
		return $result;
	}
	
}