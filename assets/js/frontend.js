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

	function ensureFormFields($form, $box) {
		if (!$form.length) {
			return;
		}

		var minCovers = getInt($box.attr('data-minimum-covers'), 1);
		var defaultService = $box.find('.cmbwc-service-radio:checked').first().val() || '';

		if (!$form.find('input[name="cmbwc_covers"]').length) {
			$form.append('<input type="hidden" name="cmbwc_covers" class="cmbwc-form-sync-covers" value="' + minCovers + '">');
		}

		if (!$form.find('input[name="cmbwc_selected_service"]').length) {
			$form.append('<input type="hidden" name="cmbwc_selected_service" class="cmbwc-form-sync-service" value="' + defaultService + '">');
		}

		if (!$form.find('input[name="cmbwc_selected_addons"]').length) {
			$form.append('<input type="hidden" name="cmbwc_selected_addons" class="cmbwc-form-sync-addons" value="[]">');
		}
	}

	function normalizeCovers($box) {
		var $input = $box.find('.cmbwc-covers');
		var rawValue = String($input.val() || '').replace(/[^\d]/g, '');
		var covers = getInt(rawValue, 1);
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

	function normalizeAddonQty($input) {
		if (!$input.length) {
			return 1;
		}

		var rawValue = String($input.val() || '').replace(/[^\d]/g, '');
		var qty = getInt(rawValue, 1);

		if (qty < 1) {
			qty = 1;
		}

		$input.val(qty);
		return qty;
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

		ensureFormFields($form, $box);

		var addonsJson = JSON.stringify(addonData.selected);

		$form.find('input[name="cmbwc_covers"]').val(String(covers));
		$form.find('input[name="cmbwc_selected_service"]').val(serviceData.selected);
		$form.find('input[name="cmbwc_selected_addons"]').val(addonsJson);

		$box.find('.cmbwc-local-sync-covers').val(String(covers));
		$box.find('.cmbwc-local-sync-service').val(serviceData.selected);
		$box.find('.cmbwc-local-sync-addons').val(addonsJson);

		console.log('CMBWC syncWooForm', {
			covers: covers,
			service: serviceData.selected,
			addons: addonData.selected,
			addonsJson: addonsJson,
			formFound: !!$form.length
		});
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

	// Tillad fri indtastning mens brugeren skriver i addon qty
	$(document).on('input', '.cmbwc-addon-qty', function () {
		var value = String($(this).val() || '').replace(/[^\d]/g, '');
		$(this).val(value);
	});

	// Håndhæv først minimum når feltet forlades / ændres
	$(document).on('change blur', '.cmbwc-addon-qty', function () {
		normalizeAddonQty($(this));
		updateBox($(this).closest('.cmbwc-menu-options'));
	});

	$(document).on('change', '.cmbwc-service-radio', function () {
		updateBox($(this).closest('.cmbwc-menu-options'));
	});

	// Tillad fri indtastning mens brugeren skriver antal kuverter
	$(document).on('input', '.cmbwc-covers', function () {
		var value = String($(this).val() || '').replace(/[^\d]/g, '');
		$(this).val(value);
	});

	// Håndhæv først minimum når feltet forlades / ændres
	$(document).on('change blur', '.cmbwc-covers', function () {
		updateBox($(this).closest('.cmbwc-menu-options'));
	});

	$(document).on('submit', 'form.cart', function () {
		updateAllBoxes();

		var $form = $(this);
		console.log('CMBWC form submit values', {
			covers: $form.find('input[name="cmbwc_covers"]').val(),
			service: $form.find('input[name="cmbwc_selected_service"]').val(),
			addons: $form.find('input[name="cmbwc_selected_addons"]').val()
		});
	});

	updateAllBoxes();
});

document.addEventListener('DOMContentLoaded', function () {
  const qtyInputs = document.querySelectorAll('input[type="number"]');

  qtyInputs.forEach(function (input) {
    input.addEventListener('focus', function () {
      setTimeout(() => input.select(), 0);
    });

    input.addEventListener('click', function () {
      setTimeout(() => input.select(), 0);
    });

    input.addEventListener('mouseup', function (e) {
      e.preventDefault();
    });

    if (input.parentElement.classList.contains('wcr-qty-wrap')) return;

    const wrap = document.createElement('div');
    wrap.className = 'wcr-qty-wrap';

    const minusBtn = document.createElement('button');
    minusBtn.type = 'button';
    minusBtn.className = 'wcr-qty-btn wcr-qty-minus';
    minusBtn.textContent = '−';

    const plusBtn = document.createElement('button');
    plusBtn.type = 'button';
    plusBtn.className = 'wcr-qty-btn wcr-qty-plus';
    plusBtn.textContent = '+';

    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(minusBtn);
    wrap.appendChild(input);
    wrap.appendChild(plusBtn);

    const step = parseInt(input.step || 1, 10);
    const min = input.min !== '' ? parseInt(input.min, 10) : 1;
    const max = input.max !== '' ? parseInt(input.max, 10) : null;

    minusBtn.addEventListener('click', function () {
      let value = parseInt(input.value || min, 10);
      value = isNaN(value) ? min : value - step;
      if (value < min) value = min;
      input.value = value;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });

    plusBtn.addEventListener('click', function () {
      let value = parseInt(input.value || min, 10);
      value = isNaN(value) ? min : value + step;
      if (max !== null && value > max) value = max;
      input.value = value;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });
});
