<?php
/**
 * @category  Fishpig
 * @package  Fishpig_CrossLink
 * @license    http://fishpig.co.uk/license.txt
 * @author    Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_CrossLink_Block_System_Config_Form_Field_Keyword extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
	/**
	 * Prepare to render
	*/
	protected function _prepareToRender()
	{
		$this->addColumn('url', array(
			'label' => $this->__('URL'),
		));
	
		$this->addColumn('keywords', array(
			'label' => $this->__('Keywords (comma separated)'),
		));
		
		$this->addColumn('sort_order', array(
			'label' => $this->__('Sort Order'),
			'style' => 'max-width: 60px;',
		));
	
		$this->_addAfter = false;
		$this->_addButtonLabel = $this->__('Add New Link');
	}
	
	public function render(Varien_Data_Form_Element_Abstract $element)
	{
		$html = parent::render($element);
		
		$position = strpos($html, substr($html, strpos($html, '<label')));

		$js = '<script type="text/javascript" id="crosslinks-script">document.observe("dom:loaded", function() {
			var me = $("crosslinks-script");
			var tr = me.up("tr");

			tr.select("td.label").invoke("remove");
			tr.select("td.value").invoke("writeAttribute", "colspan", 2);
		});</script>';

		
		return substr($html, 0, $position)
			. $js . substr($html, $position);
		
	}
}
