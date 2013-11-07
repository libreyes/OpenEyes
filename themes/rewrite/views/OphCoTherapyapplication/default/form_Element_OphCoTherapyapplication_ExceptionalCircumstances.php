<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */
?>

<section class="element <?php echo $element->elementType->class_name?>"
	data-element-type-id="<?php echo $element->elementType->id?>"
	data-element-type-class="<?php echo $element->elementType->class_name?>"
	data-element-type-name="<?php echo $element->elementType->name?>"
	data-element-display-order="<?php echo $element->elementType->display_order?>">
	<header class="element-header">
		<h3 class="element-title"><?php echo $element->elementType->name; ?></h3>
	</header>


	<div class="element-fields element-eyes row">
		<?php echo $form->hiddenInput($element, 'eye_id', false, array('class' => 'sideField')); ?>
		<div class="element-eye right-eye left side column <?php if (!$element->hasRight()) { ?> inactive<?php } ?>"
			data-side="right">
			<div class="active-form">
				<?php $this->renderPartial('form_' . get_class($element) . '_fields',
					array('side' => 'right', 'element' => $element, 'form' => $form, 'data' => $data)); ?>
			</div>
			<div class="inactive-form">
				<div class="eye-message">Only required if Patient Suitability is Non-Compliant</div>
			</div>
		</div>

		<div class="element-eye left-eye right side column <?php if (!$element->hasLeft()) { ?> inactive<?php } ?>"
			data-side="left">
			<div class="active-form">
				<?php $this->renderPartial('form_' . get_class($element) . '_fields',
					array('side' => 'left', 'element' => $element, 'form' => $form, 'data' => $data)); ?>
			</div>
			<div class="inactive-form">
				<div class="eye-message">Only required if Patient Suitability is Non-Compliant</div>
			</div>
		</div>

	</div>
</section>

<script id="previntervention_template" type="text/html">
	<?php
	$this->renderPartial('form_OphCoTherapyapplication_ExceptionalCircumstances_PastIntervention', array(
			'key' => '{{key}}',
			'side' => '{{side}}',
			'element_name' => get_class($element),
			'form' => $form,
			'pastintervention' => new OphCoTherapyapplication_ExceptionalCircumstances_PastIntervention(),
	));
	?>
</script>
<script id="relevantintervention_template" type="text/html">
	<?php
	$pastintervention = new OphCoTherapyapplication_ExceptionalCircumstances_PastIntervention();
	$pastintervention->is_relevant = true;
	$this->renderPartial('form_OphCoTherapyapplication_ExceptionalCircumstances_PastIntervention', array(
			'key' => '{{key}}',
			'side' => '{{side}}',
			'element_name' => get_class($element),
			'form' => $form,
			'pastintervention' => $pastintervention,
		));
	?>
</script>
