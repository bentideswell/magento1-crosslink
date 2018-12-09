<?php
/**
 * @category  Fishpig
 * @package  Fishpig_CrossLink
 * @license    http://fishpig.co.uk/license.txt
 * @author    Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_CrossLink_Model_System_Config_Backend_Keyword extends Mage_Adminhtml_Model_System_Config_Backend_Serialized_Array
{
	/**
	 * Apply the sort order field
	 * If sort order field is empty, entry is pushed to the back of the queue
	 *
	 * @return $this
	 */
	protected function _beforeSave()
	{
		$values = $this->getValue();
		
		if ($values && is_array($values) && count($values) > 0) {
			$sorted = array();
			$final  = array();
			
			foreach($values as $key => $value) {
				if (!$value) {
					continue;
				}
				
				$value['sort_order'] = (int)$value['sort_order'];
				
				if (!isset($sorted[$value['sort_order']])) {
					$sorted[$value['sort_order']] = array();
				}
				
				$sorted[$value['sort_order']][] = $value;
			}			
			foreach($sorted as $order => $values) {
				foreach($values as $value) {
					$final[] = $value;
				}
			}
			
			$this->setValue($final);
		}

		return parent::_beforeSave();
	}
}
