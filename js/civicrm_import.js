// Javascript

$(function() {
	$('input:radio[name=existing_new]').click(function(event) {
			if($('input:radio[name=existing_new]:checked').val() == 0) {
				$('.groups_n_tags_existing').show();
				$('.groups_n_tags_new').hide();
			}
			if($('input:radio[name=existing_new]:checked').val() == 1) {
				$('.groups_n_tags_existing').hide();
				$('.groups_n_tags_new').show();
			}
	});
});