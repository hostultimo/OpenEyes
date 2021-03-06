<div class="eventDetail<?php if ($last) {?> eventDetailLast<?php }?>" id="typeProcedure"<?php if ($hidden) {?> style="display: none;"<?php }?>>
	<div class="label"><?php echo $label?>:</div>
	<div class="data split limitWidth">
		<div class="left">
			<?php if ($headertext) {?>
				<h5 class="normal"><em><?php echo $headertext?></em></h5>
			<?php }?>
			<h5 class="normal"><em>Add a procedure:</em></h5>

			<?php
			if (!empty($subsections) || !empty($procedures)) {
				if (!empty($subsections)) {
					echo CHtml::dropDownList('subsection_id_'.$identifier, '', $subsections, array('empty' => 'Select a subsection', 'style' => 'width: 90%; margin-bottom:10px;'));
					echo CHtml::dropDownList('select_procedure_id_'.$identifier, '', array(), array('empty' => 'Select a commonly used procedure', 'style' => 'display: none; width: 90%; margin-bottom:10px;'));
				} else {
					echo CHtml::dropDownList('select_procedure_id_'.$identifier, '', $procedures, array('empty' => 'Select a commonly used procedure', 'style' => 'width: 90%; margin-bottom:10px;'));
				}
			}
			?>

			<?php
				$this->widget('zii.widgets.jui.CJuiAutoComplete', array(
					'name'=>'procedure_id_'.$identifier,
					'id'=>'autocomplete_procedure_id_'.$identifier,
					'source'=>"js:function(request, response) {
						var existingProcedures = [];
						$('#procedureList_$identifier').children('h4').children('div.procedureItem').map(function() {
							var text = $.trim($(this).children('span:nth-child(2)').text());
							existingProcedures.push(text.replace(/ - .*?$/,''));
						});

						$.ajax({
							'url': '" . Yii::app()->createUrl('procedure/autocomplete') . "',
							'type':'GET',
							'data':{'term': request.term, 'restrict': '$restrict'},
							'success':function(data) {
								data = $.parseJSON(data);

								var result = [];

								for (var i = 0; i < data.length; i++) {
									var index = $.inArray(data[i], existingProcedures);
									if (index == -1) {
										result.push(data[i]);
									}
								}

								response(result);
							}
						});
					}",
					'options'=>array(
						'minLength'=>'2',
						'select'=>"js:function(event, ui) {
							".($callback ? $callback."(ui.item.id, ui.item.value);" : '')."
							if (typeof(window.callbackVerifyAddProcedure) == 'function') {
								window.callbackVerifyAddProcedure(ui.item.value,".($durations?'1':'0').",".($short_version?'1':'0').",function(result) {
									if (result != true) {
										$('#autocomplete_procedure_id_$identifier').val('');
										return;
									}
									ProcedureSelectionSelectByName(ui.item.value,true,'$identifier');
								});
							} else {
								ProcedureSelectionSelectByName(ui.item.value,true,'$identifier');
							}
						}",
					),
				'htmlOptions'=>array('style'=>'width: 90%;','placeholder'=>'or enter procedure here')
			)); ?>

		</div>
	</div>
