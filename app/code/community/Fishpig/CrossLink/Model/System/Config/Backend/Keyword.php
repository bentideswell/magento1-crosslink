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
			$empty = array();

			while(count($values) > 0) {
				$lval = null;
				$lkey = null;

				foreach($values as $id => $value) {
					if (!isset($value['sort_order']) || trim($value['sort_order']) === '') {
						$empty[$id] = $value;
						unset($values[$id]);
					}
					else if (is_null($lkey) || (int)$value['sort_order'] < $lval) {
						$lval = (int)$value['sort_order'];
						$lkey = $id;
					}
				}
				
				if (!is_null($lkey)) {
					$sorted[$lkey] = $values[$lkey];
					unset($values[$lkey]);
				}
			}
			
			if (count($empty) > 0) {
				$sorted = array_merge($sorted, $empty);
			}
		}

		$this->setValue($sorted);
		
		return parent::_beforeSave();
	}
}