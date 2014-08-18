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
<?php
	$clinical = $this->checkAccess('OprnViewClinical');
	$warnings = $this->patient->getWarnings($clinical);
?>

<div class="panel patient<?php if ($warnings) echo " warning" ?>" id="patientID">
	<div class="patient-details">
		<?php echo CHtml::link($this->patient->getDisplayName(),array('/patient/view/'.$this->patient->id)) ?>
		<span class="patient-age">(<?php if ($this->patient->isDeceased()) { ?>Deceased<?php } else { echo $this->patient->getAge(); } ?>)</span>
	</div>
	<div class="hospital-number">
		<span class="screen-only">
			No.
		</span>
		<?php 
		$field = Yii::app()->params['highlight_identifier'] == 'nhs_num' ? 'hos_num' : 'nhs_num';
		?>
		<span class="print-only">
			<?php echo $this->patient->getAttributeLabel($field)?>
		</span>
		<?php echo $this->patient->$field?>
	</div>
	<div class="row">
		<div class="large-8 column">

			<!-- NHS number -->
			<div class="<?php if (Yii::app()->params['nhs_logo']) {?>nhs<?php }else{?>generic<?php }?>-number">
				<?php
				$field = Yii::app()->params['highlight_identifier']?>
				<span class="hide-text print-only">
					<?php echo $this->patient->getAttributeLabel($field)?>:
				</span>
				<?php if ($field == 'nhs_num') {
					$field = 'nhsnum';
				}?>
				<?php echo $this->patient->$field?>
			</div>

			<!-- Gender -->
			<span class="icon icon-alert icon-alert-<?php echo strtolower($this->patient->getGenderString()) ?>_trans">
				<?php echo $this->patient->getGenderString() ?>
			</span>

			<?php if ($widgets = Yii::app()->params['patient_summary_id_widgets']) {
				foreach ($widgets as $w) {
					$this->widget($w['class'], array(
									'patient' => $this->patient,
							));
				}
			}?>

			<!-- Warnings -->
			<?php if ($warnings) {
				$msgs = array();
				foreach ($warnings as $warn) {
					$msgs[] = $warn['short_msg'];
				}?>
				<span class="warning">
					<span class="icon icon-alert icon-alert-warning"></span>
					<span class="messages"><?php echo implode(', ', $msgs); ?></span>
				</span>
			<?php } ?>

		</div>
		<div class="large-4 column text-right patient-summary-anchor">
			<?php echo CHtml::link('Patient Summary',array('/patient/view/'.$this->patient->id)); ?>
		</div>
	</div>
</div>
