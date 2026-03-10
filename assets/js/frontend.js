jQuery(function($){
	function formatPrice(value) {
		value = parseFloat(value || 0);
		return value.toFixed(2).replace('.', ',') + ' kr.';
	}

	function updateMenuOptions($box) {
		var pricePerCover = parseFloat($box.data('price-per-cover') || 0);
		var covers = parseInt($box.find('.cmbwc-covers').val(), 10) || 1;
		var total = pricePerCover * covers;

		$box.find('.cmbwc-cover-count').text(covers);
		$box.find('.cmbwc-total-price').text(formatPrice(total));
	}

	$(document).on('input change', '.cmbwc-covers', function(){
		var $box = $(this).closest('.cmbwc-menu-options');
		updateMenuOptions($box);
	});

	$('.cmbwc-menu-options').each(function(){
		updateMenuOptions($(this));
	});
});
