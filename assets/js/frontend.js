jQuery(function ($) {
	function formatPrice(value) {
		value = parseFloat(value || 0);
		if (isNaN(value)) {
			value = 0;
		}
		return value.toLocaleString('da-DK', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		}) + ' kr.';
	}

	function getNumber(value, fallback) {
		var n = parseFloat(value);
		return isNaN(n) ? fallback : n;
	}

	function getInt(value, fallback) {
		var n = parseInt(value, 10);
		return isNaN(n) ? fallback : n;
	}

	function getWooForm($box) {
		var $form = $box.closest('form.cart');
		if ($form.length) {
			return $form;
		}
		return $('form.cart').first();
	}

	function normalizeCovers($box) {
		var $input = $box.find('.cmbwc-covers');
		var covers = getInt($input.val(), 1);
		var min = getInt($box.attr('data-minimum-covers'), 1);
		var step = getInt($box.attr('data-cover-step'), 1);

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

	function syncVisualState($box) {
		$box.find('.cmbwc-addon-item').each(function () {
			var $item = $(this);
			var checked = $item.find('.cmbwc-addon-checkbox').prop('checked');
			$item.toggleClass('is-selected', !!checked);
		});

		$box.find('.cmbwc-service-item').each(function () {
			var $item = $(this);
			var checked = $item.find('.cmbwc-service-radio').prop('checked');
			$item.toggleClass('is-selected', !!checked);
		});
	}

	function syncAddonQtyState($box, covers) {
		$box.find('.cmbwc-addon-item').each(function () {
			var $item = $(this);
			var checked = $item.find('.cmbwc-addon-checkbox').prop('checked');
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
				$qty.val(covers).prop('disabled', true);
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

		$box.find('.cmbwc-addon-checkbox:checked').each(function () {
			var $checkbox = $(this);
			var $item = $checkbox.closest('.cmbwc-addon-item');

			var addonId = getInt($item.attr('data-addon-id'), 0);
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
		var total = priceType === 'per_cover' ? price * covers : price;

		return {
			total: total,
			selected: $radio.val()
		};
	}

	function syncWooForm($box, covers, addonData, serviceData) {
		var $form = getWooForm($box);

		if (!$form.length) {
			return;
		}

		var addonsJson = JSON.stringify(addonData.selected);

		$form.find('input[name="cmbwc_covers"]').val(String(covers));
		$form.find('input[name="cmbwc_selected_service"]').val(serviceData.selected);
		$form.find('input[name="cmbwc_selected_addons"]').val(addonsJson);

		$box.find('.cmbwc-local-sync-covers').val(String(covers));
		$box.find('.cmbwc-local-sync-service').val(serviceData.selected);
		$box.find('.cmbwc-local-sync-addons').val(addonsJson);
	}

	function updateBox($box) {
		if (!$box.length) {
			return;
		}

		var covers = normalizeCovers($box);
		var pricePerCover = getNumber($box.attr('data-price-per-cover'), 0);

		syncVisualState($box);
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

	function updateAllBoxes() {
		$('.cmbwc-menu-options').each(function () {
			updateBox($(this));
		});
	}

	$(document).on('change', '.cmbwc-addon-checkbox', function () {
		updateBox($(this).closest('.cmbwc-menu-options'));
	});

	$(document).on('input change', '.cmbwc-addon-qty', function () {
		updateBox($(this).closest('.cmbwc-menu-options'));
	});

	$(document).on('change', '.cmbwc-service-radio', function () {
		updateBox($(this).closest('.cmbwc-menu-options'));
	});

	$(document).on('input change', '.cmbwc-covers', function () {
		updateBox($(this).closest('.cmbwc-menu-options'));
	});

	$(document).on('submit', 'form.cart', function () {
		updateAllBoxes();
	});

	updateAllBoxes();
});
