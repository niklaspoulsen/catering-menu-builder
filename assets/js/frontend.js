jQuery(function ($) {
	function formatPrice(value) {
		value = parseFloat(value || 0);
		return value.toFixed(2).replace('.', ',') + ' kr.';
	}

	function ensureWooForm($box) {
		var $form = $('form.cart').first();

		if (!$form.length) {
			return null;
		}

		var productId = $box.data('product-id');
		var $sync = $box.find('.cmbwc-hidden-sync');

		if (!$sync.length) {
			return $form;
		}

		var fields = [
			'<input type="hidden" name="cmbwc_covers" class="cmbwc-sync-covers" value="">',
			'<input type="hidden" name="cmbwc_selected_service" class="cmbwc-sync-service" value="">',
			'<input type="hidden" name="cmbwc_selected_addons" class="cmbwc-sync-addons" value="">'
		].join('');

		if (!$form.find('.cmbwc-sync-covers').length) {
			$form.append(fields);
		}

		if (!$form.find('.cmbwc-sync-product-id').length) {
			$form.append('<input type="hidden" name="cmbwc_sync_product_id" class="cmbwc-sync-product-id" value="' + productId + '">');
		}

		return $form;
	}

	function getCovers($box) {
		var covers = parseInt($box.find('.cmbwc-covers').val(), 10) || 1;
		var min = parseInt($box.data('minimum-covers'), 10) || 1;
		var step = parseInt($box.data('cover-step'), 10) || 1;

		if (covers < min) {
			covers = min;
		}

		if (step > 1) {
			var diff = covers - min;
			var remainder = diff % step;

			if (remainder !== 0) {
				covers = covers - remainder;
				if (covers < min) {
					covers = min;
				}
			}
		}

		$box.find('.cmbwc-covers').val(covers);

		return covers;
	}

	function getAddonTotal($box, covers) {
		var total = 0;
		var selected = [];

		$box.find('.cmbwc-addon-item').each(function () {
			var $item = $(this);
			var $checkbox = $item.find('.cmbwc-addon-checkbox');
			var isChecked = $checkbox.is(':checked');

			var addonId = $item.data('addon-id');
			var addonPrice = parseFloat($item.data('addon-price') || 0);
			var followCovers = String($item.data('follow-covers')) === 'yes';

			var qty = 0;

			if (isChecked) {
				if (followCovers) {
					qty = covers;
				} else {
					qty = parseInt($item.find('.cmbwc-addon-qty').val(), 10) || 1;
				}

				total += addonPrice * qty;

				selected.push({
					id: addonId,
					qty: qty,
					follow_covers: followCovers ? 'yes' : 'no'
				});
			}
		});

		return {
			total: total,
			selected: selected
		};
	}

	function getServiceTotal($box, covers) {
		var total = 0;
		var selected = '';

		var $selected = $box.find('.cmbwc-service-radio:checked').closest('.cmbwc-service-item');

		if ($selected.length) {
			var price = parseFloat($selected.data('service-price') || 0);
			var priceType = String($selected.data('service-price-type') || 'fixed');

			selected = $selected.find('.cmbwc-service-radio').val();

			if (priceType === 'per_cover') {
				total = price * covers;
			} else {
				total = price;
			}
		}

		return {
			total: total,
			selected: selected
		};
	}

	function syncAddonQtyState($box, covers) {
		$box.find('.cmbwc-addon-item').each(function () {
			var $item = $(this);
			var checked = $item.find('.cmbwc-addon-checkbox').is(':checked');
			var followCovers = String($item.data('follow-covers')) === 'yes';
			var $qty = $item.find('.cmbwc-addon-qty');

			if (!$qty.length) {
				return;
			}

			if (!checked) {
				$qty.prop('disabled', true);
				return;
			}

			if (followCovers) {
				$qty.val(covers).prop('disabled', true);
			} else {
				$qty.prop('disabled', false);
				if ((parseInt($qty.val(), 10) || 0) < 1) {
					$qty.val(1);
				}
			}
		});
	}

	function syncWooQuantity($box, covers) {
		var $form = ensureWooForm($box);

		if (!$form || !$form.length) {
			return;
		}

		var $wooQty = $form.find('input.qty').first();
		if ($wooQty.length) {
			$wooQty.val(covers).trigger('change');
		}

		$form.find('.cmbwc-sync-covers').val(covers);
	}

	function syncWooMeta($box, covers, addonData, serviceData) {
		var $form = ensureWooForm($box);

		if (!$form || !$form.length) {
			return;
		}

		$form.find('.cmbwc-sync-covers').val(covers);
		$form.find('.cmbwc-sync-service').val(serviceData.selected);
		$form.find('.cmbwc-sync-addons').val(JSON.stringify(addonData.selected));
	}

	function updateBox($box) {
		var pricePerCover = parseFloat($box.data('price-per-cover') || 0);
		var covers = getCovers($box);

		syncAddonQtyState($box, covers);

		var menuTotal = pricePerCover * covers;
		var addonData = getAddonTotal($box, covers);
		var serviceData = getServiceTotal($box, covers);
		var grandTotal = menuTotal + addonData.total + serviceData.total;

		$box.find('.cmbwc-cover-count').text(covers);
		$box.find('.cmbwc-price-per-cover').text(formatPrice(pricePerCover));
		$box.find('.cmbwc-menu-total').text(formatPrice(menuTotal));
		$box.find('.cmbwc-addon-total').text(formatPrice(addonData.total));
		$box.find('.cmbwc-service-total').text(formatPrice(serviceData.total));
		$box.find('.cmbwc-total-price').text(formatPrice(grandTotal));

		syncWooQuantity($box, covers);
		syncWooMeta($box, covers, addonData, serviceData);
	}

	$(document).on('change input', '.cmbwc-covers', function () {
		updateBox($(this).closest('.cmbwc-menu-options'));
	});

	$(document).on('change', '.cmbwc-addon-checkbox', function () {
		updateBox($(this).closest('.cmbwc-menu-options'));
	});

	$(document).on('change input', '.cmbwc-addon-qty', function () {
		updateBox($(this).closest('.cmbwc-menu-options'));
	});

	$(document).on('change', '.cmbwc-service-radio', function () {
		updateBox($(this).closest('.cmbwc-menu-options'));
	});

	$('.cmbwc-menu-options').each(function () {
		updateBox($(this));
	});
});