</div>
<script type="text/javascript">
	// Note: Removed_stack is probably not the best name for this. Selected procedures is more accurate.
	// It is used to suppress procedures from the add a procedure inputs
	var removed_stack_<?php echo $identifier?> = [<?php echo implode(',', $removed_stack); ?>];

	function updateTotalDuration(identifier)
	{
		// update total duration
		var totalDuration = 0;
		$('#procedureList_'+identifier).children('h4').children('div.procedureItem').map(function() {
			$(this).children('span:last').map(function() {
				totalDuration += parseInt($(this).html().match(/[0-9]+/));
			});
		});
		if ($('input[name=\"<?php echo $class?>[eye_id]\"]:checked').val() == 3) {
			$('#projected_duration_'+identifier).text(totalDuration + ' * 2');
			totalDuration *= 2;
		}
		$('#projected_duration_'+identifier).text(totalDuration+" mins");
		$('#<?php echo $class?>_total_duration_'+identifier).val(totalDuration);
	}

	$('a.removeProcedure').die('click').live('click',function() {
		var m = $(this).parent().parent().parent().parent().attr('id').match(/^procedureList_(.*?)$/);
		removeProcedure($(this),m[1]);
		return false;
	});

	function removeProcedure(element, identifier)
	{
		var len = element.parent().parent().parent().children('div').length;
		var procedure_id = element.parent().parent().find('input[type="hidden"]:first').val();

		element.parent().parent().remove();

		<?php if ($durations) {?>
			updateTotalDuration(identifier);
		<?php }?>

		if (len <= 1) {
			$('#procedureList_'+identifier).hide();
			<?php if ($durations) {?>
				$('div.extraDetails').hide();
			<?php }?>
		}

		if (typeof(window.callbackRemoveProcedure) == 'function') {
			callbackRemoveProcedure(procedure_id);
		}

		// Remove removed procedure from the removed_stack
		var stack = [];
		var popped = null;
		$.each(window["removed_stack_"+identifier], function(key, value) {
			if (value["id"] != procedure_id) {
				stack.push(value);
			} else {
				popped = value;
			}
		});
		window["removed_stack_"+identifier] = stack;

		// Refresh the current procedure select box in case the removed procedure came from there
		if ($('#subsection_id_'+identifier).length) {
			// Procedures are in subsections, so fetch a clean list via ajax (easier than trying to work out if it's the right list)
			updateProcedureSelect(identifier);
		} else if (popped) {
			// No subsections, so we should be safe to just push it back into the list
			$('#select_procedure_id_'+identifier).append('<option value="'+popped["id"]+'">'+popped["name"]+'</option>').removeAttr('disabled');
			sort_selectbox($('#select_procedure_id_'+identifier));
		}

		return false;
	}

	function selectSort(a, b)
	{
			if (a.innerHTML == rootItem) {
					return -1;
			} else if (b.innerHTML == rootItem) {
					return 1;
			}
			return (a.innerHTML > b.innerHTML) ? 1 : -1;
	};

	$('select[id^=subsection_id]').unbind('change').change(function() {
		var m = $(this).attr('id').match(/^subsection_id_(.*)$/);
		updateProcedureSelect(m[1]);
	});

	function updateProcedureSelect(identifier)
	{
		var subsection_field = $('select[id=subsection_id_'+identifier+']');
		var subsection = subsection_field.val();
		if (subsection != '') {
			var existingProcedures = [];
			$('#procedureList_'+identifier).children('h4').children('div.procedureItem').map(function() {
				var text = $.trim($(subsection_field).children('span:nth-child(2)').text());
				existingProcedures.push(text.replace(/ - .*?$/,''));
			});

			$.ajax({
				'url': '<?php echo Yii::app()->createUrl('procedure/list')?>',
				'type': 'POST',
				'data': {'subsection': subsection, 'existing': existingProcedures, 'YII_CSRF_TOKEN': YII_CSRF_TOKEN},
				'success': function(data) {
					$('select[name=select_procedure_id_'+identifier+']').attr('disabled', false);
					$('select[name=select_procedure_id_'+identifier+']').html(data);

					// remove any items in the removed_stack
					$('select[name=select_procedure_id_'+identifier+'] option').map(function() {
						var obj = $(this);

						$.each(window["removed_stack_"+identifier], function(key, value) {
							if (value["id"] == obj.val()) {
								obj.remove();
							}
						});
					});

					$('select[name=select_procedure_id_'+identifier+']').show();
				}
			});
		} else {
			$('select[name=select_procedure_id_'+identifier+']').hide();
		}
	}

	$('select[id^="select_procedure_id"]').unbind('change').change(function() {
		var m = $(this).attr('id').match(/^select_procedure_id_(.*)$/);
		var identifier = m[1];
		var select = $(this);
		var procedure = $('select[name=select_procedure_id_'+m[1]+'] option:selected').text();
		if (procedure != 'Select a commonly used procedure') {

		<?php if ($callback) {?>
			<?php echo $callback?>($(this).children('option:selected').val(), $(this).children('option:selected').text());
		<?php }?>

			if (typeof(window.callbackVerifyAddProcedure) == 'function') {
				window.callbackVerifyAddProcedure(procedure,".($durations?'1':'0').",".($short_version?'1':'0').",function(result) {
					if (result != true) {
						select.val('');
						return;
					}

					if (typeof(window.callbackAddProcedure) == 'function') {
						var procedure_id = $('select[name=select_procedure_id_'+identifier+'] option:selected').val();
						callbackAddProcedure(procedure_id);
					}

					ProcedureSelectionSelectByName(procedure,false,m[1]);
				});
			} else {
				if (typeof(window.callbackAddProcedure) == 'function') {
					var procedure_id = $('select[name=select_procedure_id_'+identifier+'] option:selected').val();
					callbackAddProcedure(procedure_id);
				}

				ProcedureSelectionSelectByName(procedure,false,m[1]);
			}
		}
		return false;
	});

	$(document).ready(function() {
		if ($('input[name=\"<?php echo $class?>[eye_id]\"]:checked').val() == 3) {
			$('#projected_duration_<?php echo $identifier?>').html((parseInt($('#projected_duration_<?php echo $identifier?>').html().match(/[0-9]+/)) * 2) + " mins");
		}
	});

	function ProcedureSelectionSelectByName(name, callback, identifier)
	{
		$.ajax({
			'url': baseUrl + '/procedure/details?durations=<?php echo $durations?'1':'0'?>&short_version=<?php echo $short_version?'1':'0'?>&identifier='+identifier,
			'type': 'GET',
			'data': {'name': name},
			'success': function(data) {
				var enableDurations = <?php echo $durations?'true':'false'?>;
				var shortVersion = <?php echo $short_version?'true':'false'?>;

				// append selection onto procedure list
				$('#procedureList_'+identifier).children('h4').append(data);
				$('#procedureList_'+identifier).show();

				if (enableDurations) {
					updateTotalDuration(identifier);
					$('div.extraDetails').show();
				}

				// clear out text field
				$('#autocomplete_procedure_id_'+identifier).val('');

				// remove selection from the filter box
				if ($('#select_procedure_id_'+identifier).children().length > 0) {
					m = data.match(/<span>(.*?)<\/span>/);

					$('#select_procedure_id_'+identifier).children().each(function () {
						if ($(this).text() == m[1]) {
							var id = $(this).val();
							var name = $(this).text();

							window["removed_stack_"+identifier].push({name: name, id: id});

							$(this).remove();
						}
					});
				}

				if (callback && typeof(window.callbackAddProcedure) == 'function') {
					m = data.match(/<input type=\"hidden\" value=\"([0-9]+)\"/);
					var procedure_id = m[1];
					callbackAddProcedure(procedure_id);
				}
			}
		});
	}
</script>
