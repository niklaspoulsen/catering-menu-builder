jQuery(function ($) {
	function formatPrice(value) {
		value = parseFloat(value || 0);
		if (isNaN(value)) {
			value = 0;
		}
		return value.toFixed(2).replace('.', ',') + ' kr.';
	}

	function getNumber(value, fallback) {
		var n = parseFloat(value);
		return isNaN(n) ? fallback : n;
	}

	function getInt(value, fallback) {
		var n = parseInt(value, 10);
		return isNaN(n) ? fallback : n;
	}

	function ensureWooForm($box) {
		var $form = $('form.cart').first();

		if (!$form.length) {
			return null;
		}

		if (!$form.find('.cmbwc-sync-covers').length) {
			$form.append('<input type="hidden" name="cmbwc_covers" class="cmbwc-sync-covers" value="">');
		}

		if (!$form.find('.cmbwc-sync-service').length) {
			$form.append('<input type="hidden" name="cmbwc_selected_service" class="cmbwc-sync-service" value="">');
		}

		if (!$form.find('.cmbwc-sync-addons').length) {
			$form.append('<input type="hidden" name="cmbwc_selected_addons" class="cmbwc-sync-addons" value="">');
		}

		return $form;
	}

	function normalizeCovers($box) {
		var $input = $box.find('.cmbwc-covers');
		var covers = getInt($input.val(), 1);
		var min = getInt($box.data('minimum-covers'), 1);
		var step = getInt($box.data('cover-step'), 1);

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

		$input.val(covers);
		return covers;
	}

	function syncAddonQtyState($box, covers) {
		$box.find('.cmbwc-addon-item').each(function () {
			var $item = $(this);
			var checked = $item.find('.cmbwc-addon-checkbox').is(':checked');
			var followCovers = String($item.attr('data-follow-covers')) === 'yes';
			var $qty = $item.find('.cmbwc-addon-qty');

			if (!$qty.length) {
				return;
			}

			if (!checked) {
				$qty.prop('disabled', true);
				return;
			}

			if (followCovers) {
				$qty.val(covers);
				$qty.prop('disabled', true);
			} else {
				$qty.prop('disabled', false);
				if (getInt($qty.val(), 0) < 1) {
					$qty.val(1);
				}
			}
		});
	}

	function calculateAddons($box, covers) {
		var total = 0;
		var selected = [];

		$box.find('.cmbwc-addon-item').each(function () {
			var $item = $(this);
			var checked = $item.find('.cmbwc-addon-checkbox').is(':checked');

			if (!checked) {
				return;
			}

			var addonId = $item.attr('data-addon-id');
			var addonPrice = getNumber($item.attr('data-addon-price'), 0);
			var followCovers = String($item.attr('data-follow-covers')) === 'yes';
			var qty = followCovers ? covers : getInt($item.find('.cmbwc-addon-qty').val(), 1);

			if (qty < 1) {
				qty = 1;
			}

			total += addonPrice * qty;

			selected.push({
				id: addonId,
				qty: qty,
				follow_covers: followCovers ? 'yes' : 'no'
			});
		});

		return {
			total: total,
			selected: selected
		};
	}

	function calculateService($box, covers) {
		var total = 0;
		var selected = '';

		var $radio = $box.find('.cmbwc-service-radio:checked');
		if (!$radio.length) {
			return {
				total: 0,
				selected: ''
			};
		}

		var $item = $radio.closest('.cmbwc-service-item');
		var price = getNumber($item.attr('data-service-price'), 0);
		var priceType = String($item.attr('data-service-price-type') || 'fixed');

		selected = $radio.val();

		if (priceType === 'per_cover') {
			total = price * covers;
		} else {
			total = price;
		}

		return {
			total: total,
			selected: selected
		};
	}

	function syncWooForm($box, covers, addonData, serviceData) {
		var $form = ensureWooForm($box);
		if (!$form) {
			return;
		}

		var $wooQty = $form.find('input.qty').first();
		if ($wooQty.length) {
			$wooQty.val(covers).trigger('change');
		}

		$form.find('.cmbwc-sync-covers').val(covers);
		$form.find('.cmbwc-sync-service').val(serviceData.selected);
		$form.find('.cmbwc-sync-addons').val(JSON.stringify(addonData.selected));
	}

	function updateBox($box) {
		if (!$box.length) {
			return;
		}

		var covers = normalizeCovers($box);
		var pricePerCover = getNumber($box.attr('data-price-per-cover'), 0);

		syncAddonQtyState($box, covers);

		var menuTotal = pricePerCover * covers;
		var addonData = calculateAddons($box, covers);
		var serviceData = calculateService($box, covers);
		var grandTotal = menuTotal + addonData.total + serviceData.total;

		$box.find('.cmbwc-price-per-cover').text(formatPrice(pricePerCover));
		$box.find('.cmbwc-cover-count').text(covers);
		$box.find('.cmbwc-menu-total').text(formatPrice(menuTotal));
		$box.find('.cmbwc-addon-total').text(formatPrice(addonData.total));
		$box.find('.cmbwc-service-total').text(formatPrice(serviceData.total));
		$box.find('.cmbwc-total-price').text(formatPrice(grandTotal));

		syncWooForm($box, covers, addonData, serviceData);
	}

	$(document).on('input change', '.cmbwc-covers', function () {
		updateBox($(this).closest('.cmbwc-menu-options'));
	});

	$(document).on('change', '.cmbwc-addon-checkbox', function () {
		updateBox($(this).closest('.cmbwc-menu-options'));
	});

	$(document).on('input change', '.cmbwc-addon-qty', function () {
		updateBox($(this).closest('.cmbwc-menu-options'));
	});

	$(document).on('change', '.cmbwc-service-radio', function () {
		updateBox($(this).closest('.cmbwc-menu-options'));
	});

	$('.cmbwc-menu-options').each(function () {
		updateBox($(this));
	});
});
